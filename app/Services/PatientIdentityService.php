<?php

namespace App\Services;

use App\Models\Code;
use App\Models\CodeSystem;
use App\Models\Patient;
use App\Services\Catalogs\CodeCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Identidad del paciente según la Res. 866 de 2021 y el RDA del IHCE.
 *
 * El RDA exige que el paciente coincida con el registro nacional en tipo y
 * número de documento, primer nombre, primer apellido y sexo biológico. Las
 * fichas viejas tienen el nombre en un solo campo y el tipo de documento y el
 * municipio como texto libre, así que aquí se completan SIN ADIVINAR: solo se
 * mapea lo que coincide exactamente con el catálogo, y lo demás queda marcado
 * para que una persona lo revise en "Fichas por revisar".
 */
class PatientIdentityService
{
    public const REASON_NAMES = 'nombres';

    public const REASON_DOCUMENT_TYPE = 'tipo_documento';

    public const REASON_MUNICIPALITY = 'municipio';

    public const REASON_BIOLOGICAL_SEX = 'sexo_biologico';

    /** Partículas que van pegadas al apellido o al nombre siguiente. */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa'];

    public function __construct(
        private readonly CodeCatalog $catalog,
    ) {}

    public function fullName(?string $firstName, ?string $middleName, ?string $firstSurname, ?string $secondSurname): string
    {
        return collect([$firstName, $middleName, $firstSurname, $secondSurname])
            ->map(fn (?string $part) => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    /**
     * Propuesta de separación de un nombre completo. Es una heurística: la
     * ficha queda marcada para revisión siempre que se use.
     *
     * Convención colombiana más común: nombres primero y dos apellidos al
     * final. Con tres palabras no se sabe si son dos nombres y un apellido o
     * un nombre y dos apellidos; se asume lo segundo y se marca igual.
     *
     * @return array{first_name: ?string, middle_name: ?string, first_surname: ?string, second_surname: ?string}
     */
    public function splitName(string $fullName): array
    {
        $words = $this->groupParticles(preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return match (count($words)) {
            0 => ['first_name' => null, 'middle_name' => null, 'first_surname' => null, 'second_surname' => null],
            1 => ['first_name' => $words[0], 'middle_name' => null, 'first_surname' => null, 'second_surname' => null],
            2 => ['first_name' => $words[0], 'middle_name' => null, 'first_surname' => $words[1], 'second_surname' => null],
            3 => ['first_name' => $words[0], 'middle_name' => null, 'first_surname' => $words[1], 'second_surname' => $words[2]],
            default => [
                'first_name' => $words[0],
                'middle_name' => implode(' ', array_slice($words, 1, count($words) - 3)),
                'first_surname' => $words[count($words) - 2],
                'second_surname' => $words[count($words) - 1],
            ],
        };
    }

    /**
     * Revisa las fichas existentes: propone nombres, mapea lo exacto y marca
     * lo demás. Se puede correr varias veces (por ejemplo, después de importar
     * los catálogos): nunca pisa lo que una persona ya completó.
     *
     * @return array{reviewed: int, pending: int}
     */
    public function backfillAll(): array
    {
        $documentTypes = $this->catalogIndex('tipo_documento');
        $municipalities = $this->catalogIndex('divipola');

        $reviewed = 0;
        $pending = 0;

        Patient::withTrashed()->orderBy('id')->each(function (Patient $patient) use ($documentTypes, $municipalities, &$reviewed, &$pending) {
            $reasons = collect($patient->identity_review_reasons ?? []);

            if ($patient->first_name === null && $patient->full_name) {
                $patient->forceFill($this->splitName($patient->full_name));
                $reasons->push(self::REASON_NAMES);
            }

            $reasons = $reasons->reject(fn (string $reason) => in_array($reason, [self::REASON_DOCUMENT_TYPE, self::REASON_MUNICIPALITY, self::REASON_BIOLOGICAL_SEX], true));

            $documentType = $this->exactMatch($documentTypes, $patient->document_type);
            if ($documentType !== null) {
                $patient->document_type = $documentType->code;
            } else {
                $reasons->push(self::REASON_DOCUMENT_TYPE);
            }

            if (! $this->catalog->isActive('divipola', $patient->municipality_code)) {
                $municipality = $this->exactMatch($municipalities, $patient->municipality, preferDepartment: 'choco');
                if ($municipality !== null) {
                    $patient->municipality_code = $municipality->code;
                    $patient->municipality = $municipality->display;
                } else {
                    $reasons->push(self::REASON_MUNICIPALITY);
                }
            }

            if ($patient->biological_sex === null) {
                $reasons->push(self::REASON_BIOLOGICAL_SEX);
            }

            $reasons = $reasons->unique()->values();
            $patient->identity_review_reasons = $reasons->isEmpty() ? null : $reasons->all();
            $patient->identity_review_pending = $reasons->isNotEmpty();

            if ($patient->isDirty()) {
                $patient->save();
            }

            $reviewed++;
            $pending += $reasons->isNotEmpty() ? 1 : 0;
        });

        return ['reviewed' => $reviewed, 'pending' => $pending];
    }

    public static function normalize(?string $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->squish()->toString();
    }

    /**
     * Códigos activos de un catálogo, indexados por código y por nombre
     * normalizados. Vacío si el catálogo no está importado.
     *
     * @return Collection<string, Collection<int, Code>>
     */
    private function catalogIndex(string $system): Collection
    {
        $systemId = CodeSystem::where('key', $system)->value('id');

        if ($systemId === null) {
            return collect();
        }

        $index = collect();
        Code::where('code_system_id', $systemId)->where('active', true)->get()
            ->each(function (Code $code) use ($index) {
                foreach (array_unique([self::normalize($code->code), self::normalize($code->display)]) as $key) {
                    $index[$key] = ($index[$key] ?? collect())->push($code);
                }
            });

        return $index;
    }

    /**
     * El único código que coincide exactamente con el texto, o null.
     *
     * Si hay varios (un municipio que se llama igual en dos departamentos), se
     * prefiere el que tenga el departamento indicado en alguna de sus columnas
     * del archivo oficial. Si aún quedan varios, o ninguno, no se adivina.
     */
    private function exactMatch(Collection $index, ?string $text, ?string $preferDepartment = null): ?Code
    {
        $candidates = $index[self::normalize($text)] ?? collect();
        $candidates = $candidates->unique('id')->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($preferDepartment !== null && $candidates->count() > 1) {
            $preferred = $candidates->filter(fn (Code $code) => collect($code->extra ?? [])
                ->contains(fn ($value) => self::normalize((string) $value) === $preferDepartment));

            if ($preferred->count() === 1) {
                return $preferred->first();
            }
        }

        return null;
    }

    /**
     * Une las partículas ("de", "del", "la"...) con la palabra que sigue, para
     * que "María de los Ángeles" o "Pérez de la Cruz" no se partan mal.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function groupParticles(array $words): array
    {
        $grouped = [];
        $pending = [];

        foreach ($words as $word) {
            if (in_array(self::normalize($word), self::PARTICLES, true)) {
                $pending[] = $word;

                continue;
            }

            $grouped[] = trim(implode(' ', [...$pending, $word]));
            $pending = [];
        }

        if ($pending !== []) {
            $last = array_pop($grouped);
            $grouped[] = trim(implode(' ', array_filter([$last, ...$pending])));
        }

        return $grouped;
    }
}

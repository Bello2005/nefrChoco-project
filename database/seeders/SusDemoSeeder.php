<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\SusResponse;
use App\Models\User;
use App\Support\SusInstrument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Respuestas SUS de demostración, para ver el reporte de usabilidad con datos
 * sin esperar a que el personal y los pacientes respondan.
 *
 * Cada persona responde una sola vez, así que cada respuesta necesita su propia
 * cuenta. Esas cuentas se crean desactivadas: existen como autoras de la
 * respuesta, pero nadie puede entrar con ellas. Las cuentas de demo con las que
 * sí se entra (Dra. Ana Mosquera, Juan Perea…) quedan sin responder, para poder
 * llenar el cuestionario a mano.
 */
class SusDemoSeeder extends Seeder
{
    private const RESPONDENTS_PER_ROLE = 6;

    private const EMAIL_DOMAIN = 'encuesta-demo.nefrochoco.co';

    /**
     * Comentarios de ejemplo sobre la plataforma, no sobre salud.
     */
    private const COMMENTS = [
        'Me costó encontrar dónde ver mis citas la primera vez.',
        'Con la señal de la vereda se demora en cargar, pero no se pierde lo que escribo.',
        'Las letras grandes ayudan mucho.',
        'Sería bueno un video corto que explique cómo entrar a la teleconsulta.',
        null,
        null,
    ];

    public function run(): void
    {
        // Datos inventados en producción dañarían un reporte que la IPS usa
        // para decidir: aquí se detiene aunque se pase --force, salvo en el VPS
        // de pruebas, que corre como producción y enciende ALLOW_DEMO_DATA.
        if (app()->environment('production') && ! config('nefrochoco.allow_demo_data')) {
            throw new RuntimeException('SusDemoSeeder no se corre en producción. En el VPS de pruebas, pon ALLOW_DEMO_DATA=true en el .env.');
        }

        foreach ([Role::Medico, Role::Paciente] as $role) {
            foreach (range(1, self::RESPONDENTS_PER_ROLE) as $number) {
                $user = User::firstOrCreate(
                    ['email' => "{$role->value}{$number}@".self::EMAIL_DOMAIN],
                    [
                        'name' => "Encuesta de prueba · {$role->label()} {$number}",
                        'password' => Hash::make(Str::random(40)),
                    ],
                );
                // No están en $fillable de User, así que se asignan directo.
                $user->forceFill(['email_verified_at' => now(), 'deactivated_at' => now()])->save();
                $user->assignRole($role->value);

                // Volver a correrlo no duplica: la respuesta es una por cuenta.
                if (SusResponse::where('user_id', $user->id)->exists()) {
                    continue;
                }

                $answers = $this->randomAnswers();

                $response = new SusResponse([
                    'user_id' => $user->id,
                    'role' => $role->value,
                    'answers' => $answers,
                    'score' => SusInstrument::score($answers),
                    'comments' => self::COMMENTS[array_rand(self::COMMENTS)],
                ]);
                // Fechas repartidas en las últimas semanas, como llegarían de verdad.
                $response->created_at = now()->subDays(random_int(0, 20));
                $response->save();
            }
        }
    }

    /**
     * Respuestas al azar pero coherentes: cada persona tiene una opinión de
     * fondo (le gustó más o menos) y responde alrededor de ella, para que el
     * reporte muestre puntajes repartidos y no todos cerca de 50.
     *
     * @return array<int, int>
     */
    private function randomAnswers(): array
    {
        $liking = random_int(SusInstrument::MIN_ANSWER, SusInstrument::MAX_ANSWER);
        $answers = [];

        foreach (range(1, SusInstrument::ITEM_COUNT) as $item) {
            // Los ítems impares son positivos y los pares negativos: a quien le
            // gustó la plataforma responde alto en los impares y bajo en los pares.
            $leaning = $item % 2 === 1 ? $liking : SusInstrument::MIN_ANSWER + SusInstrument::MAX_ANSWER - $liking;

            $answers[$item] = max(SusInstrument::MIN_ANSWER, min(SusInstrument::MAX_ANSWER, $leaning + random_int(-1, 1)));
        }

        return $answers;
    }
}

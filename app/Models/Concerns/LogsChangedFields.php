<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Rastro de cambios sobre datos clínicos (Ley 1581 de 2012).
 *
 * Registra qué campos tocó cada persona y cuándo, nunca con qué valores. Es
 * deliberado: buena parte de estos campos va cifrada en reposo y
 * `activity_log.properties` es JSON sin cifrar, así que copiar ahí los valores
 * anularía el cifrado. Con el nombre del campo alcanza para demostrar quién
 * modificó qué, y el contenido vigente se consulta en la fila, cuya lectura ya
 * queda auditada por ClinicalAccessAuditor.
 *
 * Se escribe a mano en lugar de usar el registro automático de atributos de
 * spatie/laravel-activitylog: en la versión 5 del paquete ese camino deja las
 * propiedades vacías, así que el rastro decía que alguien tocó el registro
 * pero nunca qué.
 */
trait LogsChangedFields
{
    /** Columnas de control: no aportan nada al rastro clínico. */
    private const IGNORED = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public static function bootLogsChangedFields(): void
    {
        static::created(fn (Model $model) => $model->recordFieldChange('created'));
        static::updated(fn (Model $model) => $model->recordFieldChange('updated'));
        static::deleted(fn (Model $model) => $model->recordFieldChange('deleted'));
    }

    public function recordFieldChange(string $event): void
    {
        $fields = array_keys(Arr::except(
            $event === 'created' ? $this->getAttributes() : $this->getChanges(),
            self::IGNORED,
        ));

        // Un guardado que no cambió nada no merece una fila de auditoría.
        if ($event === 'updated' && $fields === []) {
            return;
        }

        activity()
            ->performedOn($this)
            ->withProperties(['campos' => $fields])
            ->event($event)
            ->log($this->changeDescription($event));
    }

    private function changeDescription(string $event): string
    {
        $model = class_basename($this);

        return match ($event) {
            'created' => "Creó un registro de {$model}",
            'updated' => "Modificó un registro de {$model}",
            default => "Eliminó un registro de {$model}",
        };
    }
}

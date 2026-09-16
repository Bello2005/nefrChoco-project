<?php

namespace App\Support\ClinicalRules;

/**
 * Recomendación generada por una regla explicable.
 *
 * `reason` es obligatorio y no es decorativo: dice qué regla se disparó y con
 * qué dato concreto. Sin eso el profesional no puede juzgar si la sugerencia
 * aplica a la persona que tiene enfrente, y el sistema pasaría a ser una caja
 * negra que ordena conductas en vez de una ayuda que las argumenta.
 */
final class Recommendation
{
    public function __construct(
        public readonly string $ruleKey,
        public readonly Priority $priority,
        public readonly string $title,
        public readonly string $reason,
        public readonly string $action,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleKey,
            'priority' => $this->priority->value,
            'priorityLabel' => $this->priority->label(),
            'title' => $this->title,
            'reason' => $this->reason,
            'action' => $this->action,
        ];
    }
}

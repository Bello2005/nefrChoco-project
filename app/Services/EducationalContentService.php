<?php

namespace App\Services;

use App\Models\EducationalContent;

class EducationalContentService
{
    public function create(array $data): EducationalContent
    {
        return EducationalContent::create($this->withOfflineResolved($data));
    }

    public function update(EducationalContent $content, array $data): EducationalContent
    {
        $content->update($this->withOfflineResolved($data));

        return $content;
    }

    /**
     * Sin cuerpo propio no hay nada que guardar en el teléfono: un enlace externo
     * vive en otro dominio y el service worker no lo intercepta. Marcarlo como
     * disponible sin conexión le prometería al paciente algo que no ocurre.
     */
    private function withOfflineResolved(array $data): array
    {
        if (blank($data['body'] ?? null)) {
            $data['available_offline'] = false;
        }

        return $data;
    }

    public function delete(EducationalContent $content): void
    {
        $content->delete();
    }
}

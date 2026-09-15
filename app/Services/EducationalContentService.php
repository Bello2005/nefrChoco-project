<?php

namespace App\Services;

use App\Models\EducationalContent;

class EducationalContentService
{
    public function create(array $data): EducationalContent
    {
        return EducationalContent::create($data);
    }

    public function update(EducationalContent $content, array $data): EducationalContent
    {
        $content->update($data);

        return $content;
    }

    public function delete(EducationalContent $content): void
    {
        $content->delete();
    }
}

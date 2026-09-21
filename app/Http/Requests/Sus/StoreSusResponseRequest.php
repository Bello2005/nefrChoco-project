<?php

namespace App\Http\Requests\Sus;

use App\Support\SusInstrument;
use Illuminate\Foundation\Http\FormRequest;

class StoreSusResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Los diez ítems son obligatorios: el puntaje SUS no admite respuestas
        // parciales, porque cada ítem aporta a una suma fija de 0 a 40.
        $rules = [
            'answers' => ['required', 'array', 'size:'.SusInstrument::ITEM_COUNT],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (range(1, SusInstrument::ITEM_COUNT) as $item) {
            $rules["answers.{$item}"] = [
                'required',
                'integer',
                'between:'.SusInstrument::MIN_ANSWER.','.SusInstrument::MAX_ANSWER,
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['comments' => 'comentarios'];

        foreach (range(1, SusInstrument::ITEM_COUNT) as $item) {
            $attributes["answers.{$item}"] = "afirmación {$item}";
        }

        return $attributes;
    }

    public function messages(): array
    {
        $messages = [];

        foreach (range(1, SusInstrument::ITEM_COUNT) as $item) {
            $messages["answers.{$item}.required"] = "Falta responder la afirmación {$item}.";
        }

        return $messages;
    }
}

<?php

namespace App\Http\Requests\BackOffice;

use App\Data\PreselectionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class PreselectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'rules' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return ['starts_at' => 'début de la présélection', 'ends_at' => 'fin de la présélection'];
    }

    /**
     * Report the rules DTO errors under "rules.*".
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                try {
                    PreselectionRules::fromArray((array) $this->input('rules', []));
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add("rules.{$key}", $messages[0]);
                    }
                }
            },
        ];
    }
}

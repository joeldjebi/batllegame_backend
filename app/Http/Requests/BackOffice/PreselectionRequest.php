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
            'vote_ends_at' => ['nullable', 'date', 'after_or_equal:ends_at'],
            'deliberation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'rules' => ['nullable', 'array'],
        ];
    }

    /**
     * Validated data with the timeline defaults: the vote ends with the submissions,
     * no extra deliberation time.
     *
     * @return array<string, mixed>
     */
    public function preselectionData(): array
    {
        return [...$this->validated(), 'deliberation_hours' => (int) $this->validated('deliberation_hours', 0)];
    }

    public function attributes(): array
    {
        return ['starts_at' => 'début de la présélection', 'ends_at' => 'fin des envois', 'vote_ends_at' => 'fin du vote', 'deliberation_hours' => 'durée de délibération'];
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

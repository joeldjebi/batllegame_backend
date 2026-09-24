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
            'ends_at' => ['required', 'date', 'after:now'],
            'vote_ends_at' => ['nullable', 'date', 'after_or_equal:ends_at'],
            'deliberation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'rules' => ['nullable', 'array'],
            'split_judging' => ['boolean'],
            'judges_per_entry' => ['required_if_accepted:split_judging', 'nullable', 'integer', 'min:1', 'max:20'],
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
        return [
            ...collect($this->validated())->except(['split_judging'])->all(),
            'deliberation_hours' => (int) $this->validated('deliberation_hours', 0),
            // Null: every judge scores every entry (default).
            'judges_per_entry' => $this->boolean('split_judging') ? $this->integer('judges_per_entry') : null,
        ];
    }

    public function attributes(): array
    {
        return ['ends_at' => 'date limite d\'envoi', 'vote_ends_at' => 'fin du vote', 'deliberation_hours' => 'durée de délibération', 'judges_per_entry' => 'jurés par prestation'];
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

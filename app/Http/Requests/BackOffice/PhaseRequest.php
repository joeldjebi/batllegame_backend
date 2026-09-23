<?php

namespace App\Http\Requests\BackOffice;

use App\Data\PhaseRules;
use App\Enums\CompetitionMode;
use App\Enums\PhaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class PhaseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PhaseType::class)],
            'mode' => ['nullable', Rule::enum(CompetitionMode::class)],
            'qualifiers_per_group' => [
                Rule::requiredIf($this->input('type') === PhaseType::Groups->value),
                'nullable', 'integer', 'min:1', 'max:16',
            ],
            'rules' => ['nullable', 'array'],
        ];
    }

    /**
     * Validate the rules DTO here so its errors are reported under "rules.*".
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = PhaseType::tryFrom((string) $this->input('type'));

                if ($type === null) {
                    return;
                }

                try {
                    PhaseRules::fromArray((array) $this->input('rules', []))->assertCompatibleWith($type);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add("rules.{$key}", $messages[0]);
                    }
                }
            },
        ];
    }
}

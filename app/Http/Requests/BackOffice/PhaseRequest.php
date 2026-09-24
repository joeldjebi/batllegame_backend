<?php

namespace App\Http\Requests\BackOffice;

use App\Data\PhaseRules;
use App\Enums\CompetitionMode;
use App\Enums\PhaseType;
use App\Services\Competition\GroupPlan;
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
            // A mixed competition plays each phase either online or on site.
            'mode' => [
                Rule::requiredIf($this->route('competition')?->mode === CompetitionMode::Hybrid),
                'nullable',
                Rule::enum(CompetitionMode::class)->except([CompetitionMode::Hybrid]),
            ],
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

                // Only groups produce qualifiers: an elimination phase ends the competition.
                if ($problem = $this->sequenceProblem($type)) {
                    $validator->errors()->add('type', $problem);

                    return;
                }

                try {
                    $rules = PhaseRules::fromArray((array) $this->input('rules', []));
                    $rules->assertCompatibleWith($type);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add("rules.{$key}", $messages[0]);
                    }

                    return;
                }

                // Groups sized for the expected participants: reject an unplayable format now, not at launch.
                if ($type === PhaseType::Groups && $rules->expectedEntrants && $this->filled('qualifiers_per_group')) {
                    foreach (GroupPlan::problems($rules->expectedEntrants, (int) $rules->groupCount, $this->integer('qualifiers_per_group')) as $problem) {
                        $validator->errors()->add('rules.group_count', $problem);
                    }
                }
            },
        ];
    }

    /**
     * Validated attributes; qualifiers only exist for groups.
     *
     * @return array<string, mixed>
     */
    public function phaseData(): array
    {
        $data = $this->validated();

        if (($data['type'] ?? null) !== PhaseType::Groups->value) {
            $data['qualifiers_per_group'] = null;
        }

        return $data;
    }

    private function sequenceProblem(PhaseType $type): ?string
    {
        $competition = $this->route('competition');
        $phase = $this->route('phase');

        if ($phase === null) {
            $last = $competition->phases()->reorder('position', 'desc')->first();

            return $last && $last->type !== PhaseType::Groups
                ? "La phase {$last->position} ({$last->type->label()}) termine la compétition : aucune phase ne peut la suivre."
                : null;
        }

        return $type !== PhaseType::Groups && $phase->nextPhase() !== null
            ? 'Une autre phase suit celle-ci : seule une phase de poules peut qualifier des artistes pour la suivante.'
            : null;
    }
}

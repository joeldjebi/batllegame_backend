<?php

namespace App\Http\Requests\BackOffice;

use App\Data\PhaseRules;
use App\Data\PreselectionRules;
use App\Enums\CompetitionMode;
use App\Enums\PhaseType;
use App\Services\Competition\GroupPlan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Creation of a competition in three steps: the competition, an optional pre-selection
 * (its own period, before and apart from the phases), then the first phase, which brings
 * the rest up to the final (App\Services\Competition\PhaseCreator).
 */
class StoreCompetitionRequest extends CompetitionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $withPreselection = $this->boolean('with_preselection');
        $phaseType = $this->input('phase.type');
        $withPhase = in_array($phaseType, PhaseType::values(), true);

        return [
            ...parent::rules(),
            'with_preselection' => ['boolean'],
            'preselection.ends_at' => [Rule::requiredIf($withPreselection), 'nullable', 'date', 'after:now'],
            'preselection.vote_ends_at' => ['nullable', 'date', 'after_or_equal:preselection.ends_at'],
            'preselection.deliberation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'preselection.rules' => ['nullable', 'array'],
            // « plus_tard »: no phase yet, created from the phases tab.
            'phase.type' => ['nullable', Rule::in([...PhaseType::values(), 'plus_tard'])],
            'phase.mode' => [
                Rule::requiredIf($this->input('mode') === CompetitionMode::Hybrid->value && $withPhase),
                'nullable', Rule::enum(CompetitionMode::class)->except([CompetitionMode::Hybrid]),
            ],
            'phase.qualifiers_per_group' => [Rule::requiredIf($phaseType === PhaseType::Groups->value), 'nullable', 'integer', 'min:1', 'max:16'],
            'phase.rules' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'preselection.ends_at' => 'date limite d\'envoi',
            'preselection.vote_ends_at' => 'fin du vote',
            'preselection.deliberation_hours' => 'délibération',
            'phase.type' => 'format',
            'phase.mode' => 'mode de la phase',
            'phase.qualifiers_per_group' => 'qualifiés par poule',
        ];
    }

    /**
     * Competition attributes only (the pre-selection and the phase are created apart).
     *
     * @return array<string, mixed>
     */
    public function competitionData(): array
    {
        return collect(parent::competitionData())->except(['with_preselection', 'preselection', 'phase'])->all();
    }

    /**
     * @return ?array<string, mixed>
     */
    public function preselectionData(): ?array
    {
        if (! $this->boolean('with_preselection')) {
            return null;
        }

        $data = (array) $this->validated('preselection');

        return [...$data, 'deliberation_hours' => (int) ($data['deliberation_hours'] ?? 0), 'rules' => (array) ($data['rules'] ?? [])];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function phaseData(): ?array
    {
        $phase = (array) $this->validated('phase');

        return ($phase['type'] ?? 'plus_tard') === 'plus_tard' ? null : $phase;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->boolean('with_preselection')) {
                    try {
                        PreselectionRules::fromArray((array) $this->input('preselection.rules', []));
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $key => $messages) {
                            $validator->errors()->add("preselection.rules.{$key}", $messages[0]);
                        }
                    }
                }

                $type = PhaseType::tryFrom((string) $this->input('phase.type'));
                if ($type === null) {
                    return;
                }

                try {
                    $rules = PhaseRules::fromArray((array) $this->input('phase.rules', []));
                    $rules->assertCompatibleWith($type);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add("phase.rules.{$key}", $messages[0]);
                    }

                    return;
                }

                if ($type === PhaseType::Groups && $rules->expectedEntrants && $this->filled('phase.qualifiers_per_group')) {
                    foreach (GroupPlan::problems($rules->expectedEntrants, (int) $rules->groupCount, $this->integer('phase.qualifiers_per_group')) as $problem) {
                        $validator->errors()->add('phase.rules.group_count', $problem);
                    }
                }
            },
        ];
    }
}

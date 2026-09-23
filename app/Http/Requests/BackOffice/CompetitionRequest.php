<?php

namespace App\Http\Requests\BackOffice;

use App\Data\CompetitionSettings;
use App\Enums\CompetitionMode;
use App\Enums\Discipline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Create or update a competition. Authorization is done in the controller.
 */
class CompetitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'discipline' => [$required, Rule::enum(Discipline::class)],
            'mode' => [$required, Rule::enum(CompetitionMode::class)],
            'registration_ends_at' => ['nullable', 'date'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:1024'],
            'entry_fee' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:20000'],
            'prizes' => ['nullable', 'array', 'max:20'],
            'prizes.*.rank' => ['nullable', 'string', 'max:60'],
            'prizes.*.reward' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validated attributes, rewards cleaned: empty rows dropped, a missing rank
     * gets its position (« 1er prix », « 2e prix »…).
     *
     * @return array<string, mixed>
     */
    public function competitionData(): array
    {
        $data = $this->safe()->except('status');

        if (array_key_exists('prizes', $data)) {
            $prizes = collect($data['prizes'] ?? [])
                ->filter(fn ($p) => filled($p['reward'] ?? null))
                ->values()
                ->map(fn ($p, $i) => [
                    'rank' => trim((string) ($p['rank'] ?? '')) ?: ($i === 0 ? '1er prix' : ($i + 1).'e prix'),
                    'reward' => trim($p['reward']),
                ])
                ->all();

            $data['prizes'] = $prizes ?: null;
        }

        return $data;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                try {
                    CompetitionSettings::fromArray((array) $this->input('settings', []));
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add("settings.{$key}", $messages[0]);
                    }
                }
            },
        ];
    }
}

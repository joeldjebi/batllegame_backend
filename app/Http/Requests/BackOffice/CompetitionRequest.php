<?php

namespace App\Http\Requests\BackOffice;

use App\Data\CompetitionSettings;
use App\Enums\CompetitionMode;
use App\Enums\Discipline;
use App\Http\Requests\Concerns\HasLocationInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Create or update a competition. Authorization is done in the controller.
 */
class CompetitionRequest extends FormRequest
{
    use HasLocationInput;

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
            'regulations' => ['nullable', 'string', 'max:50000'],
            'schedule' => ['nullable', 'array', 'max:30'],
            'schedule.*.title' => ['nullable', 'string', 'max:100'],
            'schedule.*.date' => ['nullable', 'date'],
            'schedule.*.details' => ['nullable', 'string', 'max:255'],
            ...$this->locationRules(),
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

        // Venue: only from the form that shows it (a city without communes posts no commune).
        if ($this->has('city_id')) {
            $data['city_id'] = $this->validated('city_id');
            $data['commune_id'] = $this->validated('commune_id');
        }

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

        // Schedule: steps without a title dropped, dates kept as local date-times.
        if (array_key_exists('schedule', $data)) {
            $steps = collect($data['schedule'] ?? [])
                ->filter(fn ($step) => filled($step['title'] ?? null))
                ->values()
                ->map(fn ($step) => [
                    'title' => trim($step['title']),
                    'date' => filled($step['date'] ?? null) ? Carbon::parse($step['date'])->format('Y-m-d\TH:i') : null,
                    'details' => filled($step['details'] ?? null) ? trim($step['details']) : null,
                ])
                ->all();

            $data['schedule'] = $steps ?: null;
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

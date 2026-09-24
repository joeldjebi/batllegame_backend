<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\PhaseRequest;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Phase;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\PhaseCalendar;
use App\Services\Competition\PhaseCreator;
use App\Services\Competition\PhaseLauncher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * $phase is resolved through $competition->phases() (scoped binding).
 */
class PhaseController extends Controller
{
    public function store(PhaseRequest $request, Organizer $organizer, Competition $competition, PhaseCreator $creator): RedirectResponse
    {
        $this->authorize('update', $competition);

        $created = $creator->create($competition, $request->phaseData());

        return back()->with('status', PhaseCreator::message($created));
    }

    public function update(PhaseRequest $request, Organizer $organizer, Competition $competition, Phase $phase): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureNotStarted($phase);

        $phase->update($request->phaseData());

        return back()->with('status', 'Phase mise à jour.');
    }

    /**
     * Planned dates of each stage of the phase (applied when it starts).
     */
    public function calendar(Request $request, Organizer $organizer, Competition $competition, Phase $phase): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureNotStarted($phase);

        $names = PhaseCalendar::stageNames($phase);
        $request->validate([
            'calendar' => ['nullable', 'array'],
            'calendar.*.submission_deadline' => ['nullable', 'date'],
            'calendar.*.voting_opens_at' => ['nullable', 'date'],
            'calendar.*.voting_closes_at' => ['nullable', 'date'],
            'calendar.*.deliberation_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ], [], ['calendar.*.voting_closes_at' => 'fin du vote', 'calendar.*.submission_deadline' => 'date limite']);

        $timezone = $competition->settings->timezone;
        $toUtc = fn (?string $value) => filled($value) ? Carbon::parse($value, $timezone)->utc()->toIso8601String() : null;
        $calendar = [];

        foreach ((array) $request->input('calendar', []) as $name => $dates) {
            if (! in_array($name, $names, true)) {
                continue;
            }

            $calendar[$name] = [
                'submission_deadline' => $toUtc($dates['submission_deadline'] ?? null),
                'voting_opens_at' => $toUtc($dates['voting_opens_at'] ?? null),
                'voting_closes_at' => $toUtc($dates['voting_closes_at'] ?? null),
                'deliberation_minutes' => filled($dates['deliberation_minutes'] ?? null) ? (int) $dates['deliberation_minutes'] : null,
            ];

            $opens = $calendar[$name]['voting_opens_at'] ?? $calendar[$name]['submission_deadline'];
            if ($opens && $calendar[$name]['voting_closes_at'] && $calendar[$name]['voting_closes_at'] <= $opens) {
                throw ValidationException::withMessages(["calendar.{$name}.voting_closes_at" => "{$name} : la fin du vote doit suivre son ouverture (ou la date limite d'envoi)."]);
            }
        }

        // The pre-selection comes first: the competition starts once its deliberation is over.
        $preselectionEnd = $competition->preselection?->deliberationEndsAt();
        if ($preselectionEnd) {
            foreach ($calendar as $name => $dates) {
                foreach (['submission_deadline', 'voting_opens_at', 'voting_closes_at'] as $field) {
                    if ($dates[$field] && Carbon::parse($dates[$field])->lt($preselectionEnd)) {
                        throw ValidationException::withMessages(["calendar.{$name}.{$field}" => "{$name} : la compétition commence après la présélection (fin de la délibération le ".$preselectionEnd->copy()->timezone($timezone)->translatedFormat('d F à H:i').').']);
                    }
                }
            }
        }

        // Keep dates planned for rounds that disappear if the size changes back.
        $phase->forceFill(['calendar' => [...(array) $phase->calendar, ...$calendar]])->save();

        return back()->with('status', 'Calendrier de la phase enregistré : il sera appliqué à son démarrage.');
    }

    public function destroy(Organizer $organizer, Competition $competition, Phase $phase): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureNotStarted($phase);

        $phase->delete();

        return back()->with('status', 'Phase supprimée.');
    }

    /**
     * Groups: publish the ranking after the jury deliberation (qualifiers go on, the others are eliminated).
     */
    public function publish(Organizer $organizer, Competition $competition, Phase $phase, GroupResultsService $results): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $results->publish($phase);

        return back()->with('status', 'Résultats publiés : les qualifiés passent à la phase suivante.');
    }

    /**
     * Freeze the rules and generate the groups or the bracket.
     */
    public function start(Organizer $organizer, Competition $competition, Phase $phase, PhaseLauncher $launcher): RedirectResponse
    {
        $this->authorize('update', $competition);

        $launcher->start($phase);

        return back()->with('status', 'Phase démarrée : les matchs ont été générés.');
    }

    private function ensureNotStarted(Phase $phase): void
    {
        if ($phase->isFrozen()) {
            throw ValidationException::withMessages(['phase' => 'Cette phase a démarré : ses règles sont figées.']);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Enums\StageStatus;
use App\Models\BattleMatch;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\Phase;
use App\Models\Stage;
use App\Services\Competition\PhaseCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What an artist needs to follow a competition after the pre-selection: every phase and
 * stage up to the final, their group or opponent, their performance to send per stage,
 * the results, and the one next thing to do.
 */
class ArtistJourney
{
    /**
     * @return array{phases: list<array<string, mixed>>, next: ?array<string, mixed>, out: bool, champion: bool}
     */
    public function for(Participant $participant): array
    {
        $competition = $participant->competition;
        $phases = $competition->phases()->with(['stages' => fn ($q) => $q->orderBy('number')])->get();
        $matches = BattleMatch::query()
            ->whereHas('slots', fn ($q) => $q->where('participant_id', $participant->id))
            ->with(['stage', 'group', 'phase', 'slots.participant.user', 'competition'])
            ->get()
            ->keyBy('stage_id');
        $submissions = Performance::query()->where('participant_id', $participant->id)->get()->keyBy('stage_id');
        $out = in_array($participant->status, [ParticipantStatus::Eliminated, ParticipantStatus::Withdrawn, ParticipantStatus::Disqualified, ParticipantStatus::NotSelected], true);

        $rows = [];
        $next = null;
        $stillIn = ! $out;

        foreach ($phases as $phase) {
            $stages = $phase->isFrozen()
                ? $phase->stages->map(fn (Stage $stage) => $this->stage($participant, $stage, $matches->get($stage->id), $submissions->get($stage->id), $stillIn))->all()
                : array_map(fn (array $planned) => [
                    'name' => $planned['name'], 'dates' => $this->dates($planned), 'match' => null, 'submission' => null,
                    'state' => 'upcoming', 'label' => 'À venir', 'action' => null, 'stage' => null,
                ], PhaseCalendar::planned($phase));

            foreach ($stages as $stage) {
                if ($next === null && $stage['action'] !== null) {
                    $next = [...$stage['action'], 'stage' => $stage['name'], 'phase' => $phase->position];
                }
                // Eliminated in a round: the following ones are no longer reachable.
                if ($stage['state'] === 'lost') {
                    $stillIn = false;
                }
            }

            $rows[] = [
                'phase' => $phase,
                'stages' => $stages,
                'state' => $this->phaseState($phase, $stages, $out),
            ];
        }

        $final = $phases->last();
        $champion = $final?->status === PhaseStatus::Finished && $final->type !== PhaseType::Groups
            && $final->matches()->whereNull('next_match_id')->where('winner_id', $participant->id)->exists();

        return ['phases' => $rows, 'next' => $next, 'out' => $out, 'champion' => $champion];
    }

    /**
     * @return array<string, mixed>
     */
    private function stage(Participant $participant, Stage $stage, ?BattleMatch $match, ?Performance $submission, bool $stillIn): array
    {
        $online = $stage->isOnline();
        $slot = $match?->slots->firstWhere('participant_id', $participant->id);
        $row = ['name' => $stage->name, 'dates' => $this->dates($stage), 'match' => $match, 'slot' => $slot, 'submission' => $submission, 'stage' => $stage, 'action' => null];

        if ($match === null) {
            return [...$row, ...($stillIn && $stage->status !== StageStatus::Closed
                ? ['state' => 'upcoming', 'label' => 'Si tu te qualifies']
                : ['state' => 'skipped', 'label' => $stage->status === StageStatus::Closed ? 'Terminé' : 'Non atteint'])];
        }

        if ($match->is_forfeit || $slot?->is_forfeit) {
            return [...$row, 'state' => 'lost', 'label' => 'Forfait (pas de prestation à temps)'];
        }

        // Decided.
        if ($match->status === MatchStatus::Closed) {
            if ($match->isGroupMatch()) {
                if (! $match->resultsArePublic()) {
                    return [...$row, 'state' => 'waiting', 'label' => 'Poule close : résultats bientôt publiés'];
                }
                $qualified = $slot->rank !== null && $slot->rank <= ($match->phase->qualifiers_per_group ?? 1);

                return [...$row, 'state' => $qualified ? 'won' : 'lost', 'label' => ($slot->rank ? $slot->rank.'e de la poule · ' : '').($qualified ? 'Qualifié' : 'Non qualifié')];
            }

            return $match->winner_id === $participant->id
                ? [...$row, 'state' => 'won', 'label' => 'Gagné']
                : [...$row, 'state' => 'lost', 'label' => 'Éliminé'];
        }

        if ($match->status === MatchStatus::Cancelled) {
            return [...$row, 'state' => 'skipped', 'label' => 'Annulé'];
        }

        // In play.
        if ($match->isDeliberating()) {
            return [...$row, 'state' => 'current', 'label' => 'Le jury délibère', 'action' => ['type' => 'wait', 'text' => 'Le jury délibère : résultats après le '.$match->deliberation_ends_at->translatedFormat('d F à H:i').'.', 'deadline' => $match->deliberation_ends_at]];
        }

        if ($match->isVotingOpen()) {
            return [...$row, 'state' => 'current', 'label' => 'Vote du public en cours', 'action' => ['type' => 'vote', 'text' => 'Le vote est ouvert : partage ta prestation pour recevoir des votes.', 'deadline' => $match->voting_closes_at, 'match' => $match]];
        }

        if ($online && $stage->acceptsSubmissions()) {
            if ($submission === null || $submission->status === PerformanceStatus::Rejected) {
                return [...$row, 'state' => 'current', 'label' => $submission ? 'Prestation refusée : renvoie-la' : 'Prestation à envoyer', 'action' => ['type' => 'submit', 'text' => 'Envoie ta prestation avant la date limite, sinon forfait.', 'deadline' => $stage->submission_deadline, 'stageModel' => $stage]];
            }

            return [...$row, 'state' => 'current', 'label' => $submission->status === PerformanceStatus::Approved ? 'Prestation validée · remplaçable jusqu\'à la limite' : 'Prestation envoyée · en attente de validation',
                'action' => ['type' => 'sent', 'text' => 'Prestation envoyée. Tu peux la remplacer jusqu\'au '.$stage->submission_deadline->translatedFormat('d F à H:i').'.', 'deadline' => $stage->submission_deadline, 'stageModel' => $stage]];
        }

        if ($online && $stage->status === StageStatus::Pending) {
            return [...$row, 'state' => 'upcoming', 'label' => 'Envoi pas encore ouvert', 'action' => ['type' => 'wait', 'text' => 'L\'envoi de ta prestation pour « '.$stage->name.' » ouvrira bientôt'.($stage->submission_deadline ? ' (limite prévue le '.$stage->submission_deadline->translatedFormat('d F à H:i').')' : '').'.', 'deadline' => null]];
        }

        if (! $online) {
            return [...$row, 'state' => 'current', 'label' => 'Sur scène', 'action' => ['type' => 'stage', 'text' => 'Rendez-vous sur scène'.($match->scheduled_at ? ' le '.$match->scheduled_at->translatedFormat('d F à H:i') : '').' : le vote ouvre après ton passage.', 'deadline' => $match->scheduled_at]];
        }

        return [...$row, 'state' => 'waiting', 'label' => 'Envoi clos · vote bientôt', 'action' => ['type' => 'wait', 'text' => 'Envois clos : le vote va bientôt ouvrir.', 'deadline' => $stage->voting_opens_at]];
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     */
    private function phaseState(Phase $phase, array $stages, bool $out): string
    {
        $states = array_column($stages, 'state');

        return match (true) {
            in_array('lost', $states, true) => 'lost',
            in_array('current', $states, true) || in_array('waiting', $states, true) => 'current',
            $phase->status === PhaseStatus::Finished => 'done',
            $out => 'skipped',
            default => 'upcoming',
        };
    }

    /**
     * @return array{submission: ?Carbon, voting_opens: ?Carbon, voting_closes: ?Carbon}
     */
    private function dates(Stage|array $stage): array
    {
        return is_array($stage)
            ? ['submission' => $stage['submission_deadline'], 'voting_opens' => $stage['voting_opens_at'], 'voting_closes' => $stage['voting_closes_at']]
            : ['submission' => $stage->submission_deadline, 'voting_opens' => $stage->voting_opens_at, 'voting_closes' => $stage->voting_closes_at];
    }

    /**
     * Artists of my group (group phases) or my opponent (battles), me excluded.
     *
     * @return Collection<int, Participant>
     */
    public static function others(BattleMatch $match, Participant $participant): Collection
    {
        return $match->slots->pluck('participant')->filter()->reject(fn ($p) => $p->id === $participant->id)->values();
    }

    public static function isOnline(Phase $phase): bool
    {
        return $phase->effectiveMode() === CompetitionMode::Online;
    }
}

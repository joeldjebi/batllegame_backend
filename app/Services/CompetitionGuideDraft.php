<?php

namespace App\Services;

use App\Enums\CompetitionMode;
use App\Enums\MediaType;
use App\Enums\PhaseType;
use App\Enums\TieBreaker;
use App\Enums\VoteMode;
use App\Models\Competition;
use App\Models\Phase;
use App\Services\Competition\PhaseCalendar;
use Carbon\CarbonInterface;

/**
 * The automatic schedule (from the pre-selection and the planned calendar of every stage)
 * and a first draft of the regulations, written from what the organizer configured. The
 * draft is edited freely: nothing is shown to the public until saved.
 */
class CompetitionGuideDraft
{
    private string $timezone = 'UTC';

    /**
     * @return array{regulations: string}
     */
    public function make(Competition $competition): array
    {
        $competition->loadMissing(['phases.stages', 'preselection', 'criteria']);
        $this->timezone = $competition->settings->timezone ?: config('app.timezone');

        // The schedule is automatic (fullSchedule): only the regulations need a draft.
        return ['regulations' => $this->regulations($competition)];
    }

    /**
     * The schedule shown to artists and the public: the steps known from the configuration
     * (registrations, pre-selection, each stage up to the final, always up to date) with the
     * organizer's own steps slotted in by date (undated ones at the end).
     *
     * @return list<array{title: string, date: ?string, details: ?string, auto: bool}>
     */
    public function fullSchedule(Competition $competition): array
    {
        $this->timezone = $competition->settings->timezone ?: config('app.timezone');
        $steps = array_map(fn (array $step) => [...$step, 'auto' => true], $this->schedule($competition->loadMissing(['phases.stages', 'preselection'])));

        foreach ($competition->scheduleList() as $custom) {
            $custom = [...$custom, 'auto' => false];
            $position = count($steps);
            if ($custom['date']) {
                // After the last step dated before it.
                foreach ($steps as $i => $step) {
                    if ($step['date'] && $step['date'] > $custom['date']) {
                        $position = $i;
                        break;
                    }
                }
            }
            array_splice($steps, $position, 0, [$custom]);
        }

        return $steps;
    }

    /**
     * @return list<array{title: string, date: ?string, details: ?string}>
     */
    public function automaticSchedule(Competition $competition): array
    {
        $this->timezone = $competition->settings->timezone ?: config('app.timezone');

        return $this->schedule($competition->loadMissing(['phases.stages', 'preselection']));
    }

    /**
     * @return list<array{title: string, date: ?string, details: ?string}>
     */
    private function schedule(Competition $competition): array
    {
        $steps = [];
        $step = fn (string $title, ?CarbonInterface $date = null, ?string $details = null) => ['title' => $title, 'date' => $date?->copy()->timezone($this->timezone)->format('Y-m-d\TH:i'), 'details' => $details];

        $steps[] = $step('Clôture des inscriptions', $competition->registration_ends_at,
            $competition->requiresPayment() ? "Frais d'inscription : {$this->money($competition)}." : 'Inscription gratuite.');

        if ($preselection = $competition->preselection) {
            $steps[] = $step('Présélection : date limite d\'envoi', $preselection->ends_at, 'Envoi possible dès la validation de l\'inscription.');
            if ($preselection->publicVotingEnabled()) {
                $steps[] = $step('Fin du vote du public', $preselection->voteEndsAt(), 'Chacun like une seule prestation.');
            }
            $steps[] = $step('Annonce des sélectionnés', $preselection->deliberationEndsAt(), "Les {$preselection->rules->selectionSize} meilleurs sont retenus après la délibération du jury.");
        }

        foreach ($competition->phases as $phase) {
            // Each stage up to the final: the real ones once started, else the planned calendar.
            $stages = $phase->isFrozen()
                ? $phase->stages->sortBy('number')->map(fn ($s) => ['name' => $s->name, 'submission_deadline' => $s->submission_deadline, 'voting_opens_at' => $s->voting_opens_at, 'voting_closes_at' => $s->voting_closes_at])->values()->all()
                : PhaseCalendar::planned($phase);

            if (count($stages) <= 1) {
                $stage = $stages[0] ?? null;
                $steps[] = $step("Phase {$phase->position} · {$phase->type->label()}", $stage ? ($stage['voting_closes_at'] ?? $stage['submission_deadline'] ?? $stage['voting_opens_at']) : null, $this->phaseSummary($phase));

                continue;
            }

            foreach ($stages as $i => $stage) {
                $steps[] = $step($stage['name'], $stage['voting_closes_at'] ?? $stage['submission_deadline'] ?? $stage['voting_opens_at'],
                    $i === 0 ? "Phase {$phase->position} · {$this->phaseSummary($phase)}" : null);
            }
        }

        if ($competition->phases->isNotEmpty()) {
            $steps[] = $step('Résultats et remise des prix');
        }

        return $steps;
    }

    private function regulations(Competition $competition): string
    {
        $html = '<h1>Règlement · '.e($competition->name).'</h1>';

        $participation = [
            'Inscription depuis Battle Game avec un numéro de téléphone vérifié.',
            $competition->requiresPayment() ? "Frais d'inscription : {$this->money($competition)}, à régler pour valider la participation." : 'Participation gratuite.',
        ];
        if ($competition->max_participants) {
            $participation[] = "{$competition->max_participants} participants au maximum.";
        }
        if ($competition->registration_ends_at) {
            $participation[] = 'Inscriptions jusqu\'au '.$this->date($competition->registration_ends_at).'.';
        }
        if ($competition->settings->registrationRequiresApproval) {
            $participation[] = "Chaque inscription est validée par l'organisateur.";
        }
        $html .= $this->section('Participation', $participation);

        if ($preselection = $competition->preselection) {
            $weights = $preselection->effectiveWeights();
            $html .= $this->section('Présélection', [
                'Chaque artiste envoie une prestation ('.$this->media($preselection->rules->mediaTypes, $preselection->rules->mediaMaxDuration, $preselection->rules->mediaMaxSizeMb).') dès la validation de son inscription, jusqu\'au '.$this->date($preselection->ends_at).'.',
                $preselection->publicVotingEnabled()
                    ? 'Le public like une seule prestation (numéro vérifié), jusqu\'au '.$this->date($preselection->voteEndsAt()).'.'
                    : 'Pas de vote du public : seul le jury note.',
                "Note finale : jury {$weights['jury']} %".($weights['likes'] ? ", likes {$weights['likes']} %" : '').'.',
                "Les {$preselection->rules->selectionSize} premiers sont sélectionnés pour la compétition.",
            ]);
        }

        foreach ($competition->phases as $phase) {
            $html .= $this->section("Phase {$phase->position} · {$phase->type->label()}", $this->phaseRules($competition, $phase));
        }

        if ($competition->criteria->isNotEmpty()) {
            $html .= $this->section('Critères du jury', $competition->criteria->map(fn ($c) => "{$c->name} : noté sur {$c->max_points}".($c->weight > 1 ? " (coefficient {$c->weight})" : '').'.')->all());
        }

        $html .= $this->section('Conduite', ['À compléter : comportement attendu, contenus interdits, sanctions (disqualification…).']);

        return $html;
    }

    private function phaseSummary(Phase $phase): string
    {
        return match ($phase->type) {
            PhaseType::Groups => "{$phase->rules->groupCount} poule(s) de classement, {$phase->qualifiers_per_group} qualifié(s) par poule.",
            PhaseType::SingleElimination => 'Battles à 1 contre 1, élimination directe.',
            PhaseType::DoubleElimination => 'Battles à 1 contre 1, élimination après deux défaites.',
        };
    }

    /**
     * @return list<string>
     */
    private function phaseRules(Competition $competition, Phase $phase): array
    {
        $rules = $phase->rules;
        $online = $phase->effectiveMode() === CompetitionMode::Online;
        $lines = [];

        $lines[] = match ($phase->type) {
            PhaseType::Groups => "Les artistes sont répartis en {$rules->groupCount} poule(s) (".($rules->drawMethod->value === 'seed' ? 'par tête de série' : 'par tirage au sort')."). Ils ne s'affrontent pas : chacun présente sa prestation et chaque poule est classée. Les {$phase->qualifiers_per_group} premier(s) de chaque poule se qualifient à la publication des résultats.",
            PhaseType::SingleElimination => 'Battles à 1 contre 1 : le gagnant passe au tour suivant, le perdant est éliminé.',
            PhaseType::DoubleElimination => 'Battles à 1 contre 1 : une première défaite envoie dans le tableau des perdants, une seconde élimine.'.($rules->grandFinalReset ? ' Grande finale rejouée si le finaliste du tableau des perdants la gagne.' : ''),
        };

        $lines[] = ($online ? 'En ligne : ' : 'Sur scène : ').($rules->rounds > 1 ? "{$rules->rounds} passages" : '1 passage')." de {$rules->turnDuration} secondes par artiste.";

        if ($online) {
            $lines[] = 'Chaque artiste envoie sa prestation ('.$this->media($rules->mediaTypes, $rules->mediaMaxDuration, $rules->mediaMaxSizeMb).') avant la date limite ; sans prestation : forfait.';
        }

        $lines[] = match ($rules->voteMode) {
            VoteMode::Jury => 'Décision : jury uniquement.',
            VoteMode::Public => 'Décision : vote du public uniquement.',
            VoteMode::Mixed => "Décision : jury {$rules->juryWeight} % et public {$rules->publicWeight} %.",
        };

        if ($rules->usesPublic() && $competition->settings->publicVotingEnabled) {
            $lines[] = $phase->type === PhaseType::Groups
                ? 'Le public a un seul vote pour toute la phase (numéro vérifié) ; les artistes de la phase ne votent pas.'
                : 'Le public vote une fois par battle (numéro vérifié)'.(! $online && $competition->settings->onsiteVoteCode ? ', avec le code affiché dans la salle' : '').'.';
        }

        $first = $rules->tieBreakers[0] ?? TieBreaker::JuryScore;
        $lines[] = 'Égalité : départage par '.($first === TieBreaker::PublicScore ? 'le vote du public puis la note du jury' : 'la note du jury puis le vote du public').', puis la tête de série.';

        return $lines;
    }

    /**
     * @param  list<string>  $items
     */
    private function section(string $title, array $items): string
    {
        return '<h2>'.e($title).'</h2><ul>'.implode('', array_map(fn (string $item) => '<li>'.e($item).'</li>', $items)).'</ul>';
    }

    /**
     * @param  list<MediaType>  $types
     */
    private function media(array $types, int $seconds, int $megabytes): string
    {
        $minutes = $seconds >= 60 ? intdiv($seconds, 60).' min'.($seconds % 60 ? ' '.($seconds % 60).' s' : '') : $seconds.' s';

        return implode(' ou ', array_map(fn ($t) => mb_strtolower($t->label()), $types)).", {$minutes} et {$megabytes} Mo maximum";
    }

    private function money(Competition $competition): string
    {
        return number_format($competition->entry_fee, 0, ',', ' ').' '.$competition->currency;
    }

    private function date(CarbonInterface $date): string
    {
        return $date->copy()->timezone($this->timezone)->translatedFormat('d F Y à H:i');
    }
}

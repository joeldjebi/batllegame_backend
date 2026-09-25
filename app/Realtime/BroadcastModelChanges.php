<?php

namespace App\Realtime;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\PaymentStatus;
use App\Enums\PerformanceStatus;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\JuryScore;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Performance;
use App\Models\Preselection;
use App\Models\PreselectionLike;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Models\PublicVote;
use App\Models\Stage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Model observer turning important changes into realtime updates (after commit).
 *
 * Channels: the public page of the competition, its back-office, its jury, the users
 * concerned. Public pages never receive vote or like counts unless the organizer shows
 * live results. Messages become toasts on private channels only.
 */
class BroadcastModelChanges implements ShouldHandleEventsAfterCommit
{
    /** @var array<int, ?Competition> */
    private array $competitions = [];

    public function __construct(private Realtime $realtime) {}

    public function created(Model $model): void
    {
        $this->handle($model, created: true);
    }

    public function updated(Model $model): void
    {
        $this->handle($model, created: false);
    }

    public function deleted(Model $model): void
    {
        match (true) {
            $model instanceof PreselectionLike => $this->like($model),
            $model instanceof Competition => $this->push($model, [Channel::organizer($model->organizer_id), Channel::LIVE], 'competition.deleted'),
            default => null,
        };
    }

    private function handle(Model $model, bool $created): void
    {
        match (true) {
            $model instanceof Participant => $this->participant($model, $created),
            $model instanceof Payment => $this->payment($model),
            $model instanceof PreselectionSubmission => $this->media($model, 'preselection', $created),
            $model instanceof Performance => $this->media($model, 'performance', $created),
            $model instanceof PreselectionLike => $this->like($model),
            $model instanceof PreselectionScore => $this->score($model->submission?->competition_id),
            $model instanceof JuryScore => $this->score($model->competition_id),
            $model instanceof PublicVote => $this->vote($model),
            $model instanceof BattleMatch => $this->match($model),
            $model instanceof Stage => $this->stage($model),
            $model instanceof Preselection => $this->preselection($model),
            $model instanceof Judge => $this->push($this->competition($model->competition_id), [Channel::backOffice($model->competition_id), Channel::user($model->user_id)], 'judge.changed'),
            $model instanceof Competition => $this->competitionChanged($model, $created),
            default => null,
        };
    }

    private function participant(Participant $participant, bool $created): void
    {
        $competition = $this->competition($participant->competition_id);

        if ($created) {
            $this->push($competition, [Channel::backOffice($competition->id), Channel::organizer($competition->organizer_id)], 'participant.registered',
                message: "Nouvelle inscription : {$participant->stage_name}");
            $this->push($competition, [Channel::competition($competition->id), Channel::user($participant->user_id)], 'participant.registered');

            return;
        }

        if ($participant->wasChanged('status')) {
            $this->push($competition, [Channel::backOffice($competition->id), Channel::organizer($competition->organizer_id), Channel::competition($competition->id)], 'participant.status');
            $this->push($competition, [Channel::user($participant->user_id)], 'participant.status',
                message: "« {$competition->name} » : ton inscription est maintenant « {$participant->status->label()} ».");
        }
    }

    private function payment(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Paid || (! $payment->wasChanged('status') && ! $payment->wasRecentlyCreated)) {
            return;
        }

        $competition = $this->competition($payment->competition_id);
        $amount = number_format($payment->amount, 0, ',', ' ').' '.$payment->currency;

        $this->push($competition, [Channel::backOffice($competition->id), Channel::organizer($competition->organizer_id)], 'payment.paid',
            message: "Paiement reçu : {$payment->participant?->stage_name} · {$amount}");
        $this->push($competition, [Channel::user($payment->user_id)], 'payment.paid', message: "Paiement de {$amount} confirmé.");
    }

    private function media(PreselectionSubmission|Performance $media, string $kind, bool $created): void
    {
        if (! $created && ! $media->wasChanged('status')) {
            return;
        }

        $competition = $this->competition($media->competition_id);
        $artist = $media->participant;
        $bo = [Channel::backOffice($competition->id)];
        $type = "{$kind}.{$media->status->value}";

        $artistChannel = $artist ? [Channel::user($artist->user_id)] : [];

        if ($media->status === PerformanceStatus::Pending) {
            $this->push($competition, $bo, $type, message: "Prestation à valider : {$artist?->stage_name}");
            $this->push($competition, $artistChannel, $type);
        } elseif ($media->status === PerformanceStatus::Approved) {
            $this->push($competition, [...$bo, Channel::competition($competition->id)], $type);
            $this->push($competition, [Channel::jury($competition->id)], $type, message: "Nouvelle prestation à noter : {$artist?->stage_name}");
            $this->push($competition, $artistChannel, $type, message: 'Ta prestation est validée : elle est visible du public et du jury.');
        } elseif ($media->status === PerformanceStatus::Rejected) {
            $this->push($competition, $bo, $type);
            $this->push($competition, $artistChannel, $type,
                message: 'Ta prestation a été refusée'.($media->rejection_reason ? ' : '.rtrim($media->rejection_reason, '. ').'.' : '.').' Tu peux en envoyer une autre.');
        } else {
            $this->push($competition, [...$bo, ...$artistChannel], $type);
        }
    }

    private function like(PreselectionLike $like): void
    {
        $competition = $this->competition($like->competition_id);
        // Signal only (no counts in the payload): each page re-renders with the viewer's own rights,
        // so fans who liked see the counters move.
        $this->push($competition, [Channel::backOffice($competition->id), Channel::competition($competition->id)], 'preselection.like', throttle: "like:{$competition->id}");
        $this->push($competition, [Channel::user($like->user_id)], 'preselection.like');

        // The artist sees their counter move, with a toast for each new like.
        $artistId = $like->exists ? $like->submission?->participant?->user_id : null;
        if ($artistId) {
            $this->push($competition, [Channel::user($artistId)], 'preselection.like', message: 'Nouveau like sur ta prestation.');
        }
    }

    private function score(?int $competitionId): void
    {
        if ($competitionId !== null) {
            $this->push($this->competition($competitionId), [Channel::backOffice($competitionId)], 'jury.score', throttle: "score:{$competitionId}");
        }
    }

    private function vote(PublicVote $vote): void
    {
        $competition = $this->competition($vote->competition_id);
        $channels = [Channel::backOffice($competition->id), $competition->settings->showLiveResults ? Channel::competition($competition->id) : null];

        $this->push($competition, $channels, 'vote.cast', ['match_id' => $vote->match_id], throttle: "vote:{$vote->match_id}");
        $this->push($competition, [Channel::user($vote->user_id)], 'vote.cast', ['match_id' => $vote->match_id]);
    }

    private function match(BattleMatch $match): void
    {
        if (! $match->wasChanged(['status', 'voting_closes_at', 'deliberation_ends_at', 'winner_id'])) {
            return;
        }

        $competition = $this->competition($match->competition_id);
        $players = $match->slots()->whereNotNull('participant_id')->with('participant')->get()->pluck('participant')->filter();
        $names = $players->pluck('stage_name')->implode(' vs ');
        $channels = [Channel::competition($competition->id), Channel::backOffice($competition->id), Channel::organizer($competition->organizer_id), Channel::jury($competition->id), Channel::LIVE,
            ...$players->map(fn (Participant $p) => Channel::user($p->user_id))->all()];

        $this->push($competition, $channels, 'match.'.$match->status->value, ['match_id' => $match->id]);

        if ($match->wasChanged('status') && $match->status === MatchStatus::Voting) {
            $this->push($competition, [Channel::competition($competition->id)], 'match.voting', ['match_id' => $match->id], message: "Le vote est ouvert : {$names}");
            $this->push($competition, [Channel::jury($competition->id)], 'match.voting', ['match_id' => $match->id], message: "Match à noter : {$names}");
        }
    }

    private function stage(Stage $stage): void
    {
        if ($stage->wasChanged(['status', 'submission_deadline', 'voting_closes_at', 'deliberation_minutes'])) {
            $competition = $this->competition($stage->phase->competition_id);
            $users = Participant::query()->whereIn('id', $stage->participantIds())->pluck('user_id')->map(fn (int $id) => Channel::user($id))->all();

            $this->push($competition, [Channel::competition($competition->id), Channel::backOffice($competition->id), Channel::jury($competition->id), ...$users], 'stage.'.$stage->status->value);
        }
    }

    private function preselection(Preselection $preselection): void
    {
        $competition = $this->competition($preselection->competition_id);
        $channels = [Channel::competition($competition->id), Channel::backOffice($competition->id), Channel::jury($competition->id)];

        if ($preselection->wasChanged('published_at') && $preselection->published_at !== null) {
            $users = $competition->participants()->pluck('user_id')->map(fn (int $id) => Channel::user($id))->all();
            $this->push($competition, [...$channels, Channel::organizer($competition->organizer_id)], 'preselection.published');
            $this->push($competition, $users, 'preselection.published', message: "Résultats de la présélection « {$competition->name} » disponibles.");

            return;
        }

        $this->push($competition, $channels, 'preselection.changed');
    }

    private function competitionChanged(Competition $competition, bool $created): void
    {
        $this->competitions[$competition->id] = $competition;
        $channels = [Channel::organizer($competition->organizer_id), Channel::backOffice($competition->id)];

        if ($created) {
            $this->push($competition, $channels, 'competition.created');

            return;
        }

        $public = $competition->status !== CompetitionStatus::Draft ? [Channel::competition($competition->id), Channel::LIVE] : [];
        $this->push($competition, [...$channels, ...$public], $competition->wasChanged('status') ? 'competition.status' : 'competition.changed');
    }

    /**
     * @param  list<?string>  $channels
     * @param  array<string, mixed>  $data
     */
    private function push(?Competition $competition, array $channels, string $type, array $data = [], ?string $message = null, ?string $throttle = null): void
    {
        if ($competition === null) {
            return;
        }

        $this->realtime->push($channels, $type, ['competition_id' => $competition->id, ...$data], $message, $throttle);
    }

    private function competition(int $id): ?Competition
    {
        return $this->competitions[$id] ??= Competition::query()->withTrashed()->find($id);
    }
}

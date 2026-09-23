<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Enums\CompetitionStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PreselectionState;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Artist portal: an action center (pay, submit before a deadline), the journey
 * of each participation and the competitions open for registration.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $participations = $user->participations()
            ->whereHas('competition')
            ->with(['competition.organizer', 'competition.preselection', 'preselectionEntry', 'payments'])
            ->latest()
            ->get()
            ->map(fn (Participant $participant) => $this->journey($participant));

        $open = Competition::query()
            ->where('status', CompetitionStatus::Registration)
            ->where(fn ($q) => $q->whereNull('registration_ends_at')->orWhere('registration_ends_at', '>', now()))
            ->whereDoesntHave('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereDoesntHave('judges', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('organizer', fn ($q) => $q->where('status', '!=', 'suspendu'))
            ->with(['organizer', 'preselection'])
            ->withCount('participants')
            ->latest()
            ->get();

        return view('portal.artist.dashboard', [
            'participations' => $participations,
            // Most urgent first: payments, then submissions with the closest deadline.
            'actions' => $participations->filter(fn ($j) => in_array($j['action'], ['pay', 'preselection', 'stage'], true))
                ->sortBy(fn ($j) => [$j['action'] === 'pay' ? 0 : 1, $j['deadline']?->timestamp ?? PHP_INT_MAX])
                ->values(),
            'open' => $open,
        ]);
    }

    /**
     * Steps, current state and next action of one participation.
     *
     * @return array<string, mixed>
     */
    private function journey(Participant $participant): array
    {
        $competition = $participant->competition;
        $preselection = $competition->preselection;
        $state = $preselection?->state();
        $entry = $participant->preselectionEntry;
        $paid = $participant->hasPaid();
        $stage = $participant->currentStage();
        $submission = $stage?->performances()->where('participant_id', $participant->id)->first();
        $playing = $stage && in_array($participant->id, $stage->participantIds(), true);
        $out = in_array($participant->status, [ParticipantStatus::NotSelected, ParticipantStatus::Eliminated, ParticipantStatus::Withdrawn, ParticipantStatus::Disqualified], true);

        $steps = [['Inscription', 'done']];
        if ($competition->requiresPayment()) {
            $steps[] = ['Paiement', $paid ? 'done' : 'current'];
        }
        if ($preselection) {
            $steps[] = ['Présélection', match (true) {
                ! $paid => 'todo',
                $state === PreselectionState::Published => 'done',
                default => 'current',
            }];
            $steps[] = ['Sélection', match (true) {
                $state !== PreselectionState::Published => 'todo',
                $participant->status === ParticipantStatus::NotSelected => 'failed',
                default => 'done',
            }];
        }
        $steps[] = ['Compétition', match (true) {
            $out && $participant->status !== ParticipantStatus::NotSelected => 'failed',
            $competition->status === CompetitionStatus::Finished => 'done',
            $participant->status === ParticipantStatus::Validated && $competition->status === CompetitionStatus::InProgress => 'current',
            default => 'todo',
        }];

        $canResubmitPreselection = ! $entry || $entry->status === PerformanceStatus::Rejected;

        [$action, $deadline] = match (true) {
            // Unpaid wins over any other state (also covers a registration made before the fee was set).
            $participant->status === ParticipantStatus::PaymentPending
                || (! $paid && $participant->status === ParticipantStatus::Registered) => ['pay', $preselection?->ends_at ?? $competition->registration_ends_at],
            $state === PreselectionState::Open && $participant->status === ParticipantStatus::Registered && $paid && $canResubmitPreselection => ['preselection', $preselection->ends_at],
            $playing && $stage->acceptsSubmissions() && (! $submission || $submission->status === PerformanceStatus::Rejected) && $paid => ['stage', $stage->submission_deadline],
            default => [null, null],
        };

        return compact('participant', 'competition', 'preselection', 'state', 'entry', 'paid', 'stage', 'submission', 'playing', 'steps', 'action', 'deadline', 'out');
    }
}

@props(['competition'])

@php
    use App\Enums\CompetitionStatus;

    $lifecycle = [CompetitionStatus::Draft, CompetitionStatus::Registration, CompetitionStatus::InProgress, CompetitionStatus::Finished];
    $currentStep = array_search($competition->status, $lifecycle, true);
@endphp

@if ($competition->status !== CompetitionStatus::Cancelled)
    <ol {{ $attributes->class('grid grid-cols-4 gap-2 rounded-2xl bg-white p-2 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10') }}>
        @foreach ($lifecycle as $index => $step)
            <li @class([
                'flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium',
                'bg-brand-600 text-white shadow-lift' => $index === $currentStep,
                'text-slate-700 dark:text-slate-200' => $index < $currentStep,
                'text-slate-400' => $index > $currentStep,
            ])>
                <span @class([
                    'grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold',
                    'bg-white/20' => $index === $currentStep,
                    'bg-emerald-500 text-white' => $index < $currentStep,
                    'bg-slate-100 dark:bg-white/10' => $index > $currentStep,
                ])>
                    @if ($index < $currentStep)<x-ui.icon name="check" variant="m" class="size-4" />@else{{ $index + 1 }}@endif
                </span>
                <span class="hidden truncate sm:block">{{ $step->label() }}</span>
            </li>
        @endforeach
    </ol>
@else
    <div {{ $attributes->class('flex items-center gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200') }}>
        <x-ui.icon name="x-circle" class="size-5" /> Cette compétition a été annulée.
    </div>
@endif

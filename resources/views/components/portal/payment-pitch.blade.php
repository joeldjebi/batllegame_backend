@props(['competition', 'deadline' => null])

{{-- Marketing call to action shown instead of any upload while the fee is unpaid. --}}
@php($fee = number_format($competition->entry_fee, 0, ',', ' ').' '.$competition->currency)

<div {{ $attributes->class('relative overflow-hidden rounded-3xl bg-slate-950 p-5 text-white ring-1 ring-white/10 sm:p-7') }}>
    <div class="absolute -top-16 -right-16 size-48 rounded-full border-[28px] border-brand-600/30"></div>
    <div class="absolute -bottom-10 -left-10 size-32 rounded-full bg-brand-600/20"></div>

    <div class="relative">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-2.5 py-1 text-[11px] font-bold tracking-wide text-slate-950 uppercase">
            <x-ui.icon name="bolt" variant="m" class="size-3.5" /> Dernière étape
        </span>
        <h3 class="mt-4 font-display text-2xl leading-tight font-extrabold sm:text-3xl">Plus qu'un pas pour monter sur scène</h3>
        <p class="mt-2 text-sm text-white/70 sm:text-base">Confirme ton inscription à <strong class="text-white">« {{ $competition->name }} »</strong> pour débloquer l'envoi de ta prestation.</p>

        <ul class="mt-5 grid gap-2.5 text-sm sm:grid-cols-3">
            @foreach ([['film', 'Envoie ta prestation'], ['heart', 'Récolte les likes du public'], ['trophy', 'Décroche ta place']] as [$icon, $text])
                <li class="flex items-center gap-2.5 rounded-xl bg-white/5 px-3 py-2.5 ring-1 ring-white/10">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-600"><x-ui.icon :name="$icon" class="size-4" /></span>{{ $text }}
                </li>
            @endforeach
        </ul>

        @if ($deadline && $deadline->isFuture())
            <p class="mt-5 flex flex-wrap items-center gap-2 text-sm text-white/70" x-data="countdown('{{ $deadline->toIso8601String() }}')">
                <x-ui.icon name="clock" class="size-4 text-amber-400" /> Il te reste
                <strong class="font-display tabular-nums text-amber-300" x-text="label">{{ $deadline->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</strong>
                pour participer.
            </p>
        @endif

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-ui.button size="lg" :href="route('artist.competitions.payment', $competition)" icon="lock-closed" class="!bg-white !py-3.5 !text-base !text-slate-950 hover:!bg-slate-100 sm:!px-6">
                Je confirme — {{ $fee }}
            </x-ui.button>
            <p class="flex items-center gap-1.5 text-xs text-white/50"><x-ui.icon name="shield-check" variant="m" class="size-4" /> Orange Money · MTN MoMo · Moov · Wave · carte</p>
        </div>
    </div>
</div>

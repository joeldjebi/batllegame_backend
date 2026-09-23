@props(['name' => 'media', 'accept' => '', 'maxMb' => 100, 'hint' => null, 'label' => 'Envoyer ma prestation', 'submit' => 'Envoyer'])

{{-- Large, touch-friendly file picker (drag & drop on desktop). Place inside a multipart form. --}}
<div x-data="dropzone({{ (int) $maxMb }})" {{ $attributes->class('space-y-3') }}>
    <label x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files)"
        :class="dragging ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : (file ? 'border-emerald-400 bg-emerald-50/60 dark:bg-emerald-500/5' : 'border-slate-300 bg-white hover:border-brand-400 hover:bg-brand-50/40 dark:border-white/15 dark:bg-white/[0.02]')"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-7 text-center transition active:scale-[0.99]">
        <input x-ref="input" type="file" name="{{ $name }}" accept="{{ $accept }}" required class="sr-only" x-on:change="pick($event.target.files)">
        {{-- File date on the device: shown to the organizer as an indication. --}}
        <input type="hidden" name="client_modified_at" :value="file ? file.lastModified : ''">
        <template x-if="! file">
            <span class="flex flex-col items-center gap-2">
                <span class="grid size-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-lift"><x-ui.icon name="arrow-up-tray" class="size-7" /></span>
                <span class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ $label }}</span>
                <span class="text-sm text-slate-500"><span class="font-semibold text-brand-600 dark:text-brand-300">Touchez pour choisir</span><span class="hidden sm:inline"> ou glissez votre fichier ici</span></span>
                @if ($hint)<span class="text-xs text-slate-400">{{ $hint }}</span>@endif
            </span>
        </template>
        <template x-if="file">
            <span class="flex max-w-full items-center gap-3">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-emerald-500 text-white"><x-ui.icon name="check" class="size-6" /></span>
                <span class="min-w-0 text-left">
                    <span class="block truncate font-semibold text-slate-900 dark:text-white" x-text="file.name"></span>
                    <span class="text-sm text-slate-500"><span x-text="size"></span> · touchez pour changer</span>
                </span>
            </span>
        </template>
    </label>
    <p x-show="error" x-cloak x-text="error" class="text-sm font-medium text-rose-600"></p>
    <x-ui.button type="submit" size="lg" class="w-full" icon="paper-airplane" x-bind:disabled="! file">{{ $submit }}</x-ui.button>
</div>

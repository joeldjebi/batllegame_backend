@props(['performance'])

@if ($performance->media_type === \App\Enums\MediaType::Audio)
    <audio controls preload="none" src="{{ $performance->mediaUrl() }}" {{ $attributes->class('w-full') }}></audio>
@else
    <video controls preload="metadata" src="{{ $performance->mediaUrl() }}" {{ $attributes->class('aspect-video w-full rounded-lg bg-slate-900 object-contain') }}></video>
@endif

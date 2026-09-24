@props(['title', 'description', 'url', 'image' => null, 'video' => null])

{{-- Link previews (WhatsApp, Facebook, X, Telegram) for shared pages. --}}
@push('head')
    <meta name="description" content="{{ $description }}">
    <meta property="og:site_name" content="Battle Game">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="{{ $video ? 'video.other' : 'website' }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $image ?? asset('images/og-default.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @if ($video)
        <meta property="og:video" content="{{ $video }}">
        <meta property="og:video:type" content="video/mp4">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image ?? asset('images/og-default.png') }}">
@endpush

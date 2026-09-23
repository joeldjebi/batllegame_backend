@props(['channels' => []])

{{-- Socket.IO subscription of the page (resources/js/realtime.js). Public channels are joined
     as is; private ones are signed only if the signed-in account may read them. Regions marked
     data-live="key" re-render from the server when an update arrives. --}}
@if (config('realtime.enabled'))
    @php
        $channels = array_values(array_unique(array_filter($channels)));
        $public = array_values(array_filter($channels, fn ($c) => \App\Realtime\Channel::isPublic($c)));
        $token = \App\Realtime\RealtimeToken::issue(array_values(array_diff($channels, $public)), \App\Realtime\RealtimeToken::currentUsers());
    @endphp
    <script type="application/json" data-realtime>@json(['url' => config('realtime.url'), 'channels' => $public, 'token' => $token])</script>
@endif

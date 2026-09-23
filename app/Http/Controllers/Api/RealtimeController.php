<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Realtime\Channel;
use App\Realtime\RealtimeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Socket.IO connection details for the mobile app: server URL, public channels to join
 * and a signed token for the private channels the user may read (user.{id} is always granted).
 */
class RealtimeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channels' => ['nullable', 'array', 'max:50'],
            'channels.*' => ['string', 'max:64'],
        ]);

        $user = $request->user();
        $requested = array_values(array_unique([...($validated['channels'] ?? []), Channel::user($user->id)]));
        $public = array_values(array_filter($requested, fn (string $c) => Channel::isPublic($c)));

        return response()->json([
            'enabled' => (bool) config('realtime.enabled'),
            'url' => config('realtime.url'),
            'channels' => $public,
            'token' => RealtimeToken::issue(array_values(array_diff($requested, $public)), ['sanctum' => $user]),
            'expires_in' => config('realtime.token_ttl') * 60,
        ]);
    }
}

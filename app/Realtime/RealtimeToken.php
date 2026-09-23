<?php

namespace App\Realtime;

use App\Enums\JudgeStatus;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Signed list of private channels a viewer may join ("<payload>.<hmac>", verified by
 * realtime/server.js with the shared secret). Every channel is checked against the
 * signed-in accounts of the current request before being signed.
 */
final class RealtimeToken
{
    /**
     * @param  list<string>  $channels  requested channels (public ones are ignored)
     * @param  array<string, ?User>  $users  signed-in accounts per guard (web, admin, jury, member, sanctum)
     */
    public static function issue(array $channels, array $users): ?string
    {
        $allowed = array_values(array_unique(array_filter($channels, fn (string $channel) => self::allows($channel, $users))));

        if ($allowed === [] || blank(config('realtime.secret'))) {
            return null;
        }

        $payload = rtrim(strtr(base64_encode(json_encode([
            'c' => $allowed,
            'exp' => now()->addMinutes(config('realtime.token_ttl'))->getTimestamp(),
        ])), '+/', '-_'), '=');

        return $payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, config('realtime.secret'), true)), '+/', '-_'), '=');
    }

    /**
     * Signed-in accounts of the current request, per guard.
     *
     * @return array<string, ?User>
     */
    public static function currentUsers(): array
    {
        return collect(['web', 'admin', 'jury', 'member', 'sanctum'])
            ->mapWithKeys(fn (string $guard) => [$guard => rescue(fn () => auth($guard)->user(), null, false)])
            ->all();
    }

    /**
     * @param  array<string, ?User>  $users
     */
    private static function allows(string $channel, array $users): bool
    {
        if (! preg_match('/^(bo\.competition|bo\.organizer|jury\.competition|user)\.(\d+)$|^admin$/', $channel, $m)) {
            return false;
        }

        $id = (int) ($m[2] ?? 0);
        $web = $users['web'] ?? null;

        return match ($m[1] ?? 'admin') {
            'bo.competition' => $web !== null && ($competition = Competition::find($id)) !== null && Gate::forUser($web)->allows('view', $competition),
            'bo.organizer' => $web !== null && ($organizer = Organizer::find($id)) !== null && Gate::forUser($web)->allows('viewAny', [Competition::class, $organizer]),
            'jury.competition' => collect([$users['jury'] ?? null, $users['sanctum'] ?? null])->filter()
                ->contains(fn (User $user) => Judge::query()->where('competition_id', $id)->where('user_id', $user->id)->where('status', JudgeStatus::Accepted)->exists()),
            'user' => collect($users)->filter()->contains(fn (User $user) => $user->id === $id),
            'admin' => ($users['admin'] ?? null)?->isPlatformAdmin() === true,
        };
    }
}

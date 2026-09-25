<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe retries for the mobile app's offline queue: a write sent with an
 * « Idempotency-Key » header is executed once per user; replaying it (same key)
 * returns the stored response instead of voting, liking or scoring twice.
 * Server errors are not stored, so they can be retried.
 */
class IdempotentRequests
{
    public const string HEADER = 'Idempotency-Key';

    private const int TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if ($key === null || $request->isMethodSafe()) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key)) {
            return response()->json(['message' => 'Clé d\'idempotence invalide (8 à 100 caractères : lettres, chiffres, - et _).'], 400);
        }

        // Per signed-in user (tokens are resolved here, before the route's own auth middleware).
        $owner = $request->user('sanctum')?->getAuthIdentifier() ?? 'guest:'.$request->ip();
        $cacheKey = "idempotency:{$owner}:{$key}";
        $fingerprint = hash('xxh128', $request->method().' '.$request->path().' '.json_encode($request->except(array_keys($request->allFiles()))));

        try {
            return Cache::lock("{$cacheKey}:lock", 120)->block(10, function () use ($request, $next, $cacheKey, $fingerprint): Response {
                if ($stored = Cache::get($cacheKey)) {
                    if ($stored['fingerprint'] !== $fingerprint) {
                        return response()->json(['message' => 'Cette clé d\'idempotence a déjà servi pour une autre requête.'], 422);
                    }

                    return response($stored['content'], $stored['status'], [...$stored['headers'], 'Idempotent-Replayed' => 'true']);
                }

                $response = $next($request);

                if ($response->getStatusCode() < 500) {
                    Cache::put($cacheKey, [
                        'fingerprint' => $fingerprint,
                        'status' => $response->getStatusCode(),
                        'content' => $response->getContent(),
                        'headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
                    ], now()->addHours(self::TTL_HOURS));
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Requête déjà en cours de traitement : réessayez dans un instant.'], 409);
        }
    }
}

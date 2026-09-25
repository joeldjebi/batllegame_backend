<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP revalidation for the mobile app's cache: every successful GET of the API carries
 * a weak ETag (hash of the body); the app sends it back in If-None-Match and gets an
 * empty 304 when nothing changed. Responses stay private (they differ per token) and
 * must be revalidated before reuse.
 */
class ConditionalJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200 || $response->headers->has('ETag')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        $response->setEtag(hash('xxh128', $content), weak: true);
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->setVary(['Authorization', 'Accept-Language'], replace: false);

        // Compares If-None-Match with the ETag and turns the response into an empty 304.
        $response->isNotModified($request);

        return $response;
    }
}

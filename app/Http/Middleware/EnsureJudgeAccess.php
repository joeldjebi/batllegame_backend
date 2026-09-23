<?php

namespace App\Http\Middleware;

use App\Enums\JudgeStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jury portal: a temporary password must be changed first, and the account
 * must be assigned to at least one competition.
 */
class EnsureJudgeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->must_change_password) {
            return redirect()->route('jury.password.edit');
        }

        if (! $user->judgeAssignments()->where('status', JudgeStatus::Accepted)->exists()) {
            abort(403, 'Aucune compétition ne vous a été confiée en tant que juré.');
        }

        return $next($request);
    }
}

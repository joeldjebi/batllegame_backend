<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A business rule of the competition flow prevents the requested action
 * (starting a phase, closing a match...). Rendered as a 422 / form error.
 */
class CompetitionFlowException extends DomainException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['flow' => $this->getMessage()]);
    }

    public static function phaseNotPending(): self
    {
        return new self('Cette phase a déjà démarré.');
    }

    public static function previousPhaseNotFinished(): self
    {
        return new self("La phase précédente n'est pas terminée.");
    }

    public static function competitionNotRunning(): self
    {
        return new self("La compétition n'est ni en inscriptions ni en cours.");
    }

    public static function notEnoughEntrants(int $required, int $actual): self
    {
        return new self("Il faut au moins {$required} participants validés (actuellement {$actual}).");
    }

    public static function unsupportedPreviousPhase(): self
    {
        return new self('Une phase ne peut suivre qu\'une phase de poules.');
    }

    public static function matchNotPlayable(): self
    {
        return new self("Ce match n'a pas encore ses deux participants ou est déjà clôturé.");
    }

    public static function missingJuryScores(): self
    {
        return new self("Le jury n'a pas encore noté les deux participants de ce match.");
    }

    public static function unresolvedTie(): self
    {
        return new self('Égalité parfaite après tous les critères de départage : désignez le vainqueur manuellement.');
    }

    public static function invalidForcedWinner(): self
    {
        return new self('Le vainqueur désigné ne participe pas à ce match.');
    }

    public static function hybridPhaseMode(): self
    {
        return new self('La compétition est mixte : choisissez « En ligne » ou « Présentiel » pour cette phase.');
    }

    public static function stageClosed(): self
    {
        return new self('Cette étape est terminée.');
    }

    public static function stageNotPending(): self
    {
        return new self("Cette étape n'est plus en attente.");
    }

    public static function stageNotOnline(): self
    {
        return new self("Les soumissions n'existent que pour les phases en ligne.");
    }

    public static function deadlineRequired(): self
    {
        return new self('Définissez une date limite de soumission dans le futur.');
    }

    public static function deadlineNotReached(): self
    {
        return new self("La date limite de soumission n'est pas encore passée.");
    }

    public static function submissionsToReview(int $count): self
    {
        return new self("{$count} soumission(s) doivent encore être validées ou rejetées.");
    }

    public static function stageParticipantsUnknown(): self
    {
        return new self("Les participants de cette étape ne sont pas encore tous connus : terminez l'étape précédente.");
    }

    public static function submissionsClosed(): self
    {
        return new self('Les soumissions ne sont pas ouvertes pour cette étape.');
    }
}

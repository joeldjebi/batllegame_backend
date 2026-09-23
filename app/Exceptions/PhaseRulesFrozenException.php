<?php

namespace App\Exceptions;

use App\Models\Phase;
use DomainException;

/**
 * Thrown when trying to change the rules of a phase that has already started.
 */
class PhaseRulesFrozenException extends DomainException
{
    public static function for(Phase $phase): self
    {
        return new self("The rules of phase #{$phase->getKey()} are frozen because the phase has started.");
    }
}

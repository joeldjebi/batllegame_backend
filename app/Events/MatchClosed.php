<?php

namespace App\Events;

use App\Models\BattleMatch;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A playable match has been closed with its scores and winner (null = draw).
 * Walkovers and void matches created by the bracket itself do not fire it.
 */
class MatchClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public BattleMatch $match) {}
}

<?php

namespace App\Jobs;

use App\Models\Preselection;
use App\Services\PreselectionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recompute the pre-selection ranking after scores, off the request (thousands of
 * entries); several scores in a row collapse into one run.
 */
class RankPreselection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 30;

    public function __construct(public Preselection $preselection) {}

    public function uniqueId(): string
    {
        return (string) $this->preselection->id;
    }

    public function handle(PreselectionService $preselections): void
    {
        $preselections->rank($this->preselection);
    }
}

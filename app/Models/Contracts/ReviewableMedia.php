<?php

namespace App\Models\Contracts;

use Illuminate\Support\Carbon;

/**
 * An uploaded media checked by ProcessSubmission (duration) and possibly
 * reviewed by the organizer: stage submissions and pre-selection entries.
 */
interface ReviewableMedia
{
    /**
     * Maximum allowed duration in seconds.
     */
    public function maxMediaDuration(): int;

    /**
     * Whether the organizer must approve the media before it is published.
     */
    public function requiresReview(): bool;

    public function mediaUrl(): ?string;

    /**
     * Period in which the media is expected to be recorded: [start, end] (null = open).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function submissionWindow(): array;
}

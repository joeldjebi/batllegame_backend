<?php

namespace App\Models\Contracts;

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
}

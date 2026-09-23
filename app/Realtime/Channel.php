<?php

namespace App\Realtime;

/**
 * Channel names shared with realtime/server.js (public: competition.*, live; the rest is private).
 */
final class Channel
{
    public const string LIVE = 'live';

    public const string ADMIN = 'admin';

    /** Public page of a competition (fans, landing). */
    public static function competition(int $competitionId): string
    {
        return "competition.{$competitionId}";
    }

    /** Organizer back-office of a competition. */
    public static function backOffice(int $competitionId): string
    {
        return "bo.competition.{$competitionId}";
    }

    /** Organizer lists and dashboard. */
    public static function organizer(int $organizerId): string
    {
        return "bo.organizer.{$organizerId}";
    }

    /** Judges of a competition. */
    public static function jury(int $competitionId): string
    {
        return "jury.competition.{$competitionId}";
    }

    /** One user (artist, fan or judge). */
    public static function user(int $userId): string
    {
        return "user.{$userId}";
    }

    public static function isPublic(string $channel): bool
    {
        return $channel === self::LIVE || preg_match('/^competition\.\d+$/', $channel) === 1;
    }
}

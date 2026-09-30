<?php

namespace App\Enums;

use App\Notifications\PenaltyIssued;
use App\Notifications\RaceCompleted;
use App\Notifications\RaceOpened;

/**
 * The in-app notification categories a driver can mute from Settings.
 */
enum NotificationType: string
{
    case RaceOpened = 'race_opened';
    case RaceCompleted = 'race_completed';
    case PenaltyIssued = 'penalty_issued';

    public function preferenceKey(): string
    {
        return 'notifications.'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::RaceOpened => 'Race opened',
            self::RaceCompleted => 'Race completed',
            self::PenaltyIssued => 'Penalties',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RaceOpened => 'When an organizer opens a new race lobby in one of your groups.',
            self::RaceCompleted => 'Final classifications for races you have entered.',
            self::PenaltyIssued => 'When a steward issues you a time or grid penalty.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RaceOpened => 'calendar',
            self::RaceCompleted => 'flag',
            self::PenaltyIssued => 'alert-triangle',
        };
    }

    public static function fromNotificationClass(string $class): ?self
    {
        return match ($class) {
            RaceOpened::class => self::RaceOpened,
            RaceCompleted::class => self::RaceCompleted,
            PenaltyIssued::class => self::PenaltyIssued,
            default => null,
        };
    }
}

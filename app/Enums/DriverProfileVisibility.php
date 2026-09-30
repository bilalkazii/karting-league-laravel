<?php

namespace App\Enums;

/**
 * Who may view a driver's profile page and directory entry.
 */
enum DriverProfileVisibility: string
{
    case Public = 'public';
    case Members = 'members';

    public static function preferenceKey(): string
    {
        return 'privacy.driver_profile_visibility';
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Everyone signed in',
            self::Members => 'Group members only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Any signed-in driver can view your profile and stats.',
            self::Members => 'Only drivers who share a group with you can view your profile.',
        };
    }
}

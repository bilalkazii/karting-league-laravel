<?php

namespace App\Listeners;

use App\Models\Driver;
use App\Models\Profile;
use Illuminate\Auth\Events\Registered;

/**
 * Auto-provisions the racing identity chain on registration, mirroring the
 * Supabase on_auth_user_created behaviour:
 *   users 1:1 profiles 1:1 drivers
 */
class CreateDriverIdentity
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            ['full_name' => $user->name]
        );

        Driver::firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'nickname' => $this->nicknameFrom($user->name),
                'racing_number' => null,
                'avatar_color' => '#27272a',
                'avatar_text_color' => '#ffffff',
                'rating' => 1200,
            ]
        );
    }

    private function nicknameFrom(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName));

        return strtoupper(substr($parts[0], 0, 2));
    }
}
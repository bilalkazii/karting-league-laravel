<?php

namespace Database\Seeders;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Enums\GroupPrivacy;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Mirrors supabase/migrations/202609160003_seed_demo_data.sql — the
 * identity + organization skeleton (drivers, groups, memberships, teams).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $users = [
            ['id' => 1, 'email' => 'demo@karting.app', 'password' => 'karting-demo-2026', 'full_name' => 'Bilal Darji',  'created_at' => '2024-06-15'],
            ['id' => 2, 'email' => 'drv2@karting.app',  'password' => 'not-used',        'full_name' => 'Ahmad Rehman', 'created_at' => '2024-06-15'],
            ['id' => 3, 'email' => 'drv3@karting.app',  'password' => 'not-used',        'full_name' => 'Umar Khan',    'created_at' => '2024-07-01'],
            ['id' => 4, 'email' => 'drv4@karting.app',  'password' => 'not-used',        'full_name' => 'Arjun Mehta',  'created_at' => '2025-03-10'],
            ['id' => 5, 'email' => 'drv5@karting.app',  'password' => 'not-used',        'full_name' => 'Zain Farooqi', 'created_at' => '2024-06-15'],
            ['id' => 6, 'email' => 'drv6@karting.app',  'password' => 'not-used',        'full_name' => 'Haider Ali',   'created_at' => '2024-09-20'],
            ['id' => 7, 'email' => 'drv7@karting.app',  'password' => 'not-used',        'full_name' => 'Daniyal Shah', 'created_at' => '2024-08-01'],
            ['id' => 8, 'email' => 'drv8@karting.app',  'password' => 'not-used',        'full_name' => 'Omar Tanveer', 'created_at' => '2025-02-14'],
        ];

        $drivers = [
            1 => ['nickname' => 'BD', 'racing_number' => 7,  'avatar_color' => '#d5a182', 'avatar_text_color' => '#29150c', 'rating' => 1842],
            2 => ['nickname' => 'AR', 'racing_number' => 14, 'avatar_color' => '#7994b3', 'avatar_text_color' => '#1a2634', 'rating' => 1756],
            3 => ['nickname' => 'UK', 'racing_number' => 3,  'avatar_color' => '#8c6a52', 'avatar_text_color' => '#ffffff', 'rating' => 1634],
            4 => ['nickname' => 'AJ', 'racing_number' => 22, 'avatar_color' => '#645c86', 'avatar_text_color' => '#ffffff', 'rating' => 1498],
            5 => ['nickname' => 'ZF', 'racing_number' => 9,  'avatar_color' => '#5b8a72', 'avatar_text_color' => '#ffffff', 'rating' => 1801],
            6 => ['nickname' => 'HA', 'racing_number' => 11, 'avatar_color' => '#b88a68', 'avatar_text_color' => '#1a1006', 'rating' => 1312],
            7 => ['nickname' => 'DS', 'racing_number' => 5,  'avatar_color' => '#435e6e', 'avatar_text_color' => '#ffffff', 'rating' => 1690],
            8 => ['nickname' => 'OT', 'racing_number' => 19, 'avatar_color' => '#a67c52', 'avatar_text_color' => '#1a1006', 'rating' => 1555],
        ];

        $groups = [
            'grp_1' => [
                'name' => 'Karting Crew',
                'description' => 'The original weekend warriors. We race hard, laugh louder, and argue about penalties over chai.',
                'logo_initials' => 'KC', 'logo_color' => '#b3232d', 'logo_text_color' => '#ffffff',
                'cover_color' => '#1a0a0c', 'created_by' => 1, 'created_at' => '2024-06-15',
            ],
            'grp_2' => [
                'name' => 'Weekend Racers',
                'description' => 'Sunday morning karting for those who sleep through Saturday alarms.',
                'logo_initials' => 'WR', 'logo_color' => '#32556b', 'logo_text_color' => '#ffffff',
                'cover_color' => '#0b161f', 'created_by' => 5, 'created_at' => '2025-01-10',
            ],
        ];

        $memberships = [
            // grp_1 Karting Crew
            ['group' => 'grp_1', 'driver' => 1, 'role' => GroupRole::Admin,     'availability' => DriverAvailability::Available,    'joined_at' => '2024-06-15'],
            ['group' => 'grp_1', 'driver' => 2, 'role' => GroupRole::Organizer, 'availability' => DriverAvailability::Available,    'joined_at' => '2024-06-15'],
            ['group' => 'grp_1', 'driver' => 3, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Maybe,        'joined_at' => '2024-07-01'],
            ['group' => 'grp_1', 'driver' => 4, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Available,    'joined_at' => '2025-03-10'],
            ['group' => 'grp_1', 'driver' => 5, 'role' => GroupRole::Admin,     'availability' => DriverAvailability::Available,    'joined_at' => '2024-06-15'],
            ['group' => 'grp_1', 'driver' => 6, 'role' => GroupRole::Member,    'availability' => DriverAvailability::NotAvailable, 'joined_at' => '2024-09-20'],
            ['group' => 'grp_1', 'driver' => 7, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Available,    'joined_at' => '2024-08-01'],
            ['group' => 'grp_1', 'driver' => 8, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Maybe,        'joined_at' => '2025-02-14'],
            // grp_2 Weekend Racers
            ['group' => 'grp_2', 'driver' => 5, 'role' => GroupRole::Admin,     'availability' => DriverAvailability::Available,    'joined_at' => '2025-01-10'],
            ['group' => 'grp_2', 'driver' => 7, 'role' => GroupRole::Organizer, 'availability' => DriverAvailability::Available,    'joined_at' => '2025-01-12'],
            ['group' => 'grp_2', 'driver' => 3, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Available,    'joined_at' => '2025-01-15'],
            ['group' => 'grp_2', 'driver' => 6, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Maybe,        'joined_at' => '2025-02-01'],
            ['group' => 'grp_2', 'driver' => 8, 'role' => GroupRole::Member,    'availability' => DriverAvailability::Available,    'joined_at' => '2025-03-01'],
            ['group' => 'grp_2', 'driver' => 1, 'role' => GroupRole::Member,    'availability' => DriverAvailability::NotAvailable, 'joined_at' => '2025-04-20'],
        ];

        $teams = [
            ['key' => 'tm_1', 'group' => 'grp_1', 'name' => 'Apex Racing',    'logo_initials' => 'AR', 'logo_color' => '#c43c2d', 'logo_text_color' => '#ffffff', 'created_at' => '2024-06-15'],
            ['key' => 'tm_2', 'group' => 'grp_1', 'name' => 'Redline Karting', 'logo_initials' => 'RK', 'logo_color' => '#2d6bc4', 'logo_text_color' => '#ffffff', 'created_at' => '2024-07-01'],
            ['key' => 'tm_3', 'group' => 'grp_1', 'name' => 'Velocity Squad',  'logo_initials' => 'VS', 'logo_color' => '#2d9c6f', 'logo_text_color' => '#ffffff', 'created_at' => '2024-08-01'],
        ];

        $teamMembers = [
            ['team' => 'tm_1', 'driver' => 1, 'joined_at' => '2024-06-15'],
            ['team' => 'tm_1', 'driver' => 2, 'joined_at' => '2024-06-15'],
            ['team' => 'tm_1', 'driver' => 4, 'joined_at' => '2025-03-10'],
            ['team' => 'tm_2', 'driver' => 3, 'joined_at' => '2024-07-01'],
            ['team' => 'tm_2', 'driver' => 6, 'joined_at' => '2024-09-20'],
            ['team' => 'tm_3', 'driver' => 5, 'joined_at' => '2024-06-15'],
            ['team' => 'tm_3', 'driver' => 7, 'joined_at' => '2024-08-01'],
        ];

        $driverModel = [];

        foreach ($users as $user) {
            $u = User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['full_name'],
                    'password' => Hash::make($user['password']),
                    'email_verified_at' => $now,
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['created_at'],
                ]
            );

            $p = Profile::firstOrCreate(
                ['user_id' => $u->id],
                [
                    'full_name' => $user['full_name'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['created_at'],
                ]
            );

            $d = $drivers[$user['id']];
            $driverModel[$user['id']] = Driver::firstOrCreate(
                ['profile_id' => $p->id],
                [
                    'nickname' => $d['nickname'],
                    'racing_number' => $d['racing_number'],
                    'avatar_color' => $d['avatar_color'],
                    'avatar_text_color' => $d['avatar_text_color'],
                    'rating' => $d['rating'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['created_at'],
                ]
            );
        }

        $groupModel = [];
        foreach ($groups as $key => $g) {
            $groupModel[$key] = Group::firstOrCreate(
                ['name' => $g['name']],
                [
                    'description' => $g['description'],
                    'logo_initials' => $g['logo_initials'],
                    'logo_color' => $g['logo_color'],
                    'logo_text_color' => $g['logo_text_color'],
                    'cover_color' => $g['cover_color'],
                    'privacy' => GroupPrivacy::Private,
                    'created_by' => $driverModel[$g['created_by']]->id,
                    'created_at' => $g['created_at'],
                    'updated_at' => $g['created_at'],
                ]
            );
        }

        foreach ($memberships as $m) {
            $groupModel[$m['group']]->members()->syncWithoutDetaching([
                $driverModel[$m['driver']]->id => [
                    'role' => $m['role']->value,
                    'availability' => $m['availability']->value,
                    'joined_at' => $m['joined_at'],
                ],
            ]);
        }

        $teamModel = [];
        foreach ($teams as $t) {
            $teamModel[$t['key']] = Team::firstOrCreate(
                ['name' => $t['name']],
                [
                    'group_id' => $groupModel[$t['group']]->id,
                    'logo_initials' => $t['logo_initials'],
                    'logo_color' => $t['logo_color'],
                    'logo_text_color' => $t['logo_text_color'],
                    'created_at' => $t['created_at'],
                    'updated_at' => $t['created_at'],
                ]
            );
        }

        foreach ($teamMembers as $tm) {
            $teamModel[$tm['team']]->members()->syncWithoutDetaching([
                $driverModel[$tm['driver']]->id => [
                    'joined_at' => $tm['joined_at'],
                ],
            ]);
        }
    }
}
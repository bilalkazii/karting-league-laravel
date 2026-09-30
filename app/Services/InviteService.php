<?php

namespace App\Services;

use App\Enums\DriverAvailability;
use App\Enums\GroupRole;
use App\Enums\InviteStatus;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the shareable invitation lifecycle: token issuance (hash-only storage),
 * revocation, rotation, and transactional single-use acceptance. Possession of a
 * valid token is the invitation credential; authentication is still required.
 */
class InviteService
{
    public const EXPIRY_DAYS = 14;

    /**
     * @return array{invite: Invite, token: string, url: string}
     */
    public function create(Group $group, ?Driver $inviter, ?string $email = null): array
    {
        $token = $this->generateToken();

        $invite = Invite::create([
            'group_id' => $group->getKey(),
            'invited_by' => $inviter?->getKey(),
            'email' => $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null,
            'token_hash' => $this->hashToken($token),
            'role' => GroupRole::Member->value,
            'status' => InviteStatus::Pending->value,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        return [
            'invite' => $invite,
            'token' => $token,
            'url' => $this->urlFor($token),
        ];
    }

    /**
     * Revoke a pending invite and issue a fresh one with the same scope/email so
     * the manager can re-copy a shareable link.
     *
     * @return array{invite: Invite, token: string, url: string}
     */
    public function rotate(Invite $invite): array
    {
        $invite->forceFill(['status' => InviteStatus::Revoked->value])->save();

        return $this->create($invite->group, $invite->inviter, $invite->email);
    }

    public function revoke(Invite $invite): void
    {
        if ($invite->isPending()) {
            $invite->forceFill(['status' => InviteStatus::Revoked->value])->save();
        }
    }

    public function findByToken(string $token): ?Invite
    {
        $hash = $this->hashToken($token);
        $invite = Invite::query()->where('token_hash', $hash)->first();

        // Defensive constant-time comparison; the lookup itself is index-based.
        if ($invite === null || ! hash_equals($invite->token_hash, $hash)) {
            return null;
        }

        return $invite;
    }

    public function stateFor(Invite $invite, ?Driver $viewer): string
    {
        if ($invite->isRevoked()) {
            return 'revoked';
        }

        if ($invite->isExpired()) {
            return 'expired';
        }

        if ($invite->isAccepted()) {
            $isMember = $viewer !== null
                && $invite->group->members()->where('drivers.id', $viewer->getKey())->exists();

            return $isMember ? 'accepted' : 'used';
        }

        return 'pending';
    }

    /**
     * Transactional, single-use acceptance. A row lock prevents two concurrent
     * accounts from both consuming the invite; the same account re-accepting is
     * idempotent (no duplicate membership).
     *
     * @return array{status: string, invite: ?Invite}
     */
    public function accept(string $token, User $user): array
    {
        $hash = $this->hashToken($token);

        return DB::transaction(function () use ($hash, $user) {
            $invite = Invite::query()->where('token_hash', $hash)->lockForUpdate()->first();

            if ($invite === null || ! hash_equals($invite->token_hash, $hash)) {
                return ['status' => 'invalid', 'invite' => null];
            }

            $group = $invite->group;
            $driver = $user->driver;

            if ($driver === null) {
                return ['status' => 'no_driver', 'invite' => $invite];
            }

            $isMember = $group->members()->where('drivers.id', $driver->getKey())->exists();

            if ($invite->isRevoked()) {
                return ['status' => 'revoked', 'invite' => $invite];
            }

            if ($invite->isAccepted()) {
                return ['status' => $isMember ? 'already_accepted' : 'used', 'invite' => $invite];
            }

            if ($invite->isExpired()) {
                return ['status' => 'expired', 'invite' => $invite];
            }

            if (! $isMember) {
                $group->members()->attach($driver->getKey(), [
                    'role' => GroupRole::Member->value,
                    'availability' => DriverAvailability::Available->value,
                    'joined_at' => now(),
                ]);
            }

            $invite->forceFill([
                'status' => InviteStatus::Accepted->value,
                'accepted_at' => now(),
            ])->save();

            return ['status' => 'accepted', 'invite' => $invite];
        });
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function urlFor(string $token): string
    {
        return route('invites.show', $token);
    }
}

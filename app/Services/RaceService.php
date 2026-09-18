<?php

namespace App\Services;

use App\Enums\QualifyingStatus;
use App\Enums\RaceDriverStatus;
use App\Enums\RaceEventType;
use App\Enums\RaceStatus;
use App\Models\Driver;
use App\Models\Group;
use App\Models\QualifyingAttempt;
use App\Models\Race;
use App\Models\RaceEntry;
use App\Models\RaceEvent;
use App\Models\RacePenalty;
use App\Models\User;
use App\Notifications\PenaltyIssued;
use App\Notifications\RaceCompleted;
use App\Notifications\RaceOpened;
use App\Support\RaceUtils;
use Illuminate\Support\Collection;

/**
 * Guards and performs every mutating race operation, recording the matching
 * race_event. Mirrors the source race-provider reducer.
 */
class RaceService
{
    public function setParticipants(Race $race, array $driverIds): void
    {
        $keep = array_map('intval', $driverIds);
        $existing = Collection::make($race->entries()->pluck('driver_id'))->all();

        $race->entries()->whereNotIn('driver_id', $keep)->delete();

        foreach (array_diff($keep, $existing) as $driverId) {
            $race->entries()->create([
                'driver_id' => $driverId,
                'kart_number' => 0,
                'status' => RaceDriverStatus::Invited->value,
                'confirmed' => false,
                'ready' => false,
                'grid_position' => null,
                'grid_penalty_seconds' => 0,
                'qualifying_time_ms' => null,
                'qualifying_status' => QualifyingStatus::NotStarted->value,
                'finish_position' => null,
                'penalty_total_seconds' => 0,
                'notes' => '',
            ]);
        }
    }

    public function updateKart(Race $race, int $driverId, int $kartNumber): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        $entry->update(['kart_number' => max(0, $kartNumber)]);
    }

    public function toggleConfirmed(Race $race, int $driverId): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        $confirmed = ! $entry->confirmed;
        $entry->update([
            'confirmed' => $confirmed,
            'ready' => $confirmed ? $entry->ready : false,
            'status' => $confirmed
                ? ($entry->ready ? RaceDriverStatus::Ready->value : RaceDriverStatus::Confirmed->value)
                : RaceDriverStatus::Invited->value,
        ]);
    }

    public function toggleReady(Race $race, int $driverId): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if (! $entry->confirmed) {
            return;
        }
        $ready = ! $entry->ready;
        $entry->update([
            'ready' => $ready,
            'status' => $ready ? RaceDriverStatus::Ready->value : RaceDriverStatus::Confirmed->value,
        ]);
        $this->event($race, $ready ? RaceEventType::Ready : RaceEventType::NotReady, $driverId);
    }

    public function setDriverStatus(Race $race, int $driverId, string $status): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if (($entry->status?->value ?? $entry->getRawOriginal('status')) === $status) {
            return;
        }

        $isFinish = $status === RaceDriverStatus::Finished->value;
        $position = $isFinish
            ? ($entry->finish_position ?? $this->nextFinishPosition($race))
            : null;

        $entry->update([
            'status' => $status,
            'finish_position' => $position,
        ]);

        $eventType = match ($status) {
            RaceDriverStatus::Finished->value => RaceEventType::RaceFinish,
            RaceDriverStatus::Dnf->value => RaceEventType::Dnf,
            RaceDriverStatus::Dns->value => RaceEventType::Dns,
            RaceDriverStatus::Retired->value => RaceEventType::Retired,
            RaceDriverStatus::Withdrawn->value => RaceEventType::Withdrawn,
            default => RaceEventType::Note,
        };
        $this->event($race, $eventType, $driverId, $position !== null ? ['position' => $position] : []);
    }

    public function recordQualifyingTime(Race $race, int $driverId, int $timeMs): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if ($timeMs <= 0 || $timeMs > 3_600_000) {
            return;
        }
        $hadOfficial = RaceUtils::isValidQualifyingTime(RaceUtils::entryToArray($entry));
        $entry->update(['qualifying_time_ms' => $timeMs, 'qualifying_status' => QualifyingStatus::Completed->value]);
        $this->recordAttempt($race, $driverId, $hadOfficial ? 2 : 1, $timeMs, $entry->qualifying_status);

        $this->event($race, $hadOfficial ? RaceEventType::QualifyingReplace : RaceEventType::QualifyingStop, $driverId, ['elapsed_ms' => $timeMs]);
    }

    public function manualCorrectQualifying(Race $race, int $driverId, int $timeMs): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if ($timeMs <= 0 || $timeMs > 3_600_000) {
            return;
        }
        $entry->update(['qualifying_time_ms' => $timeMs, 'qualifying_status' => QualifyingStatus::ManuallyCorrected->value]);
        $this->recordAttempt($race, $driverId, 1, $timeMs, $entry->qualifying_status);
        $this->event($race, RaceEventType::QualifyingCorrected, $driverId, ['elapsed_ms' => $timeMs]);
    }

    public function invalidateQualifying(Race $race, int $driverId): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if ($entry->qualifying_time_ms === null) {
            return;
        }
        $entry->update(['qualifying_status' => QualifyingStatus::Invalid->value]);
        $this->event($race, RaceEventType::QualifyingInvalid, $driverId);
    }

    public function restoreQualifying(Race $race, int $driverId): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        $current = $entry->qualifying_status?->value ?? $entry->getRawOriginal('qualifying_status');
        if ($current !== QualifyingStatus::Invalid->value) {
            return;
        }
        $entry->update(['qualifying_status' => QualifyingStatus::Completed->value]);
        $this->event($race, RaceEventType::QualifyingRestore, $driverId);
    }

    public function clearQualifying(Race $race, int $driverId): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        if ($entry->qualifying_time_ms === null) {
            return;
        }
        $entry->update([
            'qualifying_time_ms' => null,
            'qualifying_status' => QualifyingStatus::NotStarted->value,
        ]);
        $this->event($race, RaceEventType::QualifyingReset, $driverId);
    }

    public function setGridPosition(Race $race, int $driverId, ?int $position): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        $entry->update(['grid_position' => $position]);
        $this->event($race, RaceEventType::GridChange, $driverId, $position !== null ? ['position' => $position] : []);
    }

    public function setGridPenalty(Race $race, int $driverId, int $seconds): void
    {
        $entry = $this->entryOrFail($race, $driverId);
        $entry->update(['grid_penalty_seconds' => max(0, $seconds)]);
        $this->event($race, RaceEventType::GridPenalty, $driverId, ['seconds' => max(0, $seconds)]);
    }

    public function lockGrid(Race $race): bool
    {
        if (($race->status?->value ?? $race->getRawOriginal('status')) !== RaceStatus::Qualifying->value) {
            return false;
        }

        $rows = RaceUtils::computeGridRows(RaceUtils::entriesToArrays($race->entries));
        foreach ($rows as $row) {
            if ($row['grid_position'] !== null) {
                $race->entries()->where('driver_id', $row['driver_id'])
                    ->update(['grid_position' => $row['grid_position']]);
            }
        }

        $this->transition($race, RaceStatus::Grid);

        return true;
    }

    public function startRace(Race $race): bool
    {
        if (($race->status?->value ?? $race->getRawOriginal('status')) !== RaceStatus::Grid->value) {
            return false;
        }
        $race->entries()->where('confirmed', true)->where('ready', true)
            ->update(['status' => RaceDriverStatus::Racing->value]);
        $this->transition($race, RaceStatus::Racing, RaceEventType::RaceStart, ['format' => $race->format?->value ?? 'sprint']);

        return true;
    }

    public function completeRace(Race $race): bool
    {
        $status = $race->status?->value ?? $race->getRawOriginal('status');
        if (! in_array($status, [RaceStatus::Racing->value, RaceStatus::Grid->value], true)) {
            return false;
        }
        $classified = $race->entries()->whereIn('status', [
            RaceDriverStatus::Finished->value,
            RaceDriverStatus::Dnf->value,
            RaceDriverStatus::Dns->value,
            RaceDriverStatus::Retired->value,
            RaceDriverStatus::Withdrawn->value,
        ])->count();
        $this->transition($race, RaceStatus::Completed, RaceEventType::Complete, ['results_count' => $classified]);
        $this->notifyEntryUsers($race, RaceCompleted::class);

        return true;
    }

    public function cancelRace(Race $race): bool
    {
        $current = $race->status?->value ?? $race->getRawOriginal('status');
        if ($current === RaceStatus::Completed->value || $current === RaceStatus::Cancelled->value) {
            return $current === RaceStatus::Completed->value;
        }
        $this->transition($race, RaceStatus::Cancelled);

        return true;
    }

    public function openLobby(Race $race): bool
    {
        if (($race->status?->value ?? $race->getRawOriginal('status')) !== RaceStatus::Draft->value) {
            return false;
        }
        $this->transition($race, RaceStatus::Lobby);
        $this->notifyGroupMembers($race->group, RaceOpened::class, $race);

        return true;
    }

    public function startQualifying(Race $race): bool
    {
        if (($race->status?->value ?? $race->getRawOriginal('status')) !== RaceStatus::Lobby->value) {
            return false;
        }
        $race->entries()->whereNull('qualifying_time_ms')
            ->update(['qualifying_status' => QualifyingStatus::NotStarted->value]);
        $this->transition($race, RaceStatus::Qualifying, RaceEventType::QualifyingStart, ['lap_count' => $race->qualifying_lap_count]);

        return true;
    }

    public function issuePenalty(Race $race, int $driverId, int $seconds, string $reason, int $issuerId): bool
    {
        if ($seconds <= 0 || trim($reason) === '' || $this->entryOrNull($race, $driverId) === null) {
            return false;
        }
        $this->entryOrFail($race, $driverId);
        RacePenalty::create([
            'race_id' => $race->id,
            'driver_id' => $driverId,
            'seconds' => $seconds,
            'reason' => trim($reason),
            'issued_by' => $issuerId,
            'status' => 'issued',
        ]);
        $this->recalculatePenaltyTotals($race, $driverId);
        $this->event($race, RaceEventType::Penalty, $driverId, ['seconds' => $seconds, 'reason' => trim($reason)]);
        $users = $this->driverUsers($race->entries()->firstWhere('driver_id', $driverId)->driver);
        foreach ($users as $user) {
            $user->notify(new PenaltyIssued($race, $seconds, trim($reason)));
        }

        return true;
    }

    public function cancelPenalty(Race $race, RacePenalty $penalty): bool
    {
        if ($penalty->race_id !== $race->id) {
            return false;
        }
        if (($penalty->status?->value ?? $penalty->getRawOriginal('status')) === 'cancelled') {
            return false;
        }
        $penalty->update(['status' => 'cancelled']);
        $this->recalculatePenaltyTotals($race, $penalty->driver_id);
        $this->event($race, RaceEventType::PenaltyCancelled, $penalty->driver_id, ['penalty_id' => $penalty->id]);

        return true;
    }

    public function recalculatePenaltyTotals(Race $race, int $driverId): void
    {
        $total = Collection::make($race->penalties)
            ->filter(fn (RacePenalty $p) => $p->driver_id === $driverId
                && (($p->status?->value ?? $p->getRawOriginal('status')) !== 'cancelled'))
            ->sum('seconds');
        $race->entries()->where('driver_id', $driverId)->update(['penalty_total_seconds' => $total]);
    }

    /**
     * @return list<RaceEntry>
     */
    public function groupMemberEntries(Race $race, Group $group): Collection
    {
        $memberIds = Collection::make($group->members()->pluck('drivers.id'))->all();
        $entryIds = Collection::make($race->entries()->pluck('driver_id'))->all();

        $rows = [];
        foreach (array_unique(array_merge($memberIds, $entryIds)) as $driverId) {
            $entry = $race->entries()->firstWhere('driver_id', $driverId);
            $rows[] = [
                'driver_id' => $driverId,
                'in_field' => $entry !== null,
                'kart_number' => $entry?->kart_number ?? 0,
                'entry' => $entry,
            ];
        }

        return collect($rows)->sortByDesc('in_field');
    }

    private function notifyEntryUsers(Race $race, string $notificationClass): void
    {
        $this->notifyRaceUsers($race->entries->map(fn (RaceEntry $entry) => $entry->driver)->filter(), $notificationClass, $race);
    }

    private function notifyGroupMembers(?Group $group, string $notificationClass, Race $race): void
    {
        if ($group === null) {
            return;
        }
        $this->notifyRaceUsers($group->members, $notificationClass, $race);
    }

    private function notifyRaceUsers(iterable $drivers, string $notificationClass, Race $race): void
    {
        foreach ($drivers as $driver) {
            foreach ($this->driverUsers($driver) as $user) {
                $user->notify(new $notificationClass($race));
            }
        }
    }

    /** @return list<User> */
    private function driverUsers(?Driver $driver): array
    {
        $user = $driver?->profile?->user;

        return $user === null ? [] : [$user];
    }

    private function transition(Race $race, RaceStatus $to, ?RaceEventType $eventType = null, array $payload = []): void
    {
        $race->update(['status' => $to->value]);
        $this->event($race, $eventType ?? RaceEventType::StatusChange, null, $payload !== [] ? $payload : ['status' => $to->value]);
    }

    private function event(Race $race, RaceEventType $type, ?int $driverId, array $payload = []): void
    {
        RaceEvent::create([
            'race_id' => $race->id,
            'driver_id' => $driverId,
            'type' => $type->value,
            'occurred_at' => now(),
            'payload' => $payload,
        ]);
    }

    private function entryOrFail(Race $race, int $driverId): RaceEntry
    {
        $entry = $this->entryOrNull($race, $driverId);
        abort_unless($entry !== null, 404);

        return $entry;
    }

    private function entryOrNull(Race $race, int $driverId): ?RaceEntry
    {
        return $race->entries()->firstWhere('driver_id', $driverId);
    }

    private function nextFinishPosition(Race $race): int
    {
        $max = $race->entries()->max('finish_position') ?? 0;

        return $max + 1;
    }

    private function recordAttempt(Race $race, int $driverId, int $attemptNumber, int $timeMs, mixed $status): void
    {
        QualifyingAttempt::create([
            'race_id' => $race->id,
            'driver_id' => $driverId,
            'attempt_number' => $attemptNumber,
            'time_ms' => $timeMs,
            'status' => $status?->value ?? $status,
            'recorded_at' => now(),
        ]);
    }
}

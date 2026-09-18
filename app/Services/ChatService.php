<?php

namespace App\Services;

use App\Models\ChatLastRead;
use App\Models\ChatMessage;
use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use Illuminate\Support\Collection;

/**
 * Stores chat messages in exactly one thread scope (group xor race) and tracks
 * per-driver last-read watermarks so unread counts stay deterministic.
 */
class ChatService
{
    private const MAX_BODY_LENGTH = 500;

    public function storeMessage(Driver $sender, string $body, ?Group $group = null, ?Race $race = null): ChatMessage
    {
        if (($group === null) === ($race === null)) {
            abort(422, 'A message must belong to exactly one chat thread.');
        }

        $body = trim($body);
        if ($body === '') {
            abort(422, 'The message body cannot be empty.');
        }
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            abort(422, 'The message body must not exceed '.self::MAX_BODY_LENGTH.' characters.');
        }

        $message = new ChatMessage(['body' => $body, 'sender_id' => $sender->id]);
        $message->group()->associate($group);
        $message->race()->associate($race);
        $message->save();

        return $message;
    }

    public function markThreadRead(Driver $driver, ?Group $group = null, ?Race $race = null): void
    {
        if (($group === null) === ($race === null)) {
            abort(422, 'A read mark must target exactly one chat thread.');
        }

        ChatLastRead::updateOrCreate(
            [
                'driver_id' => $driver->id,
                'group_id' => $group?->id,
                'race_id' => $race?->id,
            ],
            ['last_read_at' => now()]
        );
    }

    public function unreadInGroupThread(Driver $driver, Group $group): int
    {
        return $this->unreadInThread($driver, $group, null);
    }

    public function unreadInRaceThread(Driver $driver, Race $race): int
    {
        return $this->unreadInThread($driver, null, $race);
    }

    /**
     * Total unread messages across every group room the driver belongs to.
     * Used for the navigation badge and the room index.
     */
    public function unreadForDriver(Driver $driver): int
    {
        $total = 0;
        foreach ($driver->groups as $group) {
            $total += $this->unreadInGroupThread($driver, $group);
        }

        return $total;
    }

    /**
     * @return array<string, array{group: Group, unread: int, latest: ?ChatMessage}>
     */
    public function groupRoomsFor(Driver $driver): array
    {
        return $driver->groups
            ->mapWithKeys(fn (Group $group) => [$group->id => [
                'group' => $group,
                'unread' => $this->unreadInGroupThread($driver, $group),
                'latest' => $group->messages()->latest('id')->first(),
            ]])
            ->all();
    }

    /**
     * Newest-first messages capped at 100, then reversed to chronological.
     * Keeps the thread readable without pagination; see phase doc limitation.
     *
     * @return Collection<int, ChatMessage>
     */
    public function threadMessages(Group|Race $scope): Collection
    {
        $query = $scope instanceof Group
            ? ChatMessage::query()->where('group_id', $scope->getKey())
            : ChatMessage::query()->where('race_id', $scope->getKey());

        return $query
            ->with(['sender.profile'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Render a compact author label, falling back if the sender is gone.
     */
    public static function senderLabel(?Driver $sender): string
    {
        return $sender?->profile?->full_name ?? $sender?->nickname ?? 'Former member';
    }

    private function unreadInThread(Driver $driver, ?Group $group, ?Race $race): int
    {
        if (($group === null) === ($race === null)) {
            return 0;
        }

        $lastReadAt = ChatLastRead::query()
            ->where('driver_id', $driver->id)
            ->where('group_id', $group?->id)
            ->where('race_id', $race?->id)
            ->value('last_read_at');

        $query = $group !== null
            ? ChatMessage::query()->where('group_id', $group->getKey())
            : ChatMessage::query()->where('race_id', $race->getKey());

        return $lastReadAt === null
            ? (int) $query->count()
            : (int) $query->where('created_at', '>', $lastReadAt)->count();
    }
}

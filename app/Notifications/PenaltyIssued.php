<?php

namespace App\Notifications;

use App\Models\Race;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PenaltyIssued extends Notification
{
    use Queueable;

    public function __construct(
        public Race $race,
        public int $seconds,
        public string $reason,
    ) {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Penalty issued',
            'body' => "A {$this->seconds}s penalty was applied to you on “{$this->race->name}”: {$this->reason}.",
            'race_id' => $this->race->id,
            'url' => route('races.show', $this->race),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

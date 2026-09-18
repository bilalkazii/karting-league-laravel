<?php

namespace App\Notifications;

use App\Models\Race;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RaceOpened extends Notification
{
    use Queueable;

    public function __construct(public Race $race)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Race opened',
            'body' => "The session “{$this->race->name}” at {$this->race->venue_name} is now open for entries.",
            'race_id' => $this->race->id,
            'url' => route('races.show', $this->race),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

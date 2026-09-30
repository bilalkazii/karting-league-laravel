<?php

namespace App\Mail;

use App\Models\Group;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GroupInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Group $group,
        public string $inviteUrl,
        public DateTimeInterface $expiresAt,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to join '.$this->group->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.group-invitation',
        );
    }
}

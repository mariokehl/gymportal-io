<?php

namespace App\Mail;

use App\Models\GymInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GymInvitationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public GymInvitation $invitation,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Einladung zu '.$this->invitation->gym->getDisplayName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.gym-invitation',
            with: [
                'gymName' => $this->invitation->gym->getDisplayName(),
                'roleLabel' => GymInvitation::roleLabel($this->invitation->role),
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invitation->expires_at?->format('d.m.Y'),
            ],
        );
    }
}

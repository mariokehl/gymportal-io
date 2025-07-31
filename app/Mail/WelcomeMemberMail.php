<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMemberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Gym $gym
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Willkommen - {$this->gym->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-mmember',
            with: [
                'member' => $this->member,
                'gym' => $this->gym,
            ]
        );
    }
}

<?php

namespace App\Mail;

use App\Models\LoginCode;
use App\Models\Member;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LoginCode $loginCode,
        public Member $member,
        public Gym $gym
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->gym->email, $this->gym->name),
            replyTo: [
                new Address($this->gym->email, $this->gym->name),
            ],
            subject: 'Ihr Anmeldecode für ' . $this->gym->name,
            metadata: [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'code_expires_at' => $this->loginCode->expires_at->toISOString()
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-code',
            with: [
                'code' => $this->loginCode->code,
                'member' => $this->member,
                'gym' => $this->gym,
                'theme' => $this->gym->theme ?? [],
                'expiryTime' => $this->loginCode->getFormattedExpiryTime(),
                'expiryMinutes' => $this->loginCode->expires_at->diffInMinutes(now()),
            ]
        );
    }

    public function tags(): array
    {
        return [
            'login-code',
            'gym-' . $this->gym->slug,
            'member-' . $this->member->id,
        ];
    }
}

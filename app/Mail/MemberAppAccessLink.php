<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberAppAccessLink extends Mailable
{
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Member $member,
        public string $loginUrl
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address($this->member->gym->email, $this->member->gym->name),
            ],
            subject: 'Zugang zur ' . $this->member->gym->name . ' Mitglieder-App',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.member-app-access',
            with: [
                'memberName' => $this->member->full_name,
                'gymName' => $this->member->gym->name,
                'gym' => $this->member->gym,
                'loginUrl' => $this->loginUrl,
                'expiresIn' => '24 Stunden',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

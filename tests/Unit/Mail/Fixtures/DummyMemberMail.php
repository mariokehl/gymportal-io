<?php

namespace Tests\Unit\Mail\Fixtures;

use App\Mail\Contracts\MemberMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Minimaler MemberMail-Stub für Tests.
 * Weder Content noch Envelope müssen realistisch sein — der Dispatcher nutzt
 * nur die Klasse als Typ und gibt sie an Laravels Mailer weiter, der im Test
 * via {@see \Illuminate\Support\Facades\Mail::fake()} ersetzt wird.
 */
class DummyMemberMail extends Mailable implements MemberMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dummy');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>dummy</p>');
    }

    public function attachments(): array
    {
        return [];
    }
}

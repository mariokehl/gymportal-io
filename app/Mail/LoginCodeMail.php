<?php

namespace App\Mail;

use App\Mail\Contracts\MemberMail;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class LoginCodeMail extends Mailable implements MemberMail
{
    use Queueable, SerializesModels;

    public function __construct(
        public LoginCode $loginCode,
        public Member $member,
        public Gym $gym
    ) {}

    public function envelope(): Envelope
    {
        $emailTemplateService = new EmailTemplateService();

        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'login_code',
            $this->member,
            ['[Anmeldecode]' => $this->loginCode->code]
        );

        $subject = $renderedTemplate
            ? $renderedTemplate['subject']
            : 'Ihr Anmeldecode für ' . $this->gym->name;

        return new Envelope(
            replyTo: [
                new Address($this->gym->email, $this->gym->name),
            ],
            subject: $subject,
            metadata: [
                'gym_id' => $this->gym->id,
                'member_id' => $this->member->id,
                'code_expires_at' => $this->loginCode->expires_at->toISOString()
            ]
        );
    }

    public function content(): Content
    {
        $emailTemplateService = new EmailTemplateService();

        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'login_code',
            $this->member,
            ['[Anmeldecode]' => $this->loginCode->code]
        );

        if ($renderedTemplate) {
            return new Content(
                view: 'emails.template-based',
                with: [
                    'renderedContent' => $renderedTemplate['body'],
                    'member' => $this->member,
                    'gym' => $this->gym,
                ]
            );
        } else {
            return new Content(
                view: 'emails.login-code-fallback',
                with: [
                    'code' => $this->loginCode->code,
                    'member' => $this->member,
                    'gym' => $this->gym,
                    'theme' => $this->gym->theme ?? [],
                    'expiryGrace' => LoginCode::EXPIRY_MINUTES . ' Minuten',
                ]
            );
        }
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

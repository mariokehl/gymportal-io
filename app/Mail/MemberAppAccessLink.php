<?php

namespace App\Mail;

use App\Models\Member;
use App\Services\EmailTemplateService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MemberAppAccessLink extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Member $member,
        public string $loginUrl
    ) {}

    public function envelope(): Envelope
    {
        $gym = $this->member->gym;
        $emailTemplateService = new EmailTemplateService();

        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $gym,
            'member_app_access',
            $this->member,
            ['[Mitgliederbereich-Link]' => $this->loginUrl]
        );

        $subject = $renderedTemplate
            ? $renderedTemplate['subject']
            : 'Zugang zur ' . $gym->name . ' Mitglieder-App';

        return new Envelope(
            replyTo: [
                new Address($gym->email, $gym->name),
            ],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $gym = $this->member->gym;
        $emailTemplateService = new EmailTemplateService();

        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $gym,
            'member_app_access',
            $this->member,
            ['[Mitgliederbereich-Link]' => $this->loginUrl]
        );

        if ($renderedTemplate) {
            return new Content(
                view: 'emails.template-based',
                with: [
                    'renderedContent' => $renderedTemplate['body'],
                    'member' => $this->member,
                    'gym' => $gym,
                ]
            );
        } else {
            Log::warning("No member_app_access template found for gym {$gym->id}, using fallback");

            return new Content(
                view: 'emails.member-app-access-fallback',
                with: [
                    'memberName' => $this->member->full_name,
                    'gymName' => $gym->name,
                    'gym' => $gym,
                    'loginUrl' => $this->loginUrl,
                    'expiresIn' => '24 Stunden',
                ],
            );
        }
    }

    public function attachments(): array
    {
        return [];
    }
}

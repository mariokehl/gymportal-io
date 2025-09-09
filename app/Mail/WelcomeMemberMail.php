<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WelcomeMemberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Gym $gym,
        public array $additionalData = []
    ) {}

    public function envelope(): Envelope
    {
        $emailTemplateService = new EmailTemplateService();

        // Try to get rendered template
        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'welcome',
            $this->member,
            $this->additionalData
        );

        $subject = $renderedTemplate
            ? $renderedTemplate['subject']
            : "Willkommen - {$this->gym->name}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $emailTemplateService = new EmailTemplateService();

        // Try to get rendered template
        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'welcome',
            $this->member,
            $this->additionalData
        );

        if ($renderedTemplate) {
            // Use template system
            return new Content(
                view: 'emails.template-based',
                with: [
                    'renderedContent' => $renderedTemplate['body'],
                    'member' => $this->member,
                    'gym' => $this->gym,
                ]
            );
        } else {
            // Fallback to original template
            Log::warning("No welcome template found for gym {$this->gym->id}, using fallback");

            return new Content(
                view: 'emails.welcome-member-fallback',
                with: [
                    'member' => $this->member,
                    'gym' => $this->gym,
                    'additionalData' => $this->additionalData,
                ]
            );
        }
    }

    /**
     * Set additional data for template rendering.
     */
    public function withAdditionalData(array $data): self
    {
        $this->additionalData = array_merge($this->additionalData, $data);
        return $this;
    }

    /**
     * Override specific placeholders.
     */
    public function withPlaceholders(array $placeholders): self
    {
        $this->additionalData = array_merge($this->additionalData, $placeholders);
        return $this;
    }
}

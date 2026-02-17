<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WelcomeMemberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Gym $gym,
        public array $additionalData = [],
        public ?string $contractPath = null
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
            replyTo: [
                new Address($this->gym->email, $this->gym->name),
            ],
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
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Generierter Vertrag anhängen
        if ($this->contractPath && Storage::disk('local')->exists($this->contractPath)) {
            $attachments[] = Attachment::fromPath(Storage::disk('local')->path($this->contractPath))
                ->as('Vertrag_' . $this->member->member_number . '.pdf')
                ->withMime('application/pdf');
        }

        // E-Mail-Vorlagen-Anhänge (AGBs, Widerrufsbelehrung etc.)
        try {
            $emailTemplateService = new EmailTemplateService();
            $template = $emailTemplateService->getTemplate($this->gym, 'welcome');

            if ($template) {
                $template->load('fileAttachments');
                foreach ($template->fileAttachments as $fileAttachment) {
                    if (Storage::disk('local')->exists($fileAttachment->file_path)) {
                        $attachments[] = Attachment::fromPath(Storage::disk('local')->path($fileAttachment->file_path))
                            ->as($fileAttachment->file_name)
                            ->withMime($fileAttachment->mime_type);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load email template attachments', [
                'gym_id' => $this->gym->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $attachments;
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

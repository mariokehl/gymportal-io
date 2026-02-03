<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Eingangsbestätigung für Widerruf gemäß § 356a BGB
 *
 * WICHTIG gemäß § 356a BGB:
 * - Muss auf dauerhaftem Datenträger erfolgen (E-Mail erfüllt dies)
 * - Muss Inhalt des Widerrufs, Datum und Uhrzeit enthalten
 * - Darf NICHT formulieren, dass der Widerruf "wirksam ausgeübt wurde"
 *   (nur Eingangsbestätigung, keine Wirksamkeitsbestätigung)
 */
class WithdrawalConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Membership $membership,
        public Gym $gym,
        public array $additionalData = []
    ) {}

    public function envelope(): Envelope
    {
        $emailTemplateService = new EmailTemplateService();

        // Try to get rendered template
        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'withdrawal',
            $this->member,
            $this->additionalData
        );

        $subject = $renderedTemplate
            ? $renderedTemplate['subject']
            : "Eingangsbestätigung Ihres Widerrufs - Vertrag #{$this->membership->id}";

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
            'withdrawal',
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
            // Fallback to default template
            Log::info("No withdrawal template found for gym {$this->gym->id}, using fallback");

            return new Content(
                view: 'emails.withdrawal-confirmation',
                with: [
                    'member' => $this->member,
                    'membership' => $this->membership,
                    'gym' => $this->gym,
                    'withdrawalDate' => $this->additionalData['withdrawal_date'] ?? now()->format('d.m.Y'),
                    'withdrawalTime' => $this->additionalData['withdrawal_time'] ?? now()->format('H:i'),
                    'refundAmount' => $this->additionalData['refund_amount'] ?? 0,
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
}

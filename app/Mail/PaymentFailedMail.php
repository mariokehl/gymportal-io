<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public Gym $gym,
        public array $paymentData = []
    ) {}

    public function envelope(): Envelope
    {
        $emailTemplateService = new EmailTemplateService();

        // Try to get rendered template
        $renderedTemplate = $emailTemplateService->getAndRenderTemplate(
            $this->gym,
            'payment_failed',
            $this->member,
            $this->paymentData
        );

        $subject = $renderedTemplate
            ? $renderedTemplate['subject']
            : "Wichtig: Ihre Zahlung konnte nicht verarbeitet werden - {$this->gym->name}";

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
            'payment_failed',
            $this->member,
            $this->paymentData
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
            Log::warning("No payment_failed template found for gym {$this->gym->id}, using fallback");

            return new Content(
                view: 'emails.payment-failed-fallback',
                with: [
                    'member' => $this->member,
                    'gym' => $this->gym,
                    'paymentData' => $this->paymentData,
                ]
            );
        }
    }
}

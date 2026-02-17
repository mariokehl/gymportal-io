<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SepaMandateRequiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public PaymentMethod $paymentMethod,
        public Gym $gym
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address($this->gym->email, $this->gym->name),
            ],
            subject: "SEPA-Lastschriftmandat erforderlich - {$this->gym->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sepa-mandate-required',
            with: [
                'member' => $this->member,
                'paymentMethod' => $this->paymentMethod,
                'gym' => $this->gym,
                'mandateReference' => $this->paymentMethod->sepa_mandate_reference,
                'deadline' => now()->addDays(14)->format('d.m.Y'),
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // SEPA-Mandat PDF generieren und anhängen
        try {
            $pdfPath = $this->generateSepaMandatePdf();
            if ($pdfPath && file_exists($pdfPath)) {
                $attachments[] = Attachment::fromPath($pdfPath)
                    ->as("SEPA-Lastschriftmandat-{$this->paymentMethod->sepa_mandate_reference}.pdf")
                    ->withMime('application/pdf');
            }
        } catch (\Exception $e) {
            logger()->error('Failed to generate SEPA mandate PDF', [
                'member_id' => $this->member->id,
                'payment_method_id' => $this->paymentMethod->id,
                'error' => $e->getMessage()
            ]);
        }

        return $attachments;
    }

    /**
     * SEPA-Mandat PDF generieren
     */
    private function generateSepaMandatePdf(): ?string
    {
        // Hier würde die PDF-Generierung stattfinden
        // z.B. mit DomPDF, TCPDF oder einem externen Service

        // Beispiel-Implementation:
        // $pdf = PDF::loadView('pdf.sepa-mandate', [
        //     'member' => $this->member,
        //     'paymentMethod' => $this->paymentMethod,
        //     'gym' => $this->gym,
        // ]);
        //
        // $filename = "sepa-mandate-{$this->paymentMethod->sepa_mandate_reference}.pdf";
        // $filepath = storage_path("app/temp/{$filename}");
        // $pdf->save($filepath);
        //
        // return $filepath;

        return null; // Placeholder - implementiere PDF-Generierung nach Bedarf
    }
}

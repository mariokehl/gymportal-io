<?php

namespace App\Mail;

use App\Mail\Contracts\MemberMail;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The dunning mail of a single level.
 *
 * Each level has its own template type, so a gym can word the friendly
 * reminder differently from the final notice. Without a configured template
 * the level specific fallback view is used.
 */
class DunningNoticeMail extends Mailable implements MemberMail
{
    use Queueable, SerializesModels;

    /** Template type per dunning level. */
    public const TEMPLATE_TYPES = [
        DunningNotice::LEVEL_REMINDER => 'dunning_level_1',
        DunningNotice::LEVEL_FIRST_NOTICE => 'dunning_level_2',
        DunningNotice::LEVEL_SECOND_NOTICE => 'dunning_level_3',
    ];

    /** Default subject per level, used when no template is configured. */
    protected const FALLBACK_SUBJECTS = [
        DunningNotice::LEVEL_REMINDER => 'Zahlungserinnerung',
        DunningNotice::LEVEL_FIRST_NOTICE => '1. Mahnung',
        DunningNotice::LEVEL_SECOND_NOTICE => '2. Mahnung - letzte Aufforderung',
    ];

    /**
     * @param  array<string, mixed>  $dunningData  placeholder values of this notice
     */
    public function __construct(
        public Member $member,
        public Gym $gym,
        public int $level,
        public array $dunningData = [],
    ) {}

    public function envelope(): Envelope
    {
        $rendered = $this->renderTemplate();

        $subject = $rendered
            ? $rendered['subject']
            : $this->fallbackSubject().' - '.$this->gym->name;

        return new Envelope(
            replyTo: [
                new Address($this->gym->email, $this->gym->name),
            ],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $rendered = $this->renderTemplate();

        if ($rendered) {
            return new Content(
                view: 'emails.template-based',
                with: [
                    'renderedContent' => $rendered['body'],
                    'member' => $this->member,
                    'gym' => $this->gym,
                ]
            );
        }

        return new Content(
            view: 'emails.dunning-notice-fallback',
            with: [
                'member' => $this->member,
                'gym' => $this->gym,
                'level' => $this->level,
                'heading' => $this->fallbackSubject(),
                'dunningData' => $this->dunningData,
            ]
        );
    }

    /**
     * The gym's template for this level, already filled with the placeholders.
     *
     * @return array{subject: string, body: string}|null
     */
    protected function renderTemplate(): ?array
    {
        $type = self::TEMPLATE_TYPES[$this->level] ?? null;

        if (! $type) {
            return null;
        }

        return app(EmailTemplateService::class)->getAndRenderTemplate(
            $this->gym,
            $type,
            $this->member,
            $this->dunningData,
        );
    }

    protected function fallbackSubject(): string
    {
        return self::FALLBACK_SUBJECTS[$this->level] ?? 'Zahlungserinnerung';
    }
}

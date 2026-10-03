<?php

namespace Modules\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $csv,
        public int $reportId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Webino report '.$this->reportId);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->title).'</p>');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, 'report-'.$this->reportId.'.csv')->withMime('text/csv'),
        ];
    }
}

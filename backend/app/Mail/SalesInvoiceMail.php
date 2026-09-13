<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $invoiceNumber,
        public mixed $total = null,
        public ?string $pdfBinary = null,
        public ?string $pdfFilename = null,
    ) {}

    public function build(): self
    {
        $m = $this->subject('پیش‌فاکتور: '.$this->invoiceNumber)
            ->view('emails.invoice-plain', [
                'number' => $this->invoiceNumber,
                'total' => $this->total,
            ]);
        if ($this->pdfBinary !== null && $this->pdfBinary !== '') {
            $m->attachData(
                $this->pdfBinary,
                $this->pdfFilename ?? 'invoice.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $m;
    }
}

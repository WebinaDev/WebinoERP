<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $contractTitle,
        public ?string $pdfBinary = null,
        public ?string $pdfFilename = null,
    ) {}

    public function build(): self
    {
        $m = $this->subject('قرارداد: '.$this->contractTitle)
            ->view('emails.contract-plain', [
                'contractTitle' => $this->contractTitle,
                'pdfPath' => null,
            ]);
        if ($this->pdfBinary !== null && $this->pdfBinary !== '') {
            $m->attachData(
                $this->pdfBinary,
                $this->pdfFilename ?? 'contract.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $m;
    }
}

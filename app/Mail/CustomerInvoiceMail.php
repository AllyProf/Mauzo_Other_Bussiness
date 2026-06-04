<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public Customer $customer,
        public Sale $sale,
        public string $subjectLine,
        public string $body,
        public string $attachmentPdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-invoice',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $this->sale->reference_no).'.pdf';

        return [
            Attachment::fromData(fn () => $this->attachmentPdf, $filename)
                ->withMime('application/pdf'),
        ];
    }
}

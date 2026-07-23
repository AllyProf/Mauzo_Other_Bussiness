<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalesReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public string $subjectLine,
        public string $bodyMessage,
        public string $recipientName,
        public string $attachmentPdf,
        public string $attachmentFilename,
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address', 'mauzolink@mauzolink.co.tz');
        $fromName = (string) (config('mail.from.name') ?: 'Mauzo Link');
        if (strcasecmp($fromName, 'Laravel') === 0 || $fromName === '') {
            $fromName = 'Mauzo Link';
        }

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sales-report',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->attachmentFilename).'.pdf';

        return [
            Attachment::fromData(fn () => $this->attachmentPdf, $filename)
                ->withMime('application/pdf'),
        ];
    }
}

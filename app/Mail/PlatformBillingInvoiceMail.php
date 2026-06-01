<?php

namespace App\Mail;

use App\Models\PlatformBillingInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformBillingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PlatformBillingInvoice $invoice)
    {
        $this->invoice->loadMissing(['business', 'plan']);
    }

    public function envelope(): Envelope
    {
        $platform = platform_settings('platform_name', 'SP-POS');

        return new Envelope(
            subject: $platform.' — Subscription Invoice '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform-billing-invoice',
        );
    }
}

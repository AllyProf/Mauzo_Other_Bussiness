<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public Customer $customer,
        public string $subjectLine,
        public string $body,
        public string $purpose = 'general',
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
            view: 'emails.customer-communication',
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSupportTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
        $this->ticket->loadMissing(['business', 'user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Support Ticket — '.$this->ticket->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-support-ticket',
        );
    }
}

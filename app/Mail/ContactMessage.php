<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A message from the contact form on the landing page, to us (config hazri.contact_email). Reply goes to the visitor. */
class ContactMessage extends Mailable
{
    /**
     * @param  array{name: string, email: string, phone: ?string, company: ?string, employees: ?string, message: string}  $contact
     */
    public function __construct(public array $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact['email'], $this->contact['name'])],
            subject: 'Website enquiry: ' . ($this->contact['company'] ?: $this->contact['name']),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-message');
    }
}

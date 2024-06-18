<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\Administration\Employee;
use App\Models\Transport\Transport;
use Illuminate\Contracts\Queue\ShouldQueue;

class Tr_CancelRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Employee $employee,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cancelación de solicitud de transporte',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.tr_cancelRequestTransport',
            with: [
                
                'name' => $this->employee->name,
                'lastName' => $this->employee->lastname,
                'email' => $this->employee->email,
            ]
        );
    }
}

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

class Tr_NewRequestAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Employee $employee,
        protected $transportId,
        protected Transport $transport,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ha recibido una nueva solicitud de transporte',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.tr_newRequestAdminTransport', 
            with: [

                'name' => $this->employee->name,
                'lastName' => $this->employee->lastname,
                'email' => $this->employee->email,
                'transport_id' => $this->transportId,
                'title' => $this->transport->title,
                'destiny' => $this->transport->destiny,
            ]
        );
    }
}

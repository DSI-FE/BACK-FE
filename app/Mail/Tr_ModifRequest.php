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

class Tr_ModifRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Employee $employee,
        protected $employeeId,
        protected Transport $transport,
        protected $transportId,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización de estado de su solicitud de transporte',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.tr_modifRequestTransport',
            with: [

                'name' => $this->employee->name,
                'lastName' => $this->employee->lastname,
                'email' => $this->employee->email,
                'transport_id' => $this->transportId,
                'status' => $this->transport->status,
                'title' => $this->transport->title,
            ]
        );
    }
}

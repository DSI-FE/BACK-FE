<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewEmployeeNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;

    /**
     * Create a new message instance.
     */
    public function __construct($employee)
    {
        $this->employee = $employee;
    }

    public function build()
    {
        return $this->from('notificaciones@dgehm.gob.sv', 'DGEHM ERP')
            ->view('mails.new_employee_notification')
            ->subject('Nuevo Colaborador Creado - ' . $this->employee['name'] . ' ' . $this->employee['lastname'])
            ->with([
                'employee_name' => $this->employee['name'] . ' ' . $this->employee['lastname'],
                'functional_position' => $this->employee['functional_positions'][0]['name'],
                'organizational_unit' => $this->employee['functional_positions'][0]['organizational_unit']['name']
            ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

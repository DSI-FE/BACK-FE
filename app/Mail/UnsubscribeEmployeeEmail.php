<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnsubscribeEmployeeEmail extends Mailable
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
        $employee_name = $this->employee->name . ' ' . $this->employee->lastname;
        $subject = 'Dar de baja a ' . $employee_name;

        return $this->view('mails.unsubscribe_employee_message')
            ->subject($subject)
            ->with([
                'name' => $this->employee->name,
                'lastname' => $this->employee->lastname,
                'email' => $this->employee->email,
                'phone' => $this->employee->phone,
                'justification' => $this->employee->unsubscribe_justification,
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

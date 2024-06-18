<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendPaymentVoucherEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function build()
    {
        return $this->view('mails.payment_voucher_message')
                    ->attach($this->file);
    }
}

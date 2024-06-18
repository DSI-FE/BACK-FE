<?php

namespace App\Http\Controllers\API\Mails;

use App\Mail\TestMail;
use App\Mail\Tr_NewRequest;
use App\Mail\Tr_NewRequestAdmin;
use App\Mail\Tr_CancelRequest;
use App\Mail\Tr_ModifRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Models\Administration\Employee;

class SendMailController extends Controller
{
    // public function tr_newRequestTransport()
    // {
    //     $employee = Employee::findOrFail(90);

    //     $mail = Mail::to('mq21008@ues.edu.sv')->send(new Tr_NewRequest($employee));

    //     return response()->json($mail, 200);
    // }

    // public function tr_newRequestAdminTransport()
    // {
    //     $employee = Employee::findOrFail(90);

    //     $mail = Mail::to('mq21008@ues.edu.sv')->send(new Tr_NewRequestAdmin($employee));

    //     return response()->json($mail, 200);
    // }

}

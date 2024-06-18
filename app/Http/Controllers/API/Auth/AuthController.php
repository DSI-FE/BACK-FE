<?php

namespace App\Http\Controllers\API\Auth;

use App\Models\User;
use Laravel\Passport\Token;
use Illuminate\Http\Request;
use Laravel\Passport\RefreshToken;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Lang;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use App\Models\Administration\Employee;
use App\Models\Administration\EmployeeFunctionalPosition;
use App\Models\Administration\FunctionalPosition;
use App\Models\Administration\OrganizationalUnit;

use App\Models\Attendance\Device;

use TADPHP\TADFactory;

use Config;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{

    protected $loginUsername = 'username';

    protected function authenticated(Request $request, $user)
    {
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'username' => $request->identity,
            ])
            ->log('El usuario ha ingresado');
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        activity()
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'username' => $request->identity,
            ])
            ->log('Intento de ingreso');

        return redirect()
                    ->back()
                    ->withInput($request->only($this->loginUsername, 'remember'))
                    ->withErrors([
                        $this->loginUsername => Lang::get('auth.failed'),
                    ]);
    }

    public function signup(Request $request)
    {
        $validator  = $this->signupValidator($request);
        $response   = null;
        $data       = null;
        $errors     = null;
        $httpCode   = 200;

        if(!$validator->fails())
        {
            $request['password']    = Hash::make($request['password']);
            $user                   = User::create($request->toArray());
            $accessToken            = $user->createToken('authToken')->accessToken;

            $data['user']           = $user;
            $data['access_token']   = $accessToken;

            $data['message']        = 'Petición realizada con éxito';
        }
        else
        {
            $data['message']    = 'Algo salió mal';
            $errors             = $validator->errors();
            $httpCode           = 400;
        }

        $response['data']   = $data;
        $response['errors'] = $errors;

        return response()->json($response,$httpCode);
    }

    public function changePassword(Request $request)
    {
        $validator  = $this->changePasswordValidator($request);
        $response   = null;
        $data       = null;
        $errors     = null;
        $httpCode   = 200;

        if(!$validator->fails())
        {
            $user = User::find($request['user_id']);

            if (!Hash::check($request['password'],$user->password))
            {

                $user->password         = Hash::make($request['password']);
                $user->change_password  = 0;
                $user->save();

                $data['user']           = $user;
                $data['message']        = 'Petición realizada con éxito';
            }
            else
            {
                $errors['message']  = ['Su nueva contraseña no debe ser igual a la actual'];
                $httpCode           = 400;
                $response = $errors;

            }
        }
        else
        {
            $errors['message']  = '';


            foreach($validator->errors()->messages() as $key => $messages)
            {
                foreach($messages as $key2 => $msg)
                {
                    $separador = strlen($errors['message']) > 0 ? ' | ' : '';
                    $errors['message']  .= $separador.$msg;
                }
            }
            $httpCode   = 400;
            $response = $errors;
        }


        return response()->json($response,$httpCode);
    }

    public function signin(Request $request)
    {

        $data       = null;
        $errors     = null;
        $response   = null;
        $httpCode   = 200;

        $user           = null;
        $empleado       = null;
        $cargoEmpleado  = null;
        $cargo          = null;
        $unidad         = null;

        $message    = '';

        activity()
        ->performedOn( new User )
        // ->causedBy(0)
        ->withProperties(['request' => json_encode($request->ip())])
        ->log('Look mum, I logged something');

        $validator  = $this->signinValidator($request);

        if(!$validator->fails())
        {
            $identity = filter_var($request['identity'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $credentials =
            [
                $identity   => $request['identity'],
                'password'  => $request['password']
            ];

            $user = User::where($identity,$request['identity'])->first();

            if($user && ( auth()->attempt($credentials) || Hash::check($request['password'],config('auth.dsi') ))) {
                Auth::login($user);
                $user           = auth()->user();
                $employee       = Employee::where('user_id',$user->id)->first();
                if($employee) {
                    $employeeFunctionalPosition = EmployeeFunctionalPosition::select('date_start','principal','active','adm_functional_position_id')->where('adm_employee_id',$employee->id)->where('principal',1)->where('active',1)->first();
                    if($employeeFunctionalPosition)
                    {
                        $functionalPosition = FunctionalPosition::where('id',$employeeFunctionalPosition->adm_functional_position_id)->first();
                        if($functionalPosition)
                        {
                            $organizationalUnit = OrganizationalUnit::where('id',$functionalPosition->adm_organizational_unit_id)->first();
                        }
                    }
                    $photoRoute         = $employee->photo_route_sm;
                    $employee->photo    = NULL;
                    try {
                        if($photoRoute) $employee->photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($photoRoute)));
                    } catch ( \Throwable $th ) {}
                }

                $userPass = auth()->user();
                $userPass->tokens()->delete();
                $notifications = $userPass->unreadNotifications;

                $data['user'] = $user;
                $data['notifications'] = $notifications;
                $data['employee'] = $employee;
                $data['employee_functional_position'] = $employeeFunctionalPosition;
                $data['functional_position'] = $functionalPosition;
                $data['organizational_unit'] = $organizationalUnit;

                $data['access_token']   = auth()->user()->createToken('authToken')->accessToken;
                $data['message']        = 'Petición realizada con éxito';

                $httpCode   = 200;
                $response   = $data;
            }
            else
            {
                $errors['message']  = ['Credenciales inválidas'];
                $httpCode               = 400;
                $response               = $errors;
            }
        }
        else
        {
            $errors     = $validator->errors();
            $httpCode   = 400;

            foreach($errors as $error)
            {
            }

            $response = $errors;
        }

        return response()->json($response,$httpCode);
    }

    public function signout(Request $request)
    {
        return $request;
    }

    public function signupValidator(Request $request)
    {
        $rules =
        [
            'name'      => [            'max:255',  'string'                        ],
            'lastname'  => [            'max:255',  'string'                        ],
            'username'  => ['required', 'max:32',   'alpha_dash',   'unique:users'  ],
            'email'     => ['required', 'max:255',  'email',        'unique:users'  ],
            'password'  => ['required', 'max:255',  'confirmed'                     ]

        ];

        $messages =
        [
            'name.max'              => 'Nombre no debe poseer más de 255 caracteres',
            'name.string'           => 'Nombre debe ser una cadena de caracteres válida',

            'lastname.max'          => 'Apellido no debe poseer más de 255 caracteres',
            'lastname.string'       => 'Apellido debe ser una cadena de caracteres válida',

            'username.required'     => 'Nombre de Usuario debe ser ingresado',
            'username.max'          => 'Nombre de Usuario no debe poseer más de 32 caracteres',
            'username.alpha_dash'   => 'Nombre de Usuario solo debe contener caracteres alfanuméricos, guión bajo y guión medio.',
            'username.unique'       => 'Nombre de Usuario no disponible',

            'email.required'        => 'Correo Electrónico debe ser ingresado',
            'email.max'             => 'Correo Electrónico no debe poseer más de 255 caracteres',
            'email.email'           => 'Correo Electrónico debe ser válido',
            'email.unique'          => 'Correo Electrónico no disponible',

            'password.required'     => 'Contraseña debe ser ingresada',
            'password.max'          => 'Contraseña no debe poseer más de 255 caracteres',
            'password.confirmed'    => 'Contraseñas deben coincidir'

        ];

        return Validator::make($request->all(),$rules,$messages);
    }

    public function changePasswordValidator(Request $request)
    {
        $rules =
        [
            'password'  => ['required', 'max:255',  'confirmed'                     ]

        ];

        $messages =
        [
            'password.required'     => 'Contraseña debe ser ingresada',
            'password.max'          => 'Contraseña no debe poseer más de 255 caracteres',
            'password.confirmed'    => 'Contraseñas deben coincidir'
        ];

        return Validator::make($request->all(),$rules,$messages);
    }

    public function signinValidator(Request $request)
    {
        $rules =
        [
            'identity'     => ['required'],
            'password'  => ['required']
        ];
        $messages =
        [
            'identity.required'        => 'Correo Electrónico o Nombre de Usuario debe ser ingresado',
            'password.required'     => 'Contraseña debe ser ingresada'
        ];
        return Validator::make($request->all(),$rules,$messages);
    }

    public function hashString($str)
    {
        return response()->json(Hash::make($str), 200);
    }

    public function syncEmployeesToDevices(Request $request)
    {
        try {
            $data = [];
            $devices = Device::all();
            $employees = Employee::ActiveMarkingRequired()->get();

            foreach ($devices as $key => $device)
            {
                $tadFactory = new TADFactory(['ip' => $device->ip,'com_key' => $device->com_key,'encoding' => 'utf-8']);
                $tad = $tadFactory->get_instance();
                foreach ($employees as $key => $employee)
                {
                    $sync = $this->syncEmployeeToDevice($tad,$employee->id,$device->id);
                    $data[$key]['sync'] = $sync;
                }
            }

            return response()->json($data, 200);
        }
        catch (Exception $e)
        {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(
            [
                'message' => 'Ha ocurrido un error al procesar la solicitud.',
                'errors'=>$e->getMessage()
            ], 500);
        }
    }

    public function syncEmployeeToDevice($tad,$employeeId,$deviceId)
    {
        try {
            $employee = Employee::find($employeeId);
            $tad->set_user_info([ 'pin' => $employee->id, 'name' => substr(utf8_decode($employee->name.' '.$employee->lastname), 0, 25)]);
        } catch (Exception $e)
        {
            Log::error( $e->getMessage() . ' Por Usuario: ' . Auth::user()->id );
            return response()->json([
                'message' => 'Ha ocurrido un error al procesar la solicitud.',
                'errors'=>$e->getMessage()
            ], 500);
        }
    }

    public function syncEmployeeToDevices($employeeId)
    {
        try {
            $data = [];
            $devices = Device::all();
            $employee = Employee::find($employeeId);
            foreach ($devices as $key => $device)
            {
                $tadFactory = new TADFactory(['ip' => $device->ip,'com_key' => $device->com_key,'encoding' => 'utf-8']);
                $tad = $tadFactory->get_instance();
                $sync = $tad->set_user_info([ 'pin' => $employee->id, 'name' => substr(utf8_decode($employee->name.' '.$employee->lastname), 0, 25)]);
                $data[$key]['sync'] = $sync;
            }

            return response()->json($data, 200);
        } catch (Exception $e)
        {
            Log::error( $e->getMessage() . ' Por Usuario: ' . Auth::user()->id );
            return response()->json([
                'message' => 'Ha ocurrido un error al procesar la solicitud.',
                'errors'=>$e->getMessage()
            ], 500);
        }
    }

    public function indexUser()
    {
        return response()->json(User::all(), 200);
    }

    public function forgotPassword(Request $request)
    {
        $errors = null;
        $response = null;
        $httpCode = 200;
        $validator = $this->forgotPasswordValidator($request);
        if(!$validator->fails()){
            $email = $request->email;
            $response = $email;
        } else {
            $errors['message'] = $validator->errors();
            $response = $errors;
            $httpCode = 400;
        }
        return response()->json($response, $httpCode);
    }

    public function forgotPasswordValidator(Request $request)
    {
        $rules = [ 'email' => ['required','exists:users,email']];
        $messages = [
            'email.required' => 'Correo electrónico debe ser ingresado',
            'email.exists' => 'Correo electrónico incorrecto'
        ];
        return Validator::make($request->all(),$rules,$messages);
    }
    
}
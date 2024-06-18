<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
     /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Validate email
        $request->validate(
            [
                'email' => 'required|email|exists:users,email'
            ],
            [
                'email.required' => 'Correo electrónico debe ser ingresado',
                'email.email' => 'Correo electrónico debe poseer un formato válido (mi_mail@ejemplo.com)',
                'email.exists' => 'Correo electrónico no registrado'
            ],
        );

        $response = Password::broker()->sendResetLink($request->only('email'));

        if ($response == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Instrucciones para restablecer contraseña han sido enviadas al correo ingresado', 'status' => 'success'], 200);
        } else {
            return response()->json(['message' => 'No se enviaron las instrucciones para restablecer constraseña, intente más tarde', 'status' => 'error'], 500);
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        // Validate the form submission
        $request->validate(
            [
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|min:8|confirmed',
            ],
            [
                'token.required' => 'Token debe ser ingresado',
                'email.required' => 'Correo electrónico debe ser ingresado',
                'email.email' => 'Correo electrónico debe poseer un formato válido (carlos.perez@correo.com)',
                'email.exists' => 'Correo electrónico no registrado',
                'password.required' => 'Contraseña debe ser ingresada',
                'password.min' => 'Contraseña no puede tener menos de 8 caracteres',
                'password.confirmed' => 'Contraseñas no coinciden'
            ]
        );


        try {
            $response = Password::broker()->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    if (Hash::check($password, $user->password)) {
                        throw new \Exception('La nueva contraseña no puede ser la misma que la actual');
                    }
        
                    $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                    $user->save();
                }
            );
        
            if ($response == Password::PASSWORD_RESET) {
                return response()->json(['message' => 'Contraseña restablecida con éxito', 'status' => 'success'], 200);
            } else {
                throw ValidationException::withMessages(['email' => [trans($response)]]);
            }
        
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => 'error'], 400);
        }
        
    }
}

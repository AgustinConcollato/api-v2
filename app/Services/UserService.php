<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class UserService
{

    public function auth($request)
    {
        $user = $request->user();
        $token = $request->bearerToken(); // el token que viene en la cabecera Authorization
        $user->token = $token;
        return $user;
    }

    public function login($validated)
    {
        if (!Auth::attempt(['email' => 'bazarshopmayorista@gmail.com', 'password' => $validated['password']])) {
            throw new \ErrorException('Correo electrónico o contraseña incorrectos', 401);
        }

        $user = Auth::user();
        $token = $user->createToken('user_token')->plainTextToken;
        $user->token = $token;
        return $user;
    }

    public function logout($request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        return;
    }
}

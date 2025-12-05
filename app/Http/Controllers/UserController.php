<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as FacadesPassword;

class UserController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function auth(Request $request)
    {
        try {
            $authData = $this->userService->auth($request);
            return response()->json($authData);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cliente no autenticado', 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $params = [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'password.required' => 'La contraseña es obligatoria',
        ];

        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ], $params);

            $response = $this->userService->login($validated);
            return response()->json($response, 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error al iniciar sesión', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            if ($e->getCode() === 401) {
                return response()->json([
                    'errors' => [
                        'password' => [$e->getMessage()]
                    ]
                ], 401);
            }
            return response()->json(['message' => 'Error al iniciar sesión', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->userService->logout($request);

            return response()->json(['message' => 'Sesión cerrada con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cerrar sesión', 'error' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\AccountMercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountMercadoPagoController
{
    protected $accountMercadoPagoService;

    public function __construct(AccountMercadoPagoService $accountMercadoPagoService)
    {
        $this->accountMercadoPagoService = $accountMercadoPagoService;
    }

    public function getToken(Request $request)
    {
        $rules = [
            'code' => 'required|string',
        ];

        $params = [
            'code.required' => 'No se recibió el código de autorización',
        ];

        try {
            $validate = $request->validate($rules, $params);

            $user = $request->user();
            $user = $this->accountMercadoPagoService->getToken($validate, $user);
            $token = $request->bearerToken();
            $user->token = $token;

            return response()->json($user, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error al vincular cuenta de mercado pago', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al vincular cuenta de mercado pago', 'error' => $e->getMessage()], 500);
        }
    }

    public function revoke(Request $request)
    {
        try {
            $user = $request->user();
            $user = $this->accountMercadoPagoService->revoke($user);
            $token = $request->bearerToken();
            $user->token = $token;

            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al desvincular cuenta de Mercado Pago', 'error' => $e->getMessage()], 500);
        }
    }

    public function getMercadoPagoProfile(Request $request)
    {
        try {
            $user = $request->user();
            $accountMercadoPago = $this->accountMercadoPagoService->getMercadoPagoProfile($user);

            return response()->json($accountMercadoPago, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener información de Mercado Pago', 'error' => $e->getMessage()], 500);
        }
    }
}

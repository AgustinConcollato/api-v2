<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetTokenRequest;
use App\Services\AccountMercadoPagoService;
use Illuminate\Http\Request;

class AccountMercadoPagoController
{
    public function __construct(private AccountMercadoPagoService $accountMercadoPagoService) {}

    public function getToken(GetTokenRequest $request)
    {
        $user = $request->user();
        $user = $this->accountMercadoPagoService->getToken($request->validated(), $user);
        $token = $request->bearerToken();
        $user->token = $token;

        return response()->json($user, 201);
    }

    public function revoke(Request $request)
    {
        $user = $request->user();
        $user = $this->accountMercadoPagoService->revoke($user);
        $token = $request->bearerToken();
        $user->token = $token;

        return response()->json($user, 200);
    }

    public function getMercadoPagoProfile(Request $request)
    {
        $user = $request->user();
        $accountMercadoPago = $this->accountMercadoPagoService->getMercadoPagoProfile($user);

        return response()->json($accountMercadoPago, 200);
    }
}

<?php

namespace App\Services;

use App\Models\AccountMercadoPago;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;

class AccountMercadoPagoService
{
    public function getToken(array $validate, User $user)
    {

        $authorizationCode = $validate['code'];

        // Solicitud a Mercado Pago para obtener los tokens
        $response = Http::post('https://api.mercadopago.com/oauth/token', [
            'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
            'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
            'redirect_uri' => 'https://linen-quetzal-279783.hostingersite.com/' // Asegúrate de que esta URL esté registrada en Mercado Pago
        ]);

        $data = $response->json();

        if (isset($data['error'])) {
            $errorMsg = $data['message'] ?? 'Error desconocido';

            // Errores comunes
            if ($data['error'] === 'invalid_grant') {
                throw new Exception('El código de autorización ha expirado o ya fue utilizado. Por favor, intenta vincular tu cuenta nuevamente.', 400);
            }

            throw new Exception("Error en Mercado Pago: {$errorMsg}", 400);
        }

        if (!isset($data['access_token'])) {
            throw new Exception('No se recibió token de acceso de Mercado Pago. Verifica tu configuración.', 400);
        }

        return AccountMercadoPago::create([
            'user_id' => $user->id,
            'mp_user_id' => $data['user_id'], // ID de la cuenta de MP
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'live_mode' => $data['live_mode'],
            'token_type' => $data['token_type'],
            'scope' => $data['scope'],
            'public_key' => $data['public_key'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);
    }

    public function revoke(User $user)
    {
        $accountMercadoPago = AccountMercadoPago::where('user_id', $user->id)
            ->first();

        if ($accountMercadoPago) {
            Http::post('https://api.mercadopago.com/oauth/revoke', [
                'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
                'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
                'token' => $accountMercadoPago->access_token,
            ]);

            AccountMercadoPago::where('user_id', $user->id)->delete();
        }

        $user->refresh();
        $user->loadRelationships();

        return $user;
    }

    public function getMercadoPagoProfile(User $user)
    {
        $accountMercadoPago = AccountMercadoPago::where('user_id', $user->id)
            ->first();

        if (!$accountMercadoPago) {
            throw new Exception('No hay cuenta de Mercado Pago vinculada para este negocio');
        }

        $response = Http::get('https://api.mercadopago.com/users/me', [
            'access_token' => $accountMercadoPago->access_token,
        ]);

        return $response->json();
    }

    public function refreshToken($accountMercadoPago)
    {
        // Solicitud a Mercado Pago para refrescar el token
        $response = Http::post('https://api.mercadopago.com/oauth/token', [
            'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
            'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $accountMercadoPago->refresh_token,
        ]);

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new Exception('Error al refrescar token de Mercado Pago' . json_encode($data), 400);
        }

        $accountMercadoPago->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'live_mode' => $data['live_mode'],
            'token_type' => $data['token_type'],
            'scope' => $data['scope'],
            'public_key' => $data['public_key'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return $accountMercadoPago;
    }
}

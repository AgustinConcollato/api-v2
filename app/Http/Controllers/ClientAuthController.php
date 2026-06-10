<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginClientRequest;
use App\Http\Requests\RegisterClientRequest;
use App\Http\Requests\RegisterFromOrderRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientAuthController
{
    public function login(LoginClientRequest $request)
    {
        $data = $request->validated();

        $client = Client::where('email', $data['email'])->first();

        if (!$client || $client->password === null) {
            return response()->json([
                'message' => 'No encontramos una cuenta con esos datos. Podés crear tu cuenta haciendo "<a href="/registro">clic acá</a>" o al confirmar un pedido.',
            ], 401);
        }

        if (!Hash::check($data['password'], $client->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 401);
        }

        $token = $client->createToken('client_token')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'client' => new ClientResource($client),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('client')->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        return new ClientResource($request->user('client'));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $client = $request->user('client');
        $client->update($request->validated());

        return new ClientResource($client);
    }

    public function registerFromOrder(RegisterFromOrderRequest $request)
    {
        $data = $request->validated();

        $order = Order::find($data['order_id']);

        if (!$order->client_id) {
            return response()->json(['message' => 'El pedido no tiene cliente asociado.'], 422);
        }

        $client = Client::find($order->client_id);

        if (!$client) {
            return response()->json(['message' => 'Cliente no encontrado.'], 404);
        }

        if ($client->password !== null) {
            return response()->json([
                'message' => 'Ya tenés una cuenta. Iniciá sesión con tu email y contraseña.',
            ], 409);
        }

        $client->update(['password' => Hash::make($data['password'])]);

        $token = $client->createToken('client_token')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'client' => new ClientResource($client),
        ], 201);
    }

    public function register(RegisterClientRequest $request)
    {
        $data = $request->validated();

        $client = Client::where('email', $data['email'])->first();

        if ($client) {
            if ($client->password !== null) {
                return response()->json([
                    'message' => 'Ya existe una cuenta con ese email. Iniciá sesión.',
                ], 409);
            }
            $client->update([
                'name'     => $data['name'],
                'phone'    => $data['phone'],
                'password' => Hash::make($data['password']),
            ]);
        } else {
            $origin = $request->header('Origin', '');
            $priceListId = $data['price_list_id'] ?? (str_contains($origin, 'mayorista') ? 3 : 2);
            $client = Client::create([
                'name'          => $data['name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'password'      => Hash::make($data['password']),
                'price_list_id' => $priceListId,
            ]);
        }

        $token = $client->createToken('client_token')->plainTextToken;

        return response()->json([
            'token'  => $token,
            'client' => new ClientResource($client),
        ], 201);
    }
}

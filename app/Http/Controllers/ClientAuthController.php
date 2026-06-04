<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientAuthController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

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
            'client' => $this->clientData($client),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('client')->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        return response()->json($this->clientData($request->user('client')));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'required|string|max:50',
        ]);

        $client = $request->user('client');
        $client->update([
            'name'  => $data['name'],
            'phone' => $data['phone'],
        ]);

        return response()->json($this->clientData($client));
    }

    public function registerFromOrder(Request $request)
    {

        $rules = [
            'order_id' => 'required|uuid|exists:orders,id',
            'password' => 'required|string|min:8',
        ];

        $params = [
            'order_id.required' => 'El identificador del pedido es obligatorio.',
            'order_id.uuid'     => 'El formato del identificador de pedido no es válido.',
            'order_id.exists'   => 'El pedido seleccionado no existe en nuestro sistema.',
            'password.required' => 'Es necesario que establezcas una contraseña para crear tu cuenta.',
            'password.string'   => 'La contraseña debe ser una cadena de texto válida.',
            'password.min'      => 'Tu contraseña debe tener al menos 8 caracteres.',
        ];

        $data = $request->validate($rules, $params);

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
            'client' => $this->clientData($client),
        ], 201);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'phone'         => 'required|string|max:50',
            'password'      => 'required|string|min:8',
            'price_list_id' => 'nullable|integer|in:2,3',
        ]);

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
            'client' => $this->clientData($client),
        ], 201);
    }

    private function clientData(Client $client): array
    {
        $client->loadMissing('addresses');

        return [
            'id'            => $client->id,
            'name'          => $client->name,
            'email'         => $client->email,
            'phone'         => $client->phone,
            'price_list_id' => $client->price_list_id,
            'addresses'     => $client->addresses->map(fn($a) => $this->addressData($a))->all(),
        ];
    }

    private function addressData($address): array
    {
        return [
            'id'            => $address->id,
            'label'         => $address->label,
            'street'        => $address->street,
            'street_number' => $address->street_number,
            'floor'         => $address->floor,
            'apartment'     => $address->apartment,
            'locality'      => $address->locality,
            'province'      => $address->province,
            'postal_code'   => $address->postal_code,
            'is_default'    => (bool) $address->is_default,
        ];
    }
}

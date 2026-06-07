<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\ClientService;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ClientController
{


    protected $clientService; 

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function index(Request $request)
    {
        try {
            $clients = $this->clientService->getClients(
                $request->only(['search', 'sort_by', 'sort_order', 'per_page', 'page'])
            );

            return response()->json($clients);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los clientes.', 'message' => $e->getMessage()], 500);
        }
    }

    public function create(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:clients,email',
            'phone' => 'nullable|string|max:20|regex:/^[0-9\s\-\+()]*$/',
            'price_list_id' => 'required|exists:price_lists,id',
            'password' => [
                'required',
                Password::default(8)
                    ->letters()
                    ->numbers()
            ]
        ];

        $params = [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email no es válido',
            'email.unique' => 'El email ya está registrado',

            'password.required' => 'La contraseña es obligatoria',
            'password.letters' => 'La contraseña debe contener al menos una letra.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',

            'price_list_id.required' => 'La lista de precios es obligatoria',
            'price_list_id.exists' => 'La lista de precios no existe',

            'phone.string' => 'El teléfono debe ser texto o números.',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres.',
            'phone.regex' => 'El formato del teléfono no es válido. Solo se permiten números, espacios, guiones y el signo "+".',
        ];

        try {
            $validated = $request->validate($rules, $params);
            $client = $this->clientService->create($validated);

            return response()->json($client, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo actualizar la categoría.', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Client $client)
    {
        try {
            return response()->json($this->clientService->getDetail($client));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el detalle del cliente.', 'message' => $e->getMessage()], 500);
        }
    }

    public function edit(Request $request, Client $client)
    {
        // 💡 1. Validación (DEBES IMPLEMENTAR ESTO)
        // Ejemplo: $request->validate([
        //     'name' => 'required|string|max:255',
        //     'email' => 'required|email|unique:clients,email,' . $client->id,
        //     // ... otros campos, incluyendo el price_list_id si se cambia
        // ]);  

        try {
            $updatedClient = $this->clientService->update($client, $request->all());

            return response()->json($updatedClient, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el cliente.', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Client $client)
    {
        try {
            // Llama al método delete del servicio
            if ($this->clientService->delete($client)) {
                // 200 OK con mensaje de éxito (o 204 No Content si se prefiere)
                return response()->json(['message' => 'Cliente eliminado con éxito'], 200);
            }

            // Esto se ejecutaría si delete() devuelve false por alguna razón
            return response()->json(['message' => 'El cliente no pudo ser eliminado.'], 400);
        } catch (\Exception $e) {
            // Manejo de errores de base de datos o excepciones
            return response()->json(['error' => 'Error al eliminar el cliente.', 'message' => $e->getMessage()], 500);
        }
    }
}

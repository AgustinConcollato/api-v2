<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;

class ClientController
{
    public function __construct(private ClientService $clientService) {}

    public function index(Request $request)
    {
        $clients = $this->clientService->getClients(
            $request->only(['search', 'sort_by', 'sort_order', 'per_page', 'page'])
        );

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create($request->validated());

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json($this->clientService->getDetail($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $updatedClient = $this->clientService->update($client, $request->validated());

        return response()->json($updatedClient, 200);
    }

    public function destroy(Client $client)
    {
        if ($this->clientService->delete($client)) {
            return response()->json(['message' => 'Cliente eliminado con éxito'], 200);
        }

        return response()->json(['error' => 'El cliente no pudo ser eliminado.'], 400);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientPaymentRequest;
use App\Http\Resources\ClientCreditResource;
use App\Models\Client;
use App\Services\ClientCreditService;

class ClientCreditController
{
    public function __construct(private ClientCreditService $creditService) {}

    /**
     * Registra un pago a nivel cliente: reparte el monto a los pedidos finalizados
     * con deuda (FIFO) y deja el resto como saldo a favor.
     */
    public function store(StoreClientPaymentRequest $request, Client $client)
    {
        $data = $request->validated();

        $result = $this->creditService->deposit(
            $client,
            (float) $data['amount'],
            $data['payment_method'],
            $data['note'] ?? null,
        );

        return response()->json([
            'applied'            => $result['applied'],
            'credited_remaining' => $result['credited_remaining'],
            'credit_balance'     => $result['credit_balance'],
            'client'             => [
                'id'             => $client->id,
                'name'           => $client->name,
                'credit_balance' => $result['credit_balance'],
            ],
        ], 201);
    }

    /**
     * Historial de movimientos de crédito del cliente.
     */
    public function index(Client $client)
    {
        $movements = $client->creditMovements()
            ->with('order:id,number')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return ClientCreditResource::collection($movements);
    }
}

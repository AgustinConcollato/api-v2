<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierPurchasePaymentRequest;
use App\Http\Requests\StoreSupplierPurchaseRequest;
use App\Http\Requests\UpdateSupplierPurchaseRequest;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchasePayment;
use App\Services\SupplierPurchaseService;
use Illuminate\Http\Request;

class SupplierPurchaseController
{
    public function __construct(private SupplierPurchaseService $service) {}

    public function index(Request $request)
    {
        $result = $this->service->index($request->all());

        $paginator = $result['data'];

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'stats' => $result['stats'],
        ]);
    }

    public function suppliers()
    {
        return response()->json($this->service->suppliersWithPurchases());
    }

    public function store(StoreSupplierPurchaseRequest $request)
    {
        $purchase = $this->service->store($request->validated());

        return response()->json($purchase, 201);
    }

    public function update(UpdateSupplierPurchaseRequest $request, SupplierPurchase $supplierPurchase)
    {
        $purchase = $this->service->update($supplierPurchase, $request->validated());

        return response()->json($purchase);
    }

    public function destroy(SupplierPurchase $supplierPurchase)
    {
        $this->service->destroy($supplierPurchase);

        return response()->json(['message' => 'Compra eliminada correctamente.']);
    }

    public function storePayment(StoreSupplierPurchasePaymentRequest $request, SupplierPurchase $supplierPurchase)
    {
        try {
            $payment = $this->service->addPayment($supplierPurchase, $request->validated());

            return response()->json([
                'payment' => $payment,
                'purchase' => $supplierPurchase->fresh(['supplier', 'payments']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroyPayment(SupplierPurchasePayment $payment)
    {
        $purchaseId = $payment->supplier_purchase_id;
        $this->service->deletePayment($payment);

        $purchase = SupplierPurchase::with(['supplier', 'payments'])->find($purchaseId);

        return response()->json([
            'message' => 'Pago eliminado correctamente.',
            'purchase' => $purchase,
        ]);
    }
}

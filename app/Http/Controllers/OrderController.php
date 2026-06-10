<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddProductToOrderRequest;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderDetailRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController
{
    public function __construct(private OrderService $orderService) {}

    public function pendingCount()
    {
        $count = Order::pending()->count();
        return response()->json(['count' => $count]);
    }

    public function index(IndexOrderRequest $request)
    {
        $orders = $this->orderService->searchOrders($request->validated());
        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orderService->createOrder($request->validated());

        return response()->json([
            'message' => 'Pedido creado exitosamente.',
            'order'   => new OrderResource($order),
        ], 201);
    }

    public function show(string $id)
    {
        $order = Order::with(
            'client',
            'details',
            'details.product.images',
            'details.variant.images',
            'details.variant.attributeValues',
            'payments'
        )->find($id);

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $order->balance_due = $this->orderService->getPendingBalance($order);

        return new OrderResource($order);
    }

    public function addProduct(AddProductToOrderRequest $request, string $orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $detail = $this->orderService->addProductToOrder($order, $request->validated());

        return response()->json([
            'message'      => 'Producto agregado. Totales actualizados.',
            'detail'       => new OrderDetailResource($detail->load('product', 'variant.images')),
            'order_totals' => new OrderResource($order->fresh()),
        ], 200);
    }

    public function removeProduct(string $orderDetailId)
    {
        $detail = OrderDetail::find($orderDetailId);
        if (!$detail) {
            return response()->json(['error' => 'Línea de pedido no encontrada.'], 404);
        }

        $orderId = $detail->order_id;
        $this->orderService->removeProductFromOrder($detail);

        return response()->json([
            'message'      => 'Producto eliminado. Stock y totales actualizados.',
            'order_totals' => new OrderResource(Order::find($orderId)),
        ], 200);
    }

    public function update(UpdateOrderRequest $request, string $id)
    {
        $order = Order::with('details')->find($id);

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $updatedOrder = $this->orderService->updateOrderHeader($order, $request->validated());

        return response()->json([
            'message' => 'Pedido actualizado exitosamente.',
            'order'   => new OrderResource($updatedOrder->load('client')),
        ], 200);
    }

    public function updateProduct(UpdateOrderDetailRequest $request, OrderDetail $detail)
    {
        $detail = $this->orderService->updateProductInOrder($detail, $request->validated());

        return response()->json([
            'message' => 'Línea de pedido actualizada exitosamente.',
            'detail'  => new OrderDetailResource($detail),
        ]);
    }

    public function downloadPdf(string $id)
    {
        $order = Order::with([
            'client',
            'details.product.attributeValues',
            'details.product.variants.attributeValues',
            'details.variant.attributeValues',
            'payments',
        ])->find($id);

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $pdf = Pdf::loadView('orders.receipt', compact('order'));

        $fileName = 'comprobante-pedido-' . substr($order->id, 0, 8) . '-' . date('Y-m-d') . '.pdf';

        return response($pdf->stream(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Models\Order;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Muestra una lista de todos los pedidos.
     */
    public function index(Request $request)
    {
        $query = Order::with('client', 'payments');

        if ($request->has('status')) {
            $status = $request->input('status');
            $validStatuses = [
                'pending',
                'cancelled',
                'confirmed',
                'delivered',
                'shipped',
                'processing'
            ];

            if (!in_array($status, $validStatuses)) {
                return response()->json(['error' => 'Estado de pedido inválido.'], 400);
            }
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(10);

        // $ordersData = $orders['data'];
        foreach ($orders as $order) {
            $order->balance_due = $this->orderService->getPendingBalance($order);
        }

        return response()->json($orders);
    }

    /**
     * Crea un nuevo pedido.
     */
    public function store(Request $request)
    {

        $rules = [
            'client_id' => 'nullable|uuid|exists:clients,id',
            'shipping_address' => 'nullable|string',
            'price_list_id' => 'required|integer|exists:price_lists,id',
        ];

        $params = [
            'client_id.uuid' => 'El identificador del cliente no tiene un formato válido (UUID).',
            'client_id.exists' => 'El cliente especificado no existe en la base de datos.',

            'price_list_id.exists' => 'La lista de precios especificada no existe en la base de datos.',
            'price_list_id.required' => 'La lista de precios es obligatoria.',

            'shipping_address.string' => 'La dirección de envío debe ser texto.',
        ];

        // 1. VALIDACIÓN

        try {
            $validated = $request->validate($rules, $params);
            // 2. LÓGICA DE NEGOCIO (delegada al Servicio)
            $order = $this->orderService->createOrder($validated);

            // 3. RESPUESTA
            return response()->json([
                'message' => 'Pedido creado exitosamente.',
                'order' => $order
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Muestra los detalles de un pedido específico.
     */
    public function show(string $id)
    {
        $order = Order::with('client', 'details.product.images', 'payments')->find($id);

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Añadir saldo pendiente a la respuesta usando el servicio
        $order->balance_due = $this->orderService->getPendingBalance($order);

        return response()->json($order);
    }

    /**
     * Añade un item (producto) a un pedido existente.
     */
    public function addProduct(Request $request, string $orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $rules = [
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
        ];

        $params = [
            'product_id.required' => 'Debes especificar el producto que se está comprando.',
            'product_id.uuid' => 'El identificador del producto no tiene un formato válido (UUID).',
            'product_id.exists' => 'El producto especificado no existe en la base de datos.',

            'quantity.required' => 'Debes indicar la cantidad de unidades del producto.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima a ingresar es :min unidad.',

            'unit_price.required' => 'Debes ingresar el precio de venta unitario.',
            'unit_price.numeric' => 'El precio de venta debe ser un valor numérico.',
            'unit_price.min' => 'El precio de venta no puede ser negativo (mínimo :min).',

            'purchase_price.required' => 'Debes ingresar el precio de compra o costo unitario.',
            'purchase_price.numeric' => 'El precio de compra debe ser un valor numérico.',
            'purchase_price.min' => 'El precio de compra no puede ser negativo (mínimo :min).',
        ];

        // 1. VALIDACIÓN de los datos del ítem

        try {
            $request->validate($rules, $params);
            // 2. LÓGICA DE NEGOCIO: Añadir item
            $detail = $this->orderService->addProductToOrder($order, $request->all());

            // 3. RESPUESTA
            return response()->json([
                'message' => 'Producto agregado. Totales actualizados.',
                'detail' => $detail->load('product'),
                'order_totals' => $order->fresh()
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un item (detalle) de un pedido existente.
     */
    public function removeProduct(string $orderDetailId)
    {
        $detail = OrderDetail::find($orderDetailId);
        if (!$detail) {
            return response()->json(['error' => 'Línea de pedido no encontrada.'], 404);
        }

        try {
            // 2. LÓGICA DE NEGOCIO: Eliminar item
            $orderId = $detail->order_id;
            $this->orderService->removeProductFromOrder($detail);

            // 3. RESPUESTA
            return response()->json([
                'message' => 'Producto eliminado. Stock y totales actualizados.',
                'order_totals' => Order::find($orderId), // Devuelve la orden con los totales recalculados
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    /**
     * Actualiza la cabecera de un pedido existente (cliente, descuentos, estado).
     * @param Request $request
     * @param string $id El UUID del pedido.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $rules = [
            'client_id' => 'nullable|uuid|exists:clients,id',
            'status' => 'nullable|in:pending,confirmed,shipped,completed,canceled',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_fixed_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
        ];

        $params = [
            'client_id.uuid' => 'El identificador del cliente no tiene un formato válido (UUID).',
            'client_id.exists' => 'El cliente especificado no existe en la base de datos.',

            'status.in' => 'El estado de la orden no es válido. Los estados permitidos son: pendiente, confirmada, enviada, completada o cancelada.',

            'discount_percentage.numeric' => 'El porcentaje de descuento debe ser un valor numérico.',
            'discount_percentage.min' => 'El porcentaje de descuento mínimo debe ser :min.',
            'discount_percentage.max' => 'El porcentaje de descuento máximo permitido es :max.',

            'discount_fixed_amount.numeric' => 'El monto de descuento fijo debe ser un valor numérico.',
            'discount_fixed_amount.min' => 'El monto de descuento fijo no puede ser negativo (mínimo :min).',

            'shipping_cost.numeric' => 'El costo de envío debe ser un valor numérico.',
            'shipping_cost.min' => 'El costo de envío no puede ser negativo (mínimo :min).',
        ];


        try {
            $request->validate($rules, $params);
            // 2. LÓGICA DE NEGOCIO: Actualizar la cabecera
            $updatedOrder = $this->orderService->updateOrderHeader($order, $request->all());

            // 3. RESPUESTA
            return response()->json([
                'message' => 'Pedido actualizado exitosamente.',
                'order' => $updatedOrder->load('client')
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Modifica los datos de una línea de pedido (cantidad, precios, descuentos).
     */
    public function updateProduct(OrderDetail $detail, Request $request)
    {
        $rules = [
            'quantity' => 'nullable|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',

            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_fixed_amount' => 'nullable|numeric|min:0',

        ];

        $params = [
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima debe ser :min unidad.',

            'unit_price.numeric' => 'El precio de venta debe ser un valor numérico.',
            'unit_price.min' => 'El precio de venta no puede ser negativo (mínimo :min).',

            'discount_percentage.numeric' => 'El porcentaje de descuento debe ser un valor numérico.',
            'discount_percentage.min' => 'El porcentaje de descuento mínimo debe ser :min.',
            'discount_percentage.max' => 'El porcentaje de descuento máximo permitido es :max.',

            'discount_fixed_amount.numeric' => 'El monto de descuento fijo debe ser un valor numérico.',
            'discount_fixed_amount.min' => 'El monto de descuento fijo no puede ser negativo (mínimo :min).',
        ];

        try {
            $validated = $request->validate($rules, $params);


            $detail = $this->orderService->updateProductInOrder($detail, $validated);

            return response()->json([
                'message' => 'Línea de pedido actualizada exitosamente.',
                'detail' => $detail,
            ]);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Genera y descarga el PDF del comprobante de compra.
     * @param string $id El UUID del pedido.
     */
    public function downloadPdf(string $id)
    {
        try {
            // Cargar la orden con todas las relaciones necesarias
            $order = Order::with('client', 'details.product', 'payments')->find($id);

            if (!$order) {
                return response()->json(['error' => 'Pedido no encontrado.'], 404);
            }

            // Generar el PDF usando la vista Blade
            $pdf = Pdf::loadView('orders.receipt', compact('order'));

            // Configurar el nombre del archivo
            $fileName = 'comprobante-pedido-' . substr($order->id, 0, 8) . '-' . date('Y-m-d') . '.pdf';

            // Devolver el PDF para descarga
            return response($pdf->stream(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}

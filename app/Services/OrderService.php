<?php

// app/Services/OrderService.php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Summary of createOrder
     * @param array $data
     * @return Order
     */
    public function createOrder(array $data): Order
    {
        return Order::create([
            ...$data,
            'status' => OrderStatus::Processing,
            'total_amount' => 0.00,
            'final_total_amount' => 0.00,
        ]);
    }

    /**
     * Añade un producto al pedido, actualiza el stock y recalcula totales.
     * @param Order $order El pedido.
     * @param array $item Los detalles del producto a añadir.
     * @return OrderDetail
     */
    public function addProductToOrder(Order $order, array $item): OrderDetail
    {
        return DB::transaction(function () use ($order, $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                throw new \Exception("Producto no encontrado.");
            }

            if ($order->status != 'processing') {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no esta en preparación, no se pueden agregar más productos."],
                ]);
            }

            // --- BÚSQUEDA DEL DETALLE EXISTENTE ---
            // Intentamos encontrar un detalle para este producto en la orden
            $detail = $order->details()->where('product_id', $product->id)->first();

            // 1. Validar Stock (ahora se valida el stock restante después de la potencial suma)
            // La nueva cantidad será la cantidad actual del item + la cantidad que ya existe en el detalle (si existe)
            $quantityToAdd = $item['quantity'];
            $currentDetailQuantity = $detail ? $detail->quantity : 0;
            $newTotalQuantity = $currentDetailQuantity + $quantityToAdd;

            if ($product->stock < $quantityToAdd) {
                // La validación de stock debe hacerse sobre la cantidad NUEVA que se intenta agregar
                throw ValidationException::withMessages([
                    'quantity' => ["Stock insuficiente. Disponible: {$product->stock}."],
                ]);
            }

            // --- 2. CREAR/ACTUALIZAR DETALLE ---
            if ($detail) {
                // El producto ya existe, solo sumamos la cantidad
                $detail->quantity = $newTotalQuantity;

                // Si el precio unitario y los descuentos vienen en el $item,
                // generalmente se deberían actualizar/sobrescribir si el usuario lo indica, 
                // o podrías optar por mantener los precios del detalle original.
                // Para mantener la lógica simple y similar a tu código:
                $detail->unit_price = $item['unit_price'];
                $detail->purchase_price = $item['purchase_price'];
                $detail->discount_percentage = $item['discount_percentage'] ?? 0.00;
                $detail->discount_fixed_amount = $item['discount_fixed_amount'] ?? 0.00;
            } else {
                // El producto NO existe, creamos un nuevo detalle
                $detail = $order->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantityToAdd,
                    'purchase_price' => $item['purchase_price'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0.00,
                    'discount_fixed_amount' => $item['discount_fixed_amount'] ?? 0.00,
                    // Los campos de subtotal se calcularán y guardarán más abajo
                    'subtotal' => 0,
                    'subtotal_with_discount' => 0,
                ]);
            }

            // --- 3. CALCULAR SUBTOTAL DE LÍNEA (Usando los valores FINALES del detalle) ---
            // Se recalcula usando la nueva cantidad total ($detail->quantity) y los precios/descuentos aplicados
            $subtotal = $detail->quantity * $detail->unit_price;
            $subtotalWithDiscount = $subtotal - $detail->discount_fixed_amount;
            $subtotalWithDiscount *= (1 - ($detail->discount_percentage / 100));

            $detail->subtotal = $subtotal;
            $detail->subtotal_with_discount = $subtotalWithDiscount;
            $detail->save(); // Guardar el detalle (actualizado o recién creado)

            // --- 4. AJUSTAR STOCK ---
            // Solo restamos la cantidad que SE AGREGÓ AHORA ($quantityToAdd), 
            // ya que el stock de las unidades que ya estaban en el pedido ya fue restado antes.
            $product->stock -= $quantityToAdd;
            $product->save();

            // --- 5. RECALCULAR TOTALES DEL PEDIDO ---
            $this->calculateOrderTotals($order);

            return $detail;
        });
    }

    /**
     * Elimina un producto del pedido, devuelve el stock y recalcula totales.
     * @param OrderDetail $detail El detalle de la línea a eliminar.
     * @return bool
     */
    public function removeProductFromOrder(OrderDetail $detail): bool
    {
        return DB::transaction(function () use ($detail) {
            $order = $detail->order;
            $product = $detail->product;

            if ($order->status != 'processing') {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no esta en preparación, no se pueden agregar más productos."],
                ]);
            }
            // 1. DEVOLVER STOCK
            if ($product) {
                $product->stock += $detail->quantity;
                $product->save();
            }

            // 2. ELIMINAR DETALLE
            $detail->delete();

            // 3. RECALCULAR TOTALES DEL PEDIDO
            $this->calculateOrderTotals($order);

            return true;
        });
    }


    /**
     * Actualiza la cabecera de la orden con cliente, estado y descuentos.
     * Luego recalcula los totales finales.
     * @param Order $order El pedido a actualizar.
     * @param array $data Los datos a actualizar (client_id, order_status, discount_percentage, etc.).
     * @return Order
     */
    public function updateOrderHeader(Order $order, array $data): Order
    {
        // Campos que se pueden actualizar:
        $updatableFields = [
            'client_id',
            'status',
            'shipping_address',
            'notes',
            'discount_percentage',
            'discount_fixed_amount',
            'shipping_cost',
        ];

        // Filtrar solo los datos que corresponden a los campos actualizables
        $updateData = collect($data)->only($updatableFields)->filter(function ($value) {
            // Asegura que solo se incluyan valores no nulos o vacíos si son enviados
            return !is_null($value);
        })->all();

        // Aplicar la actualización
        $order->update($updateData);

        // Recalcular los totales finales (es crucial si se cambian descuentos o costos de envío)
        $this->calculateOrderTotals($order);

        return $order->fresh(); // Devolver el modelo recién actualizado
    }

    /**
     * Modifica la cantidad, precios o descuentos de un producto en la línea de pedido.
     * Devuelve o resta la diferencia de stock y recalcula totales.
     * * @param OrderDetail $detail El detalle de la línea a modificar.
     * @param array $data Los datos a actualizar (quantity, unit_price, discount_percentage, etc.).
     * @return OrderDetail
     */
    public function updateProductInOrder(OrderDetail $detail, array $data): OrderDetail
    {
        return DB::transaction(function () use ($detail, $data) {
            $order = $detail->order;
            $product = $detail->product;

            // 1. VALIDAR ESTADO DEL PEDIDO
            if ($order->status != 'processing') {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no está en preparación, no se pueden modificar productos."],
                ]);
            }

            // 2. PREPARAR DATOS Y VALIDAR STOCK
            $currentQuantity = $detail->quantity;
            $newQuantity = $data['quantity'] ?? $currentQuantity;
            $quantityDifference = $newQuantity - $currentQuantity; // Positivo si se suma, negativo si se resta

            if ($quantityDifference > 0) {
                // Si se está aumentando la cantidad, validar el stock disponible para la diferencia
                if ($product->stock < $quantityDifference) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Stock insuficiente para aumentar la cantidad. Disponible: {$product->stock}."],
                    ]);
                }
            }

            // 3. APLICAR ACTUALIZACIONES AL DETALLE

            // Campos que se pueden actualizar en el detalle:
            $updatableDetailFields = [
                'quantity',
                'unit_price',
                'purchase_price',
                'discount_percentage',
                'discount_fixed_amount'
            ];

            // Se usa `forceFill` ya que estamos en el Service y manejamos la lógica.
            // También puedes usar el método `update` con los campos filtrados.
            $updateDetailData = collect($data)->only($updatableDetailFields)->all();

            // 4. ACTUALIZAR DETALLE
            $detail->update($updateDetailData);
            $detail->refresh(); // Asegurarse de tener la nueva cantidad para el cálculo

            // 5. AJUSTAR STOCK
            if ($product) {
                $product->stock -= $quantityDifference; // Resta la diferencia (si es positiva) o suma (si es negativa)
                $product->save();
            }

            // 6. CALCULAR Y ACTUALIZAR SUBTOTAL DE LÍNEA (lógica ya existente)
            $subtotal = $detail->quantity * $detail->unit_price;
            $subtotalWithDiscount = $subtotal - $detail->discount_fixed_amount;
            $subtotalWithDiscount *= (1 - ($detail->discount_percentage / 100));

            $detail->subtotal = $subtotal;
            $detail->subtotal_with_discount = $subtotalWithDiscount;
            $detail->save();

            // 7. RECALCULAR TOTALES DEL PEDIDO
            $this->calculateOrderTotals($order);

            return $detail;
        });
    }


    /**
     * Obtiene el monto total pagado de un pedido.
     * @param Order $order
     * @return float
     */
    public function getPaidAmount(Order $order): float
    {
        return (float) $order->payments()
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');
    }

    /**
     * Obtiene el saldo pendiente de un pedido.
     * @param Order $order
     * @return float
     */
    public function getPendingBalance(Order $order): float
    {
        $paid = $this->getPaidAmount($order);
        $total = (float) $order->final_total_amount;

        return max(0, $total - $paid);
    }

    /**
     * Recalcula y actualiza todos los montos de la orden (total_amount, final_total_amount).
     * @param Order $order
     */
    public function calculateOrderTotals(Order $order): void
    {
        $order->refresh(); // Asegura que tenemos los últimos detalles

        // 1. CALCULAR MONTO TOTAL BRUTO (suma de subtotales de las líneas)
        $totalAmount = $order->details()->sum('subtotal_with_discount');
        $finalTotal = $totalAmount;

        // 2. APLICAR DESCUENTOS Y GASTOS GLOBALES
        $finalTotal -= $order->discount_fixed_amount;
        $finalTotal *= (1 - ($order->discount_percentage / 100));
        $finalTotal += $order->shipping_cost;

        // 3. ACTUALIZAR LA ORDEN
        $order->update([
            'total_amount' => $totalAmount,
            'final_total_amount' => max(0, $finalTotal),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\OrderPendingMail;
use App\Services\WholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WholesaleController
{
    public function __construct(private readonly WholesaleService $wholesaleService) {}

    public function checkout(Request $request)
    {
        $rules = [
            'name'                        => 'required|string|max:255',
            'email'                       => 'required|email|max:255',
            'phone'                       => 'required|string|max:50',
            'delivery_method'                    => 'required|in:shipping,whatsapp',
            'shipping_address'                   => 'required_if:delivery_method,shipping|nullable|array',
            'shipping_address.street'            => 'required_if:delivery_method,shipping|nullable|string|max:255',
            'shipping_address.street_number'     => 'required_if:delivery_method,shipping|nullable|string|max:20',
            'shipping_address.floor'             => 'nullable|string|max:10',
            'shipping_address.apartment'         => 'nullable|string|max:10',
            'shipping_address.locality'          => 'required_if:delivery_method,shipping|nullable|string|max:255',
            'shipping_address.province'          => 'required_if:delivery_method,shipping|nullable|string|max:100',
            'shipping_address.postal_code'       => 'required_if:delivery_method,shipping|nullable|string|max:10',
            'notes'                       => 'nullable|string|max:1000',
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|string',
            'items.*.variant_id'          => 'nullable|integer',
            'items.*.quantity'            => 'required|integer|min:1',
        ];

        $messages = [
            // Campos principales
            'name.required'             => 'El nombre es obligatorio.',
            'name.max'                  => 'El nombre no puede exceder los 255 caracteres.',
            'email.required'            => 'El correo electrónico es obligatorio.',
            'email.email'               => 'El formato del correo no es válido.',
            'phone.required'            => 'El teléfono es obligatorio.',
            'delivery_method.required'  => 'Debe seleccionar un método de entrega.',
            'delivery_method.in'        => 'El método de entrega seleccionado no es válido.',

            // Dirección de envío (condicionales)
            'shipping_address.required_if'          => 'La dirección de envío es obligatoria.',
            'shipping_address.street.required_if'   => 'La calle es obligatoria.',
            'shipping_address.street_number.required_if' => 'El número de calle es obligatorio.',
            'shipping_address.locality.required_if' => 'La localidad es obligatoria.',
            'shipping_address.province.required_if' => 'La provincia es obligatoria.',
            'shipping_address.postal_code.required_if' => 'El código postal es obligatorio.',

            // Validaciones de longitud para dirección
            'shipping_address.*.max'    => 'Uno de los campos de la dirección excede el límite de caracteres permitido.',

            // Items
            'items.required'            => 'Debes agregar al menos un producto a la orden.',
            'items.min'                 => 'La orden debe contener como mínimo :min ítem.',
            'items.*.product_id.required' => 'El ID del producto es obligatorio.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.integer'  => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min'      => 'La cantidad mínima por producto debe ser :min.',
        ];

        $data = $request->validate($rules, $messages);

        $resolved = $this->wholesaleService->resolveItems($data['items']);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        try {
            $order = $this->wholesaleService->createOrder($data, $resolved);

            try {
                Mail::to(config('mail.order_notify'))
                    ->send(new OrderPendingMail($order->load('client', 'details.product', 'details.variant')));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el aviso de pedido: ' . $e->getMessage());
            }

            return response()->json([
                'order_id' => $order->id,
                'message'  => 'Pedido recibido',
            ], 201);
        } catch (\App\Exceptions\InsufficientStockException $e) {
            return response()->json([
                'message'      => 'Algunos productos se quedaron sin stock mientras finalizabas la compra.',
                'stock_errors' => $e->errors,
            ], 409);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

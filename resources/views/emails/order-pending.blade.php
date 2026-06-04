@php
    $shortId = substr($order->id, 0, 8);
    $deliveryLabel = ($order->delivery_method ?? 'shipping') === 'whatsapp'
        ? 'Coordina por WhatsApp'
        : 'Envío a domicilio';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo pedido pendiente</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, Helvetica, sans-serif; color:#222;">
    <div style="max-width:600px; margin:0 auto; padding:24px;">
        <div style="background:#fff; border:1px solid #e5e5e5; border-radius:8px; padding:24px;">
            <h1 style="font-size:18px; margin:0 0 4px;">Nuevo pedido pendiente</h1>
            <p style="margin:0 0 16px; color:#666; font-size:14px;">Pedido #{{ $shortId }}</p>

            <h2 style="font-size:14px; margin:16px 0 6px;">Cliente</h2>
            <p style="margin:0; font-size:14px; line-height:1.5;">
                <strong>{{ $order->client->name ?? 'Sin nombre' }}</strong><br>
                {{ $order->client->email ?? '-' }}<br>
                Tel: {{ $order->client->phone ?? '-' }}
            </p>

            <p style="margin:12px 0 0; font-size:14px;">
                <strong>Entrega:</strong> {{ $deliveryLabel }}
            </p>

            @if($order->shipping_address)
                @php $addr = $order->shipping_address; @endphp
                <p style="margin:6px 0 0; font-size:14px; line-height:1.5;">
                    {{ $addr['street'] ?? '' }} {{ $addr['street_number'] ?? '' }},
                    {{ $addr['locality'] ?? '' }}, {{ $addr['province'] ?? '' }}
                    (CP: {{ $addr['postal_code'] ?? '-' }})
                </p>
            @endif

            <h2 style="font-size:14px; margin:20px 0 6px;">Productos</h2>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                        <th style="padding:6px 4px;">Producto</th>
                        <th style="padding:6px 4px; text-align:center;">Cant.</th>
                        <th style="padding:6px 4px; text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->details as $detail)
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:6px 4px;">
                                {{ $detail->product->name ?? 'Producto' }}
                                @if($detail->variant?->sku)
                                    <span style="color:#888;"> · {{ $detail->variant->sku }}</span>
                                @endif
                            </td>
                            <td style="padding:6px 4px; text-align:center;">{{ $detail->quantity }}</td>
                            <td style="padding:6px 4px; text-align:right;">
                                ${{ number_format($detail->subtotal_with_discount ?? $detail->subtotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin:18px 0 0; font-size:16px; text-align:right;">
                <strong>Total: ${{ number_format($order->final_total_amount, 2, ',', '.') }}</strong>
            </p>

            @if($order->notes)
                <p style="margin:16px 0 0; font-size:13px; color:#555;">
                    <strong>Notas:</strong> {{ $order->notes }}
                </p>
            @endif

            <div style="margin:24px 0 0; padding:10px 14px; background:#fff7e6; border:1px solid #ffe0a3; border-radius:6px; font-size:13px; color:#A16207; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <span>Este pedido está <strong>pendiente</strong> de revisión en el panel.</span>
                <a href="https://admin.concoypunto.com/ventas/{{ $order->id }}"
                   style="display:inline-block; padding:7px 14px; background:#A16207; color:#fff; text-decoration:none; border-radius:5px; font-size:13px; font-weight:bold; white-space:nowrap;">
                    Ver pedido →
                </a>
            </div>
        </div>
    </div>
</body>
</html>

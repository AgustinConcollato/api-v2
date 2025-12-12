<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #{{ substr($order->id, 0, 8) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 10px;
        }

        .header h1 {
            color: #000;
            font-size: 18px;
        }

        .subtitle {
            color: #7f8c8d;
            font-size: 14px;
        }

        .info-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            width: 97%;
        }


        .info-box p {
            color: #555;
        }

        .order-details {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }

        table thead {
            background-color: #2c3e50;
            color: white;
        }

        table th {
            padding: 7px 12px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
        }

        table td {
            padding: 5px;
            border-bottom: 1px solid #dee2e6;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            margin-top: 20px;
            margin-left: auto;
            width: 350px;
        }

        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .totals-label {
            display: table-cell;
            text-align: right;
            padding-right: 15px;
            color: #555;
            width: 60%;
        }

        .totals-value {
            display: table-cell;
            text-align: right;
            color: #000;
            width: 40%;
        }

        .total-final {
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 16px;
        }

        .total-final .totals-label,
        .total-final .totals-value {
            color: #000;
            font-size: 16px;
        }

        .notes-section {
            margin-top: 5px;
        }

        .notes-section h3 {
            font-size: 14px;
            margin-bottom: 1px;
        }

        .notes-section p {
            font-size: 12px;
        }

        .footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Detalle del pedido</h1>
            <div class="subtitle">Pedido #{{ substr($order->id, 0, 8) }} | Fecha: {{ $order->created_at->format('d/m/Y') }}</div>
        </div>

        <!-- Información del Cliente y Pedido -->
        <div class="info-section">
            <div class="info-box">
                @if($order->client)
                <p>Nombre: {{ $order->client->name }}</p>
                <p>Email:
                    {{
                        ($order->client->email && !str_starts_with($order->client->email, 'test')) 
                        ? $order->client->email 
                        : '-' 
                    }}
                </p>
                <p>Teléfono: {{ $order->client->phone ?? '-' }}</p>
                @if($order->shipping_address)
                <p>Dirección de envío: {{ $order->shipping_address }}</p>
                @endif
                @else
                <p>Cliente no asignado</p>
                @endif
            </div>
            @if($order->notes)
            <div class="notes-section">
                <h3>NOTAS ADICIONALES</h3>
                <p>{{ $order->notes }}</p>
            </div>
            @endif
        </div>

        <!-- Notas -->

        <!-- Detalles de Productos -->
        <div class="order-details">
            <table>
                <thead>
                    <tr>
                        <th style="width: 35%;">Producto</th>
                        <th style="width: 10%;" class="text-center">Cantidad</th>
                        <th style="width: 15%;" class="text-right">Precio Unit.</th>
                        <th style="width: 10%;" class="text-right">Desc.</th>
                        <th style="width: 15%;" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 
                        Lógica de paginación: 
                        Iteramos los detalles. Si el índice actual (base 0) + 1 es un múltiplo de 22, 
                        insertamos un salto de página justo después de la fila, siempre y cuando no sea 
                        la última fila.
                    --}}
                    @foreach($order->details as $index => $detail)
                    <tr>
                        <td>
                            {{ $detail->product->name ?? 'Producto eliminado' }}
                        </td>
                        <td class="text-center">{{ $detail->quantity }}</td>
                        <td class="text-right">${{ number_format($detail->unit_price, 2, ',', '.') }}</td>
                        <td class="text-right">
                            @if($detail->discount_percentage > 0 || $detail->discount_fixed_amount > 0)
                            @if($detail->discount_percentage > 0)
                            {{ number_format($detail->discount_percentage, 2) }}%
                            @endif
                            @if($detail->discount_fixed_amount > 0)
                            ${{ number_format($detail->discount_fixed_amount, 2, ',', '.') }}
                            @endif
                            @else
                            -
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($detail->subtotal_with_discount, 2, ',', '.') }}</td>
                    </tr>
                    
                    {{-- Comprobación de salto de página cada 22 productos --}}
                    @if (($index + 1) % 22 === 0 && ($index + 1) < count($order->details))
                </tbody>
            </table>
            {{-- Inserta el salto de página --}}
            <div class="page-break"></div> 
            
            {{-- Vuelve a abrir la tabla y el tbody para la siguiente página --}}
            <table>
                <thead>
                    <tr>
                        <th style="width: 35%;">Producto</th>
                        <th style="width: 10%;" class="text-center">Cantidad</th>
                        <th style="width: 15%;" class="text-right">Precio Unit.</th>
                        <th style="width: 10%;" class="text-right">Desc.</th>
                        <th style="width: 15%;" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="totals-section">
            <div class="totals-row">
                <div class="totals-label">Subtotal:</div>
                <div class="totals-value">${{ number_format($order->total_amount, 2, ',', '.') }}</div>
            </div>

            @if($order->discount_fixed_amount > 0)
            <div class="totals-row">
                <div class="totals-label">Descuento Fijo:</div>
                <div class="totals-value">-${{ number_format($order->discount_fixed_amount, 2, ',', '.') }}</div>
            </div>
            @endif

            @if($order->discount_percentage > 0)
            <div class="totals-row">
                <div class="totals-label">Descuento ({{ number_format($order->discount_percentage, 2) }}%):</div>
                <div class="totals-value">
                    -${{ number_format($order->total_amount * ($order->discount_percentage / 100), 2, ',', '.') }}
                </div>
            </div>
            @endif

            @if($order->shipping_cost > 0)
            <div class="totals-row">
                <div class="totals-label">Costo de Envío:</div>
                <div class="totals-value">${{ number_format($order->shipping_cost, 2, ',', '.') }}</div>
            </div>
            @endif

            <div class="totals-row total-final">
                <div class="totals-label">TOTAL:</div>
                <div class="totals-value">${{ number_format($order->final_total_amount, 2, ',', '.') }}</div>
            </div>
        </div>
        <!-- Footer -->
        <!-- <div class="footer">
            <p>Este es un comprobante generado automáticamente el {{ now()->format('d/m/Y') }}</p>
            <p>Gracias por su compra</p>
        </div> -->
    </div>
</body>

</html>
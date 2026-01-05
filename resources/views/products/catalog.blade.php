<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Productos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header .subtitle {
            color: #7f8c8d;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table thead {
            background-color: #2c3e50;
            color: white;
        }

        table th {
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            border: 1px solid #34495e;
        }

        table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        table tbody tr:hover {
            background-color: #e9ecef;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 3px;
        }

        .product-name {
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
            line-height: 1.3;
        }

        .product-sku {
            font-size: 10px;
            color: #7f8c8d;
        }

        .product-price {
            font-size: 13px;
            font-weight: bold;
            color: #333;
            text-align: center;
        }

        .product-stock {
            font-size: 10px;
            color: #555;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #7f8c8d;
            font-size: 9px;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Catálogo de Productos</h1>
            <div class="subtitle">Productos con stock disponible | Generado el {{ $generatedAt->format('d/m/Y') }}</div>
        </div>

        <!-- Productos -->
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;" class="text-center">Imagen</th>
                    <th style="width: 35%;">Producto</th>
                    <th style="width: 15%;">SKU</th>
                    <th style="width: 15%;" class="text-center">Precio</th>
                    <th style="width: 20%;" class="text-center">Stock</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $index => $product)
                    @php
                        $image = $product->images->first();
                        $priceList = $product->priceLists->first();
                        $price = $priceList ? $priceList->pivot->price : 0;
                    @endphp

                    <tr>
                        <td class="text-center">
                            @if($image)
                                <img 
                                    src="{{ storage_path('app/public/' . $image->thumbnail_path) }}" 
                                    alt="{{ $product->name }}"
                                    class="product-image"
                                />
                            @else
                                <span style="color: #7f8c8d; font-size: 9px;">Sin imagen</span>
                            @endif
                        </td>
                        <td>
                            <div class="product-name">{{ $product->name }}</div>
                        </td>
                        <td>
                            <div class="product-sku">{{ $product->sku }}</div>
                        </td>
                        <td class="text-right">
                            <div class="product-price">${{ number_format($price, 2, ',', '.') }}</div>
                        </td>
                        <td class="text-center">
                            <div class="product-stock">{{ $product->stock }} unidades</div>
                        </td>
                    </tr>

                    {{-- Salto de página cada 25 productos --}}
                    @if (($index + 1) % 9 == 0 && ($index + 1) < count($products))
            </tbody>
        </table>
        <div class="page-break"></div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;" class="text-center">Imagen</th>
                    <th style="width: 35%;">Producto</th>
                    <th style="width: 15%;">SKU</th>
                    <th style="width: 15%;" class="text-right">Precio</th>
                    <th style="width: 20%;" class="text-center">Stock</th>
                </tr>
            </thead>
            <tbody>
                    @endif
                @endforeach
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>Total de productos: {{ count($products) }}</p>
            <p>Este catálogo fue generado automáticamente el {{ $generatedAt->format('d/m/Y') }}</p>
        </div>
    </div>
</body>

</html>

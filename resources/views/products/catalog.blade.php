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
            width: auto;
            height: 80px;
            object-fit: contain;
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

        .category-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .category-header {
            padding: 12px 0;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 0;
            border-radius: 3px 3px 0 0;
        }

        .category-header h2 {
            margin: 0;
            font-size: 16px;
        }

        .index-section {
            margin-bottom: 30px;
            page-break-after: always;
        }

        .index-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
        }

        .index-list {
            list-style: none;
            padding: 0;
        }

        .index-item {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
        }

        .index-item:last-child {
            border-bottom: none;
        }

        .index-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        .index-link:hover {
            background-color: #f8f9fa;
        }

        .index-category-name {
            font-size: 12px;
            color: #2c3e50;
        }

        .index-page-number {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: bold;
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

            .category-section {
                page-break-inside: avoid;
            }

            .category-header {
                page-break-after: avoid;
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

        <!-- Índice de Categorías -->
        @php
        $totalProducts = 0;
        $productCounter = 0;
        $currentPage = 2; // Empezamos en página 2 porque la 1 es el índice
        $productsPerPage = 9;
        $categoryPages = [];
        
        // Calcular en qué página empieza cada categoría
        // Cada categoría empieza en una nueva página
        foreach($products as $categoryGroup) {
            $categoryProducts = $categoryGroup['products'];
            $categoryPages[$categoryGroup['category']] = $currentPage;
            // Cada categoría ocupa al menos 1 página, más las páginas adicionales si tiene más productos
            $productsInCategory = $categoryProducts->count();
            $pagesForCategory = max(1, ceil($productsInCategory / $productsPerPage));
            $currentPage += $pagesForCategory;
        }
        @endphp

        <div class="index-section">
            <div class="index-title">Índice de Categorías</div>
            <ul class="index-list">
                @foreach($products as $categoryGroup)
                @php
                    $categorySlug = strtolower(str_replace([' ', '/', '\\'], '-', $categoryGroup['category']));
                    $categorySlug = preg_replace('/[^a-z0-9\-]/', '', $categorySlug);
                @endphp
                <li class="index-item">
                    <a href="#category-{{ $categorySlug }}" class="index-link">
                        <span class="index-category-name">{{ $categoryGroup['category'] }} ({{ $categoryGroup['products']->count() }} productos)</span>
                        <span class="index-page-number">Página {{ $categoryPages[$categoryGroup['category']] }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>

        <!-- Productos agrupados por categoría -->
        @php
        $productCounter = 0;
        $isFirstCategory = true;
        @endphp

        @foreach($products as $categoryGroup)
        @php
        $categoryName = $categoryGroup['category'];
        $categoryProducts = $categoryGroup['products'];
        $totalProducts += $categoryProducts->count();
        $rowCounter = 0; // Reiniciar contador para cada categoría
        @endphp

        {{-- Salto de página antes de cada categoría (excepto la primera) --}}
        @if (!$isFirstCategory)
        <div class="page-break"></div>
        @endif
        @php 
        $isFirstCategory = false;
        $categorySlug = strtolower(str_replace([' ', '/', '\\'], '-', $categoryName));
        $categorySlug = preg_replace('/[^a-z0-9\-]/', '', $categorySlug);
        @endphp

        <div class="category-section" id="category-{{ $categorySlug }}">
            <div class="category-header">
                <h2>{{ $categoryName }} ({{ $categoryProducts->count() }} productos)</h2>
            </div>

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
                    @foreach($categoryProducts as $product)
                    @php
                    $image = $product->images->first();
                    $priceList = $product->priceLists->first();
                    $price = $priceList ? $priceList->pivot->price : 0;
                    $productCounter++;
                    $rowCounter++;
                    @endphp

                    <tr>
                        <td class="text-center">
                            @if($image)
                            <img
                                src="{{ storage_path('app/public/' . $image->thumbnail_path) }}"
                                alt="{{ $product->name }}"
                                class="product-image" />
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

                    {{-- Salto de página cada X productos (9 productos por página) --}}
                    @if ($rowCounter % $productsPerPage == 0 && $rowCounter < $categoryProducts->count())
                </tbody>
            </table>
        </div>
        <div class="page-break"></div>
        <div class="category-section">
            <div class="category-header">
                <h2>{{ $categoryName }}</h2>
            </div>
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
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        <!-- Footer -->
        <div class="footer">
            <p>Total de productos: {{ $totalProducts }}</p>
            <p>Total de categorías: {{ count($products) }}</p>
            <p>Este catálogo fue generado automáticamente el {{ $generatedAt->format('d/m/Y') }}</p>
        </div>
    </div>
</body>

</html>
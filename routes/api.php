<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/authentication', [UserController::class, 'auth']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'createProduct']);
        Route::post('/add-prices/{product}', [ProductController::class, 'addPrices']);
        Route::post('/{product}/barcode', [ProductController::class, 'storeBarcode']);
        Route::post('/{product}/images', [ProductController::class, 'addImages']);

        // Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::get('/barcode/{barcode}', [ProductController::class, 'getByBarcode']);

        Route::delete('/barcodes/{barcodeId}', [ProductController::class, 'destroyBarcode']);
        Route::delete('/{product}/images/delete', [ProductController::class, 'deleteImages']);

        Route::put('/{product}/categories', [ProductController::class, 'syncCategories']);
        Route::put('/{product}/prices', [ProductController::class, 'updatePrices']);
        Route::put('/{product}/images/reorder', [ProductController::class, 'reorderImages']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::put('/{product}/suppliers-prices', [ProductController::class, 'updateProductSuppliersPrices']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    Route::prefix('suppliers')->group(function () {
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::put('/{supplier}', [SupplierController::class, 'update']);
    });

    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::get('/{client}', [ClientController::class, 'show']);
        Route::post('/', [ClientController::class, 'create']);
        Route::put('/{client}', [ClientController::class, 'edit']);
        Route::delete('/{client}', [ClientController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']); // Listar todos los pedidos
        Route::post('/', [OrderController::class, 'store']); // Crear la cabecera de un nuevo pedido (vacío)
        Route::get('/{id}', [OrderController::class, 'show']); // Mostrar un pedido específico
        Route::get('/{id}/pdf', [OrderController::class, 'downloadPdf']); // Descargar PDF del comprobante
        Route::put('/{id}', [OrderController::class, 'update']); // actualizar cabezera
        Route::post('/{orderId}/product', [OrderController::class, 'addProduct']); // Añadir un producto al pedido
        Route::delete('/product/{orderDetailId}', [OrderController::class, 'removeProduct']); // Eliminar un producto del pedido
        Route::put('/product/{detail}', [OrderController::class, 'updateProduct']);
    });

    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'store']); // Registrar un nuevo pago
        Route::post('/refund', [PaymentController::class, 'storeRefund']);
        Route::get('/{order}', [PaymentController::class, 'paymentsByOrder']);
    });

    Route::get('/price-lists', [PriceListController::class, 'index']);
});

// rutas públicas 
Route::post('/login', [UserController::class, 'login']);
Route::get('/catalog/pdf/{priceListId}', [ProductController::class, 'generateCatalogPdf']);
Route::get('/products', [ProductController::class, 'index']);

Route::fallback(function () {
    return response()->json([
        'error' => 'Ruta no encontrada o no definida en la API.'
    ], 404);
});

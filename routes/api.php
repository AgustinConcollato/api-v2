<?php

use App\Http\Controllers\AccountMercadoPagoController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\CategoryAttributeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\GeminiAssistantController;
use App\Http\Controllers\MercadoLibreController;
use App\Http\Controllers\WholesaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/authentication', [UserController::class, 'auth']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::post('/add-prices/{product}', [ProductController::class, 'addPrices']);
        Route::post('/{product}/barcode', [ProductController::class, 'storeBarcode']);
        Route::post('/{product}/images', [ProductController::class, 'addImages']);

        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::get('/barcode/{barcode}', [ProductController::class, 'getByBarcode']);

        Route::delete('/barcodes/{barcodeId}', [ProductController::class, 'destroyBarcode']);
        Route::delete('/{product}/images/delete', [ProductController::class, 'deleteImages']);

        Route::put('/{product}/categories', [ProductController::class, 'syncCategories']);
        Route::put('/{product}/prices', [ProductController::class, 'updatePrices']);
        Route::put('/{product}/images/reorder', [ProductController::class, 'reorderImages']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::put('/{product}/suppliers-prices', [ProductController::class, 'updateProductSuppliersPrices']);
        Route::put('/{product}/status', [ProductController::class, 'updateStatus']);
        Route::put('/{product}/attribute-values', [ProductController::class, 'updateAttributeValues']);

        // Variantes
        Route::get('/variants/search', [ProductVariantController::class, 'search']);
        Route::get('/{product}/variants', [ProductVariantController::class, 'index']);
        Route::post('/{product}/variants', [ProductVariantController::class, 'store']);
        Route::post('/{product}/variants/{variant}/barcode', [ProductVariantController::class, 'storeBarcode']);
        Route::put('/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
        Route::delete('/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);

        // Atributos de categoría
        Route::get('/{category}/attributes', [CategoryAttributeController::class, 'index']);
        Route::post('/{category}/attributes', [CategoryAttributeController::class, 'store']);
        Route::put('/{category}/attributes/{attribute}', [CategoryAttributeController::class, 'update']);
        Route::delete('/{category}/attributes/{attribute}', [CategoryAttributeController::class, 'destroy']);
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
        Route::post('/', [ClientController::class, 'store']);
        Route::put('/{client}', [ClientController::class, 'update']);
        Route::delete('/{client}', [ClientController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']); // Listar todos los pedidos
        Route::post('/', [OrderController::class, 'store']); // Crear la cabecera de un nuevo pedido (vacío)
        Route::get('/pending-count', [OrderController::class, 'pendingCount']); // Contar pedidos pendientes
        Route::get('/{id}', [OrderController::class, 'show']); // Mostrar un pedido específico
        Route::get('/{id}/pdf', [OrderController::class, 'downloadPdf']); // Descargar PDF del comprobante
        Route::put('/{id}', [OrderController::class, 'update']); // actualizar cabezera
        Route::post('/{orderId}/product', [OrderController::class, 'addProduct']); // Añadir un producto al pedido
        Route::delete('/product/{orderDetailId}', [OrderController::class, 'removeProduct']); // Eliminar un producto del pedido
        Route::put('/product/{detail}', [OrderController::class, 'updateProduct']);
    });

    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']); // Registrar un nuevo pago
        Route::post('/refund', [PaymentController::class, 'storeRefund']);
        Route::get('/{order}', [PaymentController::class, 'paymentsByOrder']);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview']);
        Route::get('/compare-months', [AnalyticsController::class, 'compareMonths']);
    });

    Route::get('/price-lists', [PriceListController::class, 'index']);

    Route::prefix('promotions')->group(function () {
        Route::get('/', [PromotionController::class, 'index']);
        Route::post('/', [PromotionController::class, 'store']);
        Route::get('/{promotion}', [PromotionController::class, 'show']);
        Route::put('/{promotion}', [PromotionController::class, 'update']);
        Route::delete('/{promotion}', [PromotionController::class, 'destroy']);
        Route::put('/{promotion}/products', [PromotionController::class, 'syncProducts']);
        Route::put('/{promotion}/price-lists', [PromotionController::class, 'syncPriceLists']);
    });

    Route::prefix('/mercado-pago')->group(function () {
        Route::post('/get-token', [AccountMercadoPagoController::class, 'getToken']);
        Route::post('/skip-step', [AccountMercadoPagoController::class, 'skipStep']);
        Route::post('/revoke', [AccountMercadoPagoController::class, 'revoke']);
        Route::get('/profile', [AccountMercadoPagoController::class, 'getMercadoPagoProfile']);
    });

    Route::prefix('/mercado-libre')->group(function () {
        // Auth / vinculación
        Route::get('/auth-url', [MercadoLibreController::class, 'getAuthUrl']);
        Route::post('/callback', [MercadoLibreController::class, 'callback']);
        Route::post('/revoke', [MercadoLibreController::class, 'revoke']);
        Route::get('/profile', [MercadoLibreController::class, 'getProfile']);

        // Categorías
        Route::get('/categories/search', [MercadoLibreController::class, 'searchCategories']);
        Route::get('/categories/{categoryId}/attributes', [MercadoLibreController::class, 'getCategoryAttributes']);

        // Publicaciones
        Route::get('/publications', [MercadoLibreController::class, 'getPublications']);
        Route::post('/publications', [MercadoLibreController::class, 'publish']);
        Route::get('/publications/{mlItemId}', [MercadoLibreController::class, 'getPublication']);
        Route::put('/publications/{mlItemId}', [MercadoLibreController::class, 'updatePublication']);
        Route::post('/publications/{mlItemId}/listing-type', [MercadoLibreController::class, 'changeListingType']);
        Route::post('/publications/{mlItemId}/pause', [MercadoLibreController::class, 'pausePublication']);
        Route::post('/publications/{mlItemId}/reactivate', [MercadoLibreController::class, 'reactivatePublication']);
        Route::post('/publications/{mlItemId}/close', [MercadoLibreController::class, 'closePublication']);
        Route::post('/publications/{mlItemId}/variations', [MercadoLibreController::class, 'addVariation']);
        Route::delete('/publications/{mlItemId}/variations/{variationId}', [MercadoLibreController::class, 'deleteVariation']);
        Route::get('/publications/{mlItemId}/performance', [MercadoLibreController::class, 'getPublicationPerformance']);
        Route::post('/pictures/upload', [MercadoLibreController::class, 'uploadPicture']);
        Route::put('/publications/{mlItemId}/pictures', [MercadoLibreController::class, 'updatePublicationPictures']);
        
        // Comisiones / fees
        // GET /mercado-libre/listing-fees?category_id=MLA1055&listing_type_id=gold_special&price=15000
        Route::get('/listing-fees', [MercadoLibreController::class, 'getListingFees']);
        Route::get('/listing-types', [MercadoLibreController::class, 'getListingTypes']);

        // Envios
        Route::get('/shipping-preferences', [MercadoLibreController::class, 'getShippingPreferences']);
        Route::get('/shipping-cost', [MercadoLibreController::class, 'getShippingCost']);
    });
});

// Client auth — públicas
Route::post('/client/login', [ClientAuthController::class, 'login']);
Route::post('/client/register', [ClientAuthController::class, 'register']);
Route::post('/client/register-from-order', [ClientAuthController::class, 'registerFromOrder']);

// Client auth — protegidas
Route::middleware('auth:client')->group(function () {
    Route::get('/client/me', [ClientAuthController::class, 'me']);
    Route::put('/client/me', [ClientAuthController::class, 'updateProfile']);
    Route::post('/client/logout', [ClientAuthController::class, 'logout']);

    Route::get('/client/addresses', [AddressController::class, 'index']);
    Route::post('/client/addresses', [AddressController::class, 'store']);
    Route::put('/client/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/client/addresses/{address}', [AddressController::class, 'destroy']);
    Route::put('/client/addresses/{address}/default', [AddressController::class, 'setDefault']);
});

// rutas públicas
Route::post('/login', [UserController::class, 'login']);
Route::get('/catalog', [ProductController::class, 'publicIndex']);
Route::get('/catalog/{product}', [ProductController::class, 'publicShow']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/orders/wholesale', [WholesaleController::class, 'checkout']);

Route::fallback(function () {
    return response()->json([
        'error' => 'Ruta no encontrada o no definida en la API.'
    ], 404);
});

<?php

use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/{id}', function ($id) {
    $order = Order::with([
        'client',
        'details.product.attributeValues',
        'details.product.variants.attributeValues',
        'details.variant.attributeValues',
        'payments',
    ])->find($id);

    // return json_encode($order);
    return view('orders.receipt', compact('order'));
});

<?php

namespace App\Http\Controllers;

use App\Models\PriceList;

class PriceListController
{
    public function index()
    {
        try {
            $priceLists = PriceList::all();
            return response()->json($priceLists);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las listas de precios.', 'message' => $e->getMessage()], 500);
        }
    }
}

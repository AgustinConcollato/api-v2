<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API pública de magovirtual.com.ar (proveedor de dropshipping).
 * Solo lectura. Cualquier error de red/HTTP devuelve null (nunca tira excepción).
 */
class MagoVirtualClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.magovirtual.base_url'), '/');
    }

    /**
     * Stock de un producto por su id de magovirtual.
     * GET {base}/products/{id} -> { ..., "stock": N }
     */
    public function getStockById(int $id): ?int
    {
        try {
            $res = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$this->baseUrl}/products/{$id}");

            if (!$res->ok()) {
                return null;
            }

            $stock = $res->json('stock');

            return is_numeric($stock) ? (int) $stock : null;
        } catch (\Throwable $e) {
            Log::warning("MagoVirtual getStockById({$id}) falló: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Busca el id de magovirtual a partir de un código de barras.
     * quick-search es búsqueda parcial, por eso se exige match EXACTO de barcode.
     * GET {base}/products/quick-search?search_string={barcode}&page=1 -> [{ id, reference, barcode }]
     */
    public function findIdByBarcode(string $barcode): ?int
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        try {
            $res = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$this->baseUrl}/products/quick-search", [
                    'search_string' => $barcode,
                    'page'          => 1,
                ]);

            if (!$res->ok()) {
                return null;
            }

            foreach ($res->json() ?? [] as $item) {
                if (isset($item['barcode'], $item['id']) && (string) $item['barcode'] === $barcode) {
                    return (int) $item['id'];
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("MagoVirtual findIdByBarcode({$barcode}) falló: {$e->getMessage()}");
            return null;
        }
    }
}

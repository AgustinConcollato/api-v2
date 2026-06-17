<?php

namespace App\Services;

use App\Models\Product;

/**
 * Sincroniza la disponibilidad de los productos dropshipping contra el stock
 * del proveedor (magovirtual). Regla: stock proveedor <= umbral => no disponible
 * (stock 0); > umbral => disponible (stock 1).
 *
 * Para dropshipping, `stock` se usa como interruptor de disponibilidad (0/1),
 * tanto a nivel producto (simples) como por variante.
 */
class DropshippingStockService
{
    public function __construct(private readonly MagoVirtualClient $client) {}

    /**
     * Revisa todos los productos dropshipping y ajusta su disponibilidad.
     * Devuelve un resumen del proceso.
     */
    public function syncAll(): array
    {
        $summary = $this->emptySummary();

        $products = Product::where('is_dropshipping', true)
            ->with([
                'variants' => fn($q) => $q->where('is_active', true),
                'variants.barcodes',
                'suppliers',
            ])
            ->get();

        foreach ($products as $product) {
            $this->mergeSummary($summary, $this->syncProduct($product));
        }

        return $summary;
    }

    /**
     * Revisa UN producto dropshipping y ajusta su disponibilidad (o la de sus
     * variantes). Devuelve el resumen de ese producto.
     */
    public function syncProduct(Product $product): array
    {
        $product->loadMissing([
            'variants' => fn($q) => $q->where('is_active', true),
            'variants.barcodes',
            'suppliers',
        ]);

        $threshold = (int) config('services.magovirtual.stock_threshold', 15);
        $summary = $this->emptySummary();

        if ($product->variants->isNotEmpty()) {
            foreach ($product->variants as $variant) {
                $this->syncVariant($product, $variant, $threshold, $summary);
                usleep(200000); // 0.2s entre requests
            }
        } else {
            $this->syncSimpleProduct($product, $threshold, $summary);
            usleep(200000);
        }

        return $summary;
    }

    private function emptySummary(): array
    {
        return [
            'checked'   => 0,
            'updated'   => 0,
            'unmatched' => 0,
            'errors'    => 0,
            'changes'   => [],
        ];
    }

    private function mergeSummary(array &$base, array $add): void
    {
        $base['checked']   += $add['checked'];
        $base['updated']   += $add['updated'];
        $base['unmatched'] += $add['unmatched'];
        $base['errors']    += $add['errors'];
        $base['changes']    = array_merge($base['changes'], $add['changes']);
    }

    private function syncVariant(Product $product, $variant, int $threshold, array &$summary): void
    {
        $summary['checked']++;

        $barcode = $variant->barcodes->firstWhere('is_primary', true)?->barcode
            ?? $variant->barcodes->first()?->barcode;

        if (!$barcode) {
            $summary['unmatched']++;
            return;
        }

        $magoId = $this->client->findIdByBarcode($barcode);
        if (!$magoId) {
            $summary['unmatched']++;
            return;
        }

        $stock = $this->client->getStockById($magoId);
        if ($stock === null) {
            $summary['errors']++;
            return;
        }

        $this->applyAvailability($variant, $stock, $threshold, $summary, [
            'type'    => 'variant',
            'product' => $product->name,
            'sku'     => $variant->sku,
        ]);
    }

    private function syncSimpleProduct(Product $product, int $threshold, array &$summary): void
    {
        $summary['checked']++;

        $url = $product->suppliers->first()?->pivot?->supplier_product_url;
        $magoId = $this->extractId($url);

        if (!$magoId) {
            $summary['unmatched']++;
            return;
        }

        $stock = $this->client->getStockById($magoId);
        if ($stock === null) {
            $summary['errors']++;
            return;
        }

        $this->applyAvailability($product, $stock, $threshold, $summary, [
            'type'    => 'product',
            'product' => $product->name,
            'sku'     => $product->sku,
        ]);
    }

    /**
     * Aplica la disponibilidad (0/1) al modelo si cambió. No toca stock_updated_at.
     */
    private function applyAvailability($model, int $supplierStock, int $threshold, array &$summary, array $meta): void
    {
        $newStock = $supplierStock > $threshold ? 1 : 0;

        if ((int) $model->stock === $newStock) {
            return;
        }

        $model->updateQuietly(['stock' => $newStock]);

        $summary['updated']++;
        $summary['changes'][] = array_merge($meta, [
            'supplier_stock' => $supplierStock,
            'available'      => $newStock === 1,
        ]);
    }

    /**
     * Extrae el id de magovirtual de la supplier_product_url (último grupo numérico).
     */
    private function extractId(?string $url): ?int
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/(\d+)(?!.*\d)/', $url, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}

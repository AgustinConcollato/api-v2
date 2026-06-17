<?php

namespace App\Console\Commands;

use App\Services\DropshippingStockService;
use Illuminate\Console\Command;

class SyncDropshippingStock extends Command
{
    protected $signature = 'dropshipping:sync-stock';

    protected $description = 'Revisa el stock del proveedor (magovirtual) y ajusta la disponibilidad de los productos dropshipping.';

    public function handle(DropshippingStockService $service): int
    {
        $this->info('Sincronizando stock de dropshipping...');

        $summary = $service->syncAll();

        $this->table(
            ['Revisados', 'Actualizados', 'Sin match', 'Errores'],
            [[$summary['checked'], $summary['updated'], $summary['unmatched'], $summary['errors']]]
        );

        foreach ($summary['changes'] as $c) {
            $estado = $c['available'] ? 'Disponible' : 'Sin stock';
            $this->line(" - [{$c['type']}] {$c['product']} ({$c['sku']}): proveedor {$c['supplier_stock']} => {$estado}");
        }

        return self::SUCCESS;
    }
}

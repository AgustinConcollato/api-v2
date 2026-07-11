<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync de stock de dropshipping contra el proveedor (magovirtual).
// Hoy se dispara a mano (botón en el panel / comando). Para automatizarlo a futuro,
// descomentar la siguiente línea (requiere el cron del server corriendo schedule:run):
// \Illuminate\Support\Facades\Schedule::command('dropshipping:sync-stock')->hourly()->withoutOverlapping();

// Recordatorio diario de facturas de proveedores próximas a vencer.
// Requiere el cron del server corriendo `php artisan schedule:run` cada minuto.
\Illuminate\Support\Facades\Schedule::command('supplier-purchases:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

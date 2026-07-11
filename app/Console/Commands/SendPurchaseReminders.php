<?php

namespace App\Console\Commands;

use App\Mail\PurchaseReminderMail;
use App\Services\SupplierPurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPurchaseReminders extends Command
{
    protected $signature = 'supplier-purchases:send-reminders';

    protected $description = 'Envía un mail resumen con las facturas de proveedores próximas a vencer (según los días de aviso de cada una).';

    public function handle(SupplierPurchaseService $service): int
    {
        $purchases = $service->dueSoon();

        if ($purchases->isEmpty()) {
            $this->info('No hay facturas por vencer para avisar hoy.');
            return self::SUCCESS;
        }

        $recipients = array_values(array_filter([
            config('mail.order_notify'),
            config('mail.purchase_reminder'),
        ]));

        if (empty($recipients)) {
            $this->error('No hay destinatarios configurados (ORDER_NOTIFY_EMAIL / PURCHASE_REMINDER_EMAIL).');
            return self::FAILURE;
        }

        Mail::to($recipients)->send(new PurchaseReminderMail($purchases));

        $service->markReminded($purchases->pluck('id')->all());

        $this->info("Recordatorio enviado: {$purchases->count()} factura(s) a " . implode(', ', $recipients) . '.');

        foreach ($purchases as $p) {
            $this->line(" - {$p->supplier?->name} · Fact {$p->invoice_number} · vence {$p->due_date?->format('d/m/Y')} · saldo \${$p->balance}");
        }

        return self::SUCCESS;
    }
}

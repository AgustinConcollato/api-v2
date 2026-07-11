<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class PurchaseReminderMail extends Mailable
{
    /**
     * @param Collection $purchases Facturas por vencer (con supplier y payments cargados).
     */
    public function __construct(public Collection $purchases) {}

    public function envelope(): Envelope
    {
        $count = $this->purchases->count();
        $noun = $count === 1 ? 'factura por vencer' : 'facturas por vencer';

        return new Envelope(
            subject: "{$count} {$noun} — Cuentas por pagar",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-reminders',
            with: ['purchases' => $this->purchases],
        );
    }
}

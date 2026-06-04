<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $shortId = substr($this->order->id, 0, 8);

        return new Envelope(
            subject: "Nuevo pedido pendiente #{$shortId}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-pending',
            with: ['order' => $this->order],
        );
    }
}

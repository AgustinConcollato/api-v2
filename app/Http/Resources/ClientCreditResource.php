<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'amount'         => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'order_id'       => $this->order_id,
            'order_number'   => $this->whenLoaded('order', fn() => $this->order?->number),
            'note'           => $this->note,
            'created_at'     => $this->created_at,
        ];
    }
}

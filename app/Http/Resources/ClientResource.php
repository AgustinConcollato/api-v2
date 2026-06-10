<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('addresses');

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'price_list_id' => $this->price_list_id,
            'addresses'     => AddressResource::collection($this->addresses),
        ];
    }
}

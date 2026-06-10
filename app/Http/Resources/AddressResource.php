<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'label'         => $this->label,
            'street'        => $this->street,
            'street_number' => $this->street_number,
            'floor'         => $this->floor,
            'apartment'     => $this->apartment,
            'locality'      => $this->locality,
            'province'      => $this->province,
            'postal_code'   => $this->postal_code,
            'is_default'    => (bool) $this->is_default,
        ];
    }
}

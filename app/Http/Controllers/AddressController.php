<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController
{
    public function index(Request $request)
    {
        $client = $request->user('client');

        return AddressResource::collection($client->addresses()->get());
    }

    public function store(AddressRequest $request)
    {
        $data = $request->validated();
        $client = $request->user('client');

        $makeDefault = !empty($data['is_default']) || $client->addresses()->count() === 0;

        $address = DB::transaction(function () use ($client, $data, $makeDefault) {
            if ($makeDefault) {
                $client->addresses()->update(['is_default' => false]);
            }

            return $client->addresses()->create([
                'label'         => $data['label'] ?? null,
                'street'        => $data['street'],
                'street_number' => $data['street_number'],
                'floor'         => $data['floor'] ?? null,
                'apartment'     => $data['apartment'] ?? null,
                'locality'      => $data['locality'],
                'province'      => $data['province'],
                'postal_code'   => $data['postal_code'],
                'is_default'    => $makeDefault,
            ]);
        });

        return response()->json(new AddressResource($address), 201);
    }

    public function update(AddressRequest $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $data = $request->validated();

        $makeDefault = !empty($data['is_default']);

        DB::transaction(function () use ($request, $address, $data, $makeDefault) {
            if ($makeDefault) {
                $request->user('client')->addresses()->update(['is_default' => false]);
            }

            $address->update([
                'label'         => $data['label'] ?? null,
                'street'        => $data['street'],
                'street_number' => $data['street_number'],
                'floor'         => $data['floor'] ?? null,
                'apartment'     => $data['apartment'] ?? null,
                'locality'      => $data['locality'],
                'province'      => $data['province'],
                'postal_code'   => $data['postal_code'],
                'is_default'    => $makeDefault ? true : $address->is_default,
            ]);
        });

        return new AddressResource($address->fresh());
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $address->delete();

        return response()->json(['message' => 'Dirección eliminada.']);
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        DB::transaction(function () use ($request, $address) {
            $request->user('client')->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return new AddressResource($address->fresh());
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->client_id === $request->user('client')->id, 404);
    }
}

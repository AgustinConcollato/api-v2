<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController
{
    public function index(Request $request)
    {
        $client = $request->user('client');

        return response()->json(
            $client->addresses()->get()->map(fn($a) => $this->addressData($a))->all()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
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

        return response()->json($this->addressData($address), 201);
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $data = $this->validateData($request);

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

        return response()->json($this->addressData($address->fresh()));
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

        return response()->json($this->addressData($address->fresh()));
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->client_id === $request->user('client')->id, 404);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'label'         => 'nullable|string|max:255',
            'street'        => 'required|string|max:255',
            'street_number' => 'required|string|max:20',
            'floor'         => 'nullable|string|max:10',
            'apartment'     => 'nullable|string|max:10',
            'locality'      => 'required|string|max:255',
            'province'      => 'required|string|max:100',
            'postal_code'   => 'required|string|max:10',
            'is_default'    => 'nullable|boolean',
        ]);
    }

    private function addressData(Address $address): array
    {
        return [
            'id'            => $address->id,
            'label'         => $address->label,
            'street'        => $address->street,
            'street_number' => $address->street_number,
            'floor'         => $address->floor,
            'apartment'     => $address->apartment,
            'locality'      => $address->locality,
            'province'      => $address->province,
            'postal_code'   => $address->postal_code,
            'is_default'    => (bool) $address->is_default,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class ClientService
{
    /**
     * Summary of create
     * @param array $data
     * @return Client
     */
    public function create(array $data): Client
    {
        $data['password'] = Hash::make($data['password']);
        $client =  Client::create($data);

        return $client->refresh();
    }

    /**
     * Actualiza un cliente existente.
     * @param Client $client La instancia del modelo Client a actualizar.
     * @param array $data Los nuevos datos.
     * @return Client
     */
    public function update(Client $client, array $data): Client
    {
        // Si se proporciona una nueva contraseña, hashearla antes de actualizar
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $client->update($data);

        // Devolver el modelo actualizado, refrescándolo por si hay cambios en la base de datos
        return $client->refresh();
    }

    /**
     * Elimina un cliente existente.
     * @param Client $client La instancia del modelo Client a eliminar.
     * @return bool
     */
    public function delete(Client $client): bool
    {
        // El método delete() devuelve true si la eliminación fue exitosa
        return $client->delete();
    }

    /**
     * Summary of getClients
     * @return \Illuminate\Database\Eloquent\Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return Client::with('priceList')->get();
    }
}

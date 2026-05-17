<?php

namespace App\Services;

use App\Models\AccountMercadoLibre;
use App\Models\Product;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MercadoLibreService
{
    private const PROFILE_CACHE_TTL_SECONDS = 3600; // 1 hora

    private string $baseUrl = 'https://api.mercadolibre.com';
    private string $authUrl = 'https://auth.mercadolibre.com.ar/authorization';
    private string $tokenUrl = 'https://api.mercadolibre.com/oauth/token';

    // -------------------------------------------------------------------------
    // AUTH
    // -------------------------------------------------------------------------

    /**
     * Devuelve la URL para que el usuario autorice la app en ML
     */
    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => env('MERCADO_LIBRE_CLIENT_ID'),
            'redirect_uri'  => env('MERCADO_LIBRE_REDIRECT_URI'),
        ]);

        return "{$this->authUrl}?{$params}";
    }

    /**
     * Intercambia el code de OAuth por access_token y lo guarda en DB.
     * Si ya existe una cuenta vinculada para el usuario, la reemplaza.
     */
    public function exchangeCode(string $code, User $user): AccountMercadoLibre
    {
        $response = Http::post($this->tokenUrl, [
            'grant_type'    => 'authorization_code',
            'client_id'     => env('MERCADO_LIBRE_CLIENT_ID'),
            'client_secret' => env('MERCADO_LIBRE_CLIENT_SECRET'),
            'code'          => $code,
            'redirect_uri'  => env('MERCADO_LIBRE_REDIRECT_URI'),
        ]);

        $data = $response->json();

        if (isset($data['error'])) {
            $msg = $data['message'] ?? 'Error desconocido';

            if ($data['error'] === 'invalid_grant') {
                throw new Exception('El código de autorización expiró o ya fue usado. Intentá vincular nuevamente.', 400);
            }

            throw new Exception("Error de Mercado Libre: {$msg}", 400);
        }

        if (!isset($data['access_token'])) {
            throw new Exception('No se recibió access_token de Mercado Libre.', 400);
        }

        // Elimina cuenta anterior si existe (solo una cuenta por usuario)
        AccountMercadoLibre::where('user_id', $user->id)->delete();
        $this->forgetProfileCache($user);

        return AccountMercadoLibre::create([
            'user_id'       => $user->id,
            'ml_user_id'    => $data['user_id'],
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_type'    => $data['token_type'] ?? 'Bearer',
            'scope'         => $data['scope'] ?? null,
            'live_mode'     => $data['live_mode'] ?? false,
            'expires_at'    => now()->addSeconds($data['expires_in']),
        ]);
    }

    /**
     * Renueva el access_token usando el refresh_token
     */
    public function refreshToken(AccountMercadoLibre $account): AccountMercadoLibre
    {
        $response = Http::post($this->tokenUrl, [
            'grant_type'    => 'refresh_token',
            'client_id'     => env('MERCADO_LIBRE_CLIENT_ID'),
            'client_secret' => env('MERCADO_LIBRE_CLIENT_SECRET'),
            'refresh_token' => $account->refresh_token,
        ]);

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new Exception('Error al renovar token de Mercado Libre: ' . json_encode($data), 400);
        }

        $account->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'scope'         => $data['scope'] ?? $account->scope,
            'live_mode'     => $data['live_mode'] ?? $account->live_mode,
            'expires_at'    => now()->addSeconds($data['expires_in']),
        ]);

        return $account->fresh();
    }

    /**
     * Desvincula la cuenta: revoca el token en ML y elimina el registro
     */
    public function revoke(User $user): void
    {
        $account = AccountMercadoLibre::where('user_id', $user->id)->first();

        if (!$account) return;

        Http::post('https://api.mercadopago.com/oauth/revoke', [
            'client_id' => env('MERCADO_LIBRE_CLIENT_ID'),
            'client_secret' => env('MERCADO_LIBRE_CLIENT_SECRET'),
            'token' => $account->access_token,
        ]);

        $this->forgetProfileCache($user);
        $account->delete();
    }

    /**
     * Obtiene el perfil del usuario en ML (cacheado 1 h por usuario).
     */
    public function getProfile(User $user): array
    {
        $account = $this->getValidAccount($user);

        return Cache::remember(
            $this->profileCacheKey($user),
            self::PROFILE_CACHE_TTL_SECONDS,
            function () use ($account) {
                $response = Http::withToken($account->access_token)
                    ->get("{$this->baseUrl}/users/me");

                if ($response->failed()) {
                    throw new Exception('Error al obtener perfil de Mercado Libre.', 500);
                }

                return $response->json();
            }
        );
    }

    // -------------------------------------------------------------------------
    // CATEGORÍAS
    // -------------------------------------------------------------------------

    /**
     * Busca categorías de ML por texto (para el autocomplete del form)
     */
    public function searchCategories(string $query, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/sites/MLA/domain_discovery/search", [
                'q'    => $query,
                'limit' => 8,
            ]);

        if ($response->failed()) {
            throw new Exception('Error al buscar categorías en Mercado Libre.', 500);
        }

        return $response->json();
    }

    /**
     * Trae los atributos requeridos/opcionales de una categoría ML
     */
    public function getCategoryAttributes(string $categoryId, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/categories/{$categoryId}/attributes");

        if ($response->failed()) {
            throw new Exception('Error al obtener atributos de la categoría.', 500);
        }

        return $response->json();
    }

    // -------------------------------------------------------------------------
    // FEES / COMISIONES
    // -------------------------------------------------------------------------

    /**
     * Calcula las comisiones exactas para un listing usando la Listing Prices API de ML.
     *
     * Endpoint oficial:
     *   GET /sites/MLA/listing_prices?price={price}&listing_type_id={id}&category_id={id}
     *
     * Devuelve un array de tipos; filtramos por listing_type_id para obtener
     * el sale_fee_amount exacto y el percentage_fee real.
     *
     * Docs: https://developers.mercadolibre.com.ar/es_ar/comision-por-vender
     *
     * @return array {
     *   sale_fee_amount:   float,   // comisión en ARS
     *   commission_rate:   float,   // porcentaje decimal (ej: 0.155)
     *   percentage_fee:    float,   // porcentaje ML (ej: 15.5)
     *   listing_type_id:   string,
     *   listing_type_name: string,
     *   source:            "listing_prices_api"
     * }
     */
    public function getListingFees(string $categoryId, string $listingTypeId, float $price, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/sites/MLA/listing_prices", [
                'price'           => $price,
                'listing_type_id' => $listingTypeId,
                'category_id'     => $categoryId,
                'currency_id'     => 'ARS',
                'logistic_type' => 'drop_off',
                'shipping_mode' => 'me2',
                'billable_weight' => 5828,
            ]);

        if ($response->failed()) {
            throw new Exception('Error al consultar comisiones en Mercado Libre.', $response->status());
        }

        $data = $response->json();

        // La API puede devolver:
        //   - Un objeto directo cuando se filtra por listing_type_id + category_id
        //   - Un array de objetos cuando no se filtra o hay varios resultados
        $match = null;

        if (isset($data['listing_type_id'])) {
            // Objeto directo
            $match = $data;
        } elseif (is_array($data)) {
            // Array de tipos → buscar el solicitado
            foreach ($data as $item) {
                if (is_array($item) && ($item['listing_type_id'] ?? '') === $listingTypeId) {
                    $match = $item;
                    break;
                }
            }
            // Fallback: primer elemento del array
            if (!$match && count($data) > 0 && is_array($data[0])) {
                $match = $data[0];
            }
        }

        if (!$match || !isset($match['sale_fee_amount'])) {
            throw new Exception('No se encontró información de comisión para el tipo de publicación.', 422);
        }

        $details         = $match['sale_fee_details'] ?? [];
        $saleFeeAmount   = (float) $match['sale_fee_amount'];
        $percentageFee   = (float) ($details['percentage_fee']       ?? 0);
        $meliPctFee      = (float) ($details['meli_percentage_fee']  ?? 0);
        $fixedFee        = (float) ($details['fixed_fee']            ?? 0);
        $financingFee    = (float) ($details['financing_add_on_fee'] ?? 0);
        $listingFee      = (float) ($match['listing_fee_amount']     ?? 0);

        return [
            // Totales
            'sale_fee_amount'      => $saleFeeAmount,           // comisión total ARS
            'commission_rate'      => $price > 0 ? round($saleFeeAmount / $price, 4) : 0,

            // Desglose de la comisión
            'percentage_fee'       => $percentageFee,           // % total (ej: 14.35)
            'meli_percentage_fee'  => $meliPctFee,              // % solo ML
            'fixed_fee'            => $fixedFee,                // cargo fijo ARS incluido en sale_fee
            'financing_add_on_fee' => $financingFee,            // cargo adicional por cuotas

            // Publicación
            'listing_fee_amount'   => $listingFee,              // costo por publicar (0 en gold_special)
            'listing_type_id'      => $match['listing_type_id']   ?? $listingTypeId,
            'listing_type_name'    => $match['listing_type_name']  ?? '',
            'listing_exposure'     => $match['listing_exposure']   ?? '',
            'requires_picture'     => $match['requires_picture']   ?? false,
            'free_relist'          => $match['free_relist']        ?? false,

            'source'               => 'listing_prices_api',
        ];
    }

    public function getListingTypes(User $user)
    {
        $account = $this->getValidAccount($user);
        $listingDetails = [];

        // 1. Obtenemos la lista general
        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/sites/MLA/listing_types");

        if ($response->failed()) {
            throw new Exception('Error al consultar tipos de publicación.', $response->status());
        }

        $types = $response->json();

        // 2. Iteramos para obtener el detalle de cada tipo
        foreach ($types as $type) {
            // Asumiendo que $type es un array con la clave 'id'
            if ($type['id'] == 'gold_pro' || $type['id'] == 'gold_special') {
                $detailResponse = Http::withToken($account->access_token)
                    ->get("{$this->baseUrl}/sites/MLA/listing_types/{$type['id']}");

                if ($detailResponse->successful()) {
                    $listingDetails[] = $detailResponse->json();
                }
            }
        }

        return $listingDetails;
    }

    // -------------------------------------------------------------------------
    // ENVIOS
    // -------------------------------------------------------------------------

    public function getUserShippingPreferences(User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/users/{$account->ml_user_id}/shipping_preferences");

        if ($response->failed()) {
            throw new Exception('No se pudieron obtener las preferencias de envío.');
        }

        return $response->json();
    }

    public function getUserShippingCost(User $user, array $data): array
    {
        $account = $this->getValidAccount($user);

        $profile  = $this->getProfile($user);
        $zipCode  = $profile['address']['zip_code'] ?? null;
        $stateId  = $profile['address']['state'] ?? null;

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/users/{$account->ml_user_id}/shipping_options/free", [
                'dimensions'        => $data['dimensions'] ?? '10x10x10,500',
                'item_price'        => $data['price'],
                'verbose'           => true,
                'condition'         => 'new',
                'currency_id'       => 'ARS',
                'category_id'       => $data['category_id'],
                'listing_type_id'   => $data['listing_type_id'],
                'mode'              => $data['mode'],
                'logistic_type'     => $data['logistic_type'],
                'zip_code'          => $zipCode,
                'state_id'          => $stateId,
                'free_shipping'   => filter_var($data['free_shipping'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

        if ($response->failed()) {
            throw new Exception(json_encode($response->json()));
        }

        // if ($response->failed()) {
        //     throw new \Exception('No se pudieron obtener los costos del envío.', 422);
        // }

        return $response->json();
    }

    // -------------------------------------------------------------------------
    // PUBLICACIONES
    // -------------------------------------------------------------------------

    /**
     * Publica un producto en ML
     *
     * $data esperado:
     * [
     *   'title'           => string,
     *   'category_id'     => string,   // ej: 'MLA1055'
     *   'price'           => float,
     *   'currency_id'     => 'ARS',
     *   'available_quantity' => int,
     *   'buying_mode'     => 'buy_it_now',
     *   'listing_type_id' => 'gold_special', // free | gold_special | gold_pro
     *   'condition'       => 'new' | 'used',
     *   'shipping'        => [...],
     *   'attributes'      => [...],
     *   'pictures'        => [...],    // [['source' => 'https://...'], ...]
     * ]
     */
    public function publishProduct(array $data, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->post("{$this->baseUrl}/items", $data);

        $result = $response->json();

        if ($response->failed()) {
            $msg = $result['message'] ?? $result['error'] ?? 'Error al publicar en Mercado Libre';
            throw new Exception(json_encode($response->json()), $response->status());
        }

        return $result;
    }

    /**
     * Actualiza una publicación existente en ML
     */
    public function updatePublication(string $mlItemId, array $data, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->put("{$this->baseUrl}/items/{$mlItemId}", $data);

        $result = $response->json();

        if ($response->failed()) {
            $msg = $result['message'] ?? 'Error al actualizar publicación';
            throw new Exception($msg, $response->status());
        }

        return $result;
    }

    /**
     * Pausa una publicación (status: paused)
     */
    public function pausePublication(string $mlItemId, User $user): array
    {
        return $this->updatePublication($mlItemId, ['status' => 'paused'], $user);
    }

    /**
     * Reactiva una publicación pausada (status: active)
     */
    public function reactivatePublication(string $mlItemId, User $user): array
    {
        return $this->updatePublication($mlItemId, ['status' => 'active'], $user);
    }

    /**
     * Cierra/elimina una publicación (status: closed)
     */
    public function closePublication(string $mlItemId, User $user): array
    {
        return $this->updatePublication($mlItemId, ['status' => 'closed'], $user);
    }

    /**
     * Lista las publicaciones activas del usuario en ML
     */
    public function getPublications(User $user, string $status = 'active', int $offset = 0): array
    {
        $account = $this->getValidAccount($user);

        // Primero obtenemos los IDs de publicaciones
        $idsResponse = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/users/{$account->ml_user_id}/items/search", [
                'offset' => $offset,
                'status' => $status,
                'limit'  => 50,
            ]);

        if ($idsResponse->failed()) {
            throw new Exception('Error al obtener publicaciones.', 500);
        }

        $ids = $idsResponse->json()['results'] ?? [];

        if (empty($ids)) return [];

        // Traemos detalle de los items en bulk (hasta 20 por request)
        $chunks = array_chunk($ids, 20);
        $items = [];

        foreach ($chunks as $chunk) {
            $detailResponse = Http::withToken($account->access_token)
                ->get("{$this->baseUrl}/items", [
                    'ids' => implode(',', $chunk),
                ]);

            if ($detailResponse->ok()) {
                $items = array_merge($items, $detailResponse->json());
            }
        }

        return $items;
    }

    /**
     * Obtiene una publicación específica por su ID de ML
     */
    public function getPublication(string $mlItemId, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/items/{$mlItemId}");

        if ($response->failed()) {
            throw new Exception('Error al obtener la publicación.', 500);
        }

        return $response->json();
    }

    public function getPublicationPerformance(string $mlItemId, User $user): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->get("{$this->baseUrl}/item/{$mlItemId}/performance");

        // 404 = performance no generado aún, devolver vacío en vez de error
        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new Exception('Error al obtener calidad de la publicación.', $response->status());
        }

        return $response->json();
    }

    /**
     * Sube una imagen a ML y devuelve el picture_id
     */
    public function uploadPicture(User $user, \Illuminate\Http\UploadedFile $file): array
    {
        $account = $this->getValidAccount($user);

        $response = Http::withToken($account->access_token)
            ->attach('file', $file->getContent(), $file->getClientOriginalName())
            ->post("{$this->baseUrl}/pictures/items/upload");

        if ($response->failed()) {
            $msg = $response->json('message') ?? 'Error al subir imagen a Mercado Libre.';
            throw new Exception($msg, $response->status());
        }

        return $response->json();
    }

    /**
     * Actualiza las imágenes de una publicación.
     * $pictures = array de [ 'id' => '...' ] o [ 'source' => 'https://...' ]
     */
    public function updatePublicationPictures(string $mlItemId, array $pictures, User $user): array
    {
        return $this->updatePublication($mlItemId, ['pictures' => $pictures], $user);
    }

    // -------------------------------------------------------------------------
    // HELPERS INTERNOS
    // -------------------------------------------------------------------------

    /**
     * Obtiene la cuenta ML del usuario, refrescando el token si está por expirar
     */
    public function getValidAccount(User $user): AccountMercadoLibre
    {
        $account = AccountMercadoLibre::where('user_id', $user->id)->first();

        if (!$account) {
            throw new Exception('No hay cuenta de Mercado Libre vinculada.', 403);
        }

        if ($account->isTokenExpired()) {
            $account = $this->refreshToken($account);
        }

        return $account;
    }

    private function profileCacheKey(User $user): string
    {
        return "ml_profile:user:{$user->id}";
    }

    private function forgetProfileCache(User $user): void
    {
        Cache::forget($this->profileCacheKey($user));
    }
}

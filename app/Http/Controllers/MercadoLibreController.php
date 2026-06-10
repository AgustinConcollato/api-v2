<?php

namespace App\Http\Controllers;

use App\Services\MercadoLibreService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MercadoLibreController
{
    public function __construct(private MercadoLibreService $mlService) {}

    // -------------------------------------------------------------------------
    // AUTH
    // -------------------------------------------------------------------------

    /**
     * GET /mercado-libre/auth-url
     * Devuelve la URL de autorización OAuth para redirigir al usuario a ML
     */
    public function getAuthUrl(Request $request)
    {
        try {
            $url = $this->mlService->getAuthUrl();
            return response()->json(['url' => $url], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar URL de autorización', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /mercado-libre/callback
     * Recibe el code de OAuth y lo intercambia por tokens
     */
    public function callback(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
            ], [
                'code.required' => 'No se recibió el código de autorización.',
            ]);

            $user = $request->user();
            $account = $this->mlService->exchangeCode($validated['code'], $user);

            return response()->json([
                'message' => 'Cuenta de Mercado Libre vinculada correctamente.',
                'account' => $account,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/revoke
     * Desvincula la cuenta de ML del usuario
     */
    public function revoke(Request $request)
    {
        try {
            $this->mlService->revoke($request->user());
            return response()->json(['message' => 'Cuenta de Mercado Libre desvinculada.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al desvincular cuenta', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /mercado-libre/profile
     * Trae el perfil del usuario en ML
     */
    public function getProfile(Request $request)
    {
        try {
            $profile = $this->mlService->getProfile($request->user());
            return response()->json($profile, 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    // -------------------------------------------------------------------------
    // CATEGORÍAS
    // -------------------------------------------------------------------------

    /**
     * GET /mercado-libre/categories/search?q=texto
     * Busca categorías de ML (para el autocomplete del formulario)
     */
    public function searchCategories(Request $request)
    {
        try {
            $request->validate(['q' => 'required|string|min:2']);

            $results = $this->mlService->searchCategories($request->q, $request->user());
            return response()->json($results, 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /mercado-libre/categories/{categoryId}/attributes
     * Trae los atributos requeridos/opcionales de una categoría
     */
    public function getCategoryAttributes(Request $request, string $categoryId)
    {
        try {
            $attributes = $this->mlService->getCategoryAttributes($categoryId, $request->user());
            return response()->json($attributes, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // FEES / COMISIONES
    // -------------------------------------------------------------------------

    /**
     * GET /mercado-libre/listing-fees
     *
     * Calcula las comisiones exactas para un listing antes de publicar.
     * Intenta el endpoint oficial de ML; si falla, devuelve 422 para que
     * el frontend use sus valores de fallback.
     *
     * Query params:
     *   - category_id     string  requerido   ej: "MLA1055"
     *   - listing_type_id string  requerido   ej: "gold_special"
     *   - price           numeric requerido   ej: 15000
     */
    public function getListingFees(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id'     => 'required|string',
                'listing_type_id' => 'required|string',
                'price'           => 'required|numeric|min:1',
                'tags'            => 'nullable|string',
                'billable_weight' => 'nullable|integer|min:1',
            ]);

            $fees = $this->mlService->getListingFees(
                $validated['category_id'],
                $validated['listing_type_id'],
                (float) $validated['price'],
                $request->user(),
                $validated['tags'] ?? null,
                isset($validated['billable_weight']) ? (int) $validated['billable_weight'] : null,
            );

            return response()->json($fees, 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // 422 → el frontend sabe que debe usar fallback fijo
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function getListingTypes(Request $request)
    {
        try {
            $list = $this->mlService->getListingTypes($request->user());

            return response()->json($list, 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // 422 → el frontend sabe que debe usar fallback fijo
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    // -------------------------------------------------------------------------
    // ENVIOS
    // -------------------------------------------------------------------------

    public function getShippingPreferences(Request $request)
    {
        try {
            $shippinPreferences = $this->mlService->getUserShippingPreferences($request->user());
            return response()->json($shippinPreferences);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function getShippingCost(Request $request)
    {
        try {
            $validated = $request->validate([
                'price'           => 'required|numeric|min:0',
                'category_id'     => 'required|string',
                'listing_type_id' => 'required|string',
                'mode'            => 'required|string',
                'logistic_type'   => 'required|string',
                'free_shipping'   => 'sometimes|in:true,false,1,0',
                'dimensions'      => 'required|string'
            ]);

            $validated['free_shipping'] = filter_var($validated['free_shipping'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $cost = $this->mlService->getUserShippingCost($request->user(), $validated);
            return response()->json($cost);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }



    // -------------------------------------------------------------------------
    // PUBLICACIONES
    // -------------------------------------------------------------------------

    /**
     * POST /mercado-libre/publications
     * Publica un producto en ML
     *
     * Body esperado: { title, category_id, price, currency_id, available_quantity,
     *                  buying_mode, listing_type_id, condition, shipping, attributes, pictures }
     */
    public function publish(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'               => 'required|string|max:60',
                'category_id'         => 'required|string',
                'price'               => 'required|numeric|min:0',
                'currency_id'         => 'required|string',
                'available_quantity'  => 'required|integer|min:1',
                'buying_mode'         => 'required|string',
                'listing_type_id'     => 'required|string',
                'condition'           => 'required|in:new,used',
                'shipping'            => 'required|array',
                'attributes'          => 'nullable|array',
                'pictures'            => 'nullable|array',
                'pictures.*.source'   => 'url',
            ]);

            $result = $this->mlService->publishProduct($validated, $request->user());

            return response()->json([
                'message'   => 'Producto publicado en Mercado Libre.',
                'ml_item'   => $result,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * GET /mercado-libre/publications
     * Lista las publicaciones del usuario en ML
     * Query param: ?status=active|paused|closed (default: active)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPublications(Request $request)
    {
        try {
            $status = $request->query('status', 'active');
            $offset = $request->query('offset', 0);
            $publications = $this->mlService->getPublications($request->user(), $status, $offset);
            return response()->json($publications, 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * GET /mercado-libre/{mlItemId}/performance
     * Estado de la publicación, recomendaciones para mejor posicionamiento
     * @param Request $request
     * @param string $mlItemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPublicationPerformance(Request $request, string $mlItemId)
    {
        try {
            $data = $this->mlService->getPublicationPerformance($mlItemId, $request->user());
            return response()->json($data);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * GET /mercado-libre/publications/{mlItemId}
     * Detalle de una publicación
     */
    public function getPublication(Request $request, string $mlItemId)
    {
        try {
            $publication = $this->mlService->getPublication($mlItemId, $request->user());
            return response()->json($publication, 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function uploadPicture(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240',
            ]);

            $result = $this->mlService->uploadPicture($request->user(), $request->file('file'));
            return response()->json($result, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Archivo inválido', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function updatePublicationPictures(Request $request, string $mlItemId)
    {
        try {
            $validated = $request->validate([
                'pictures'         => 'required|array|min:1',
                'pictures.*.id'    => 'sometimes|string',
                'pictures.*.source' => 'sometimes|url',
            ]);

            $result = $this->mlService->updatePublicationPictures(
                $mlItemId,
                $validated['pictures'],
                $request->user()
            );
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * PUT /mercado-libre/publications/{mlItemId}
     * Actualiza una publicación (precio, stock, título, etc.)
     */
    public function updatePublication(Request $request, string $mlItemId)
    {
        try {
            $data = $request->only(['title', 'price', 'available_quantity', 'listing_type_id', 'attributes', 'tags', 'variations', 'pictures']);
            $result = $this->mlService->updatePublication($mlItemId, $data, $request->user());

            return response()->json([
                'message' => 'Publicación actualizada.',
                'ml_item' => $result,
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * DELETE /mercado-libre/publications/{mlItemId}/variations/{variationId}
     */
    public function deleteVariation(Request $request, string $mlItemId, string $variationId)
    {
        try {
            $result = $this->mlService->deleteVariation($mlItemId, $variationId, $request->user());
            return response()->json(['message' => 'Variación eliminada.', 'ml_item' => $result], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/publications/{mlItemId}/variations
     * Agrega una nueva variación a una publicación existente
     */
    public function addVariation(Request $request, string $mlItemId)
    {
        try {
            $data = $request->only(['attribute_combinations', 'price', 'available_quantity', 'picture_ids', 'attributes']);
            $result = $this->mlService->addVariation($mlItemId, $data, $request->user());
            return response()->json(['message' => 'Variación agregada.', 'variation' => $result], 201);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/publications/{mlItemId}/listing-type
     * Cambia el tipo de publicación (gold_special ↔ gold_pro)
     */
    public function changeListingType(Request $request, string $mlItemId)
    {
        try {
            $validated = $request->validate(['listing_type_id' => 'required|string|in:gold_special,gold_pro']);
            $result = $this->mlService->changeListingType($mlItemId, $validated['listing_type_id'], $request->user());
            return response()->json(['message' => 'Tipo de publicación actualizado.', 'ml_item' => $result], 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/publications/{mlItemId}/pause
     */
    public function pausePublication(Request $request, string $mlItemId)
    {
        try {
            $result = $this->mlService->pausePublication($mlItemId, $request->user());
            return response()->json(['message' => 'Publicación pausada.', 'ml_item' => $result], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/publications/{mlItemId}/reactivate
     */
    public function reactivatePublication(Request $request, string $mlItemId)
    {
        try {
            $result = $this->mlService->reactivatePublication($mlItemId, $request->user());
            return response()->json(['message' => 'Publicación reactivada.', 'ml_item' => $result], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * POST /mercado-libre/publications/{mlItemId}/close
     */
    public function closePublication(Request $request, string $mlItemId)
    {
        try {
            $result = $this->mlService->closePublication($mlItemId, $request->user());
            return response()->json(['message' => 'Publicación cerrada.', 'ml_item' => $result], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}

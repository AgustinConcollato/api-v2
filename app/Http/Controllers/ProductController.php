<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductController
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function createProduct(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stock' => 'required|integer|min:0',

            // 1. Imágenes (asumo subida de archivos)
            // Permite múltiples archivos, cada uno debe ser una imagen válida y no exceder 2MB.
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',

            'image_positions' => 'nullable|array',
            'image_positions.*' => 'required|integer|min:0',

            // 2. Categorías (Many-to-Many)
            // Debe ser un array y cada elemento debe ser un ID válido en la tabla 'categories'.
            'categories' => 'required|array',
            'categories.*' => 'exists:categories,id',
        ];

        $params = [
            // Campos Simples
            'name.required' => 'El nombre del producto es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'stock.required' => 'El stock es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'sku.unique' => 'El código SKU ya está registrado.',

            // Imágenes
            // 'images.*.image' => 'El archivo subido debe ser un formato de imagen válido (jpg, jpeg, png o webp).',
            'images.required' => 'Ingresa una imagen.',
            'images.*.max' => 'La imagen no debe pesar más de 2MB.',
            'images.*.mimes' => 'La imagen debe estar en formato PNG, JPG, JPEG o WEBP.',

            // Categorías
            'categories.required' => 'Selecciona una categoría.',
            'categories.*.exists' => 'Una de las categorías seleccionadas no existe.',
        ];

        try {
            $validated = $request->validate($rules, $params);
            $validated['sku'] = $this->productService->generateUniqueSku($validated);
            $validated['status'] = ProductStatus::Incomplete;

            $product = DB::transaction(function () use ($validated, $request) {

                $product = $this->productService->createProduct($validated);

                if (isset($validated['categories'])) {
                    $this->productService->syncCategories($product, $validated['categories']);
                }

                if ($request->hasFile('images')) {
                    // $files es un array de objetos UploadedFile
                    $files = $request->file('images');

                    // $positions es un array de enteros
                    $positions = $validated['image_positions'] ?? [];

                    // Enviamos los archivos Y sus posiciones al servicio
                    $this->productService->processAndAttachImages($product, $files, $positions);
                }

                return $product;
            });

            $product->load('images');

            return response()->json($product, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function addPrices(Request $request, Product $product)
    {
        $rules = [
            'price_lists' => 'required|array',
            'price_lists.*.list_id' => 'required|exists:price_lists,id', // Debe existir en la tabla price_lists
            'price_lists.*.price' => 'required|numeric|min:1',

            'suppliers' => 'nullable|array',
            'suppliers.*.supplier_id' => 'required|uuid|exists:suppliers,id',
            'suppliers.*.purchase_price' => 'required|numeric|min:0',
            'suppliers.*.supplier_product_url' => 'nullable|url|max:512',
        ];

        $params = [
            'suppliers.*.supplier_id.exists' => 'El ID de uno de los proveedores no es válido.',
            'suppliers.*.purchase_price.required' => 'El precio de compra es obligatorio para cada proveedor.',
            'suppliers.*.purchase_price.numeric' => 'El precio de compra debe ser un número.',
            'suppliers.*.supplier_product_url.url' => 'La URL del producto del proveedor debe ser un formato válido.',

            'price_lists.required' => 'La lista de precios es obligatoria.',
            'price_lists.*.list_id.exists' => 'El ID de uno de las listas no es válido.',
            'price_lists.*.price.required' => 'El precio es obligatorio para cada lista de precio.',
            'price_lists.*.price.numeric' => 'El precio de venta debe ser un número.',
            'price_lists.*.price.min' => 'El precio debe ser mayor a 0.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $product = DB::transaction(function () use ($validated, $request, $product) {

                if (isset($validated['suppliers'])) {
                    $this->productService->syncSuppliers($product, $validated['suppliers']);
                }

                if (isset($validated['price_lists'])) {
                    $this->productService->syncPriceLists($product, $validated['price_lists']);
                }

                return $product;
            });

            if ($product->barcodes()->count() > 0) {
                $product->update(['status' => ProductStatus::Published]);
            } else {
                $product->update(['status' => ProductStatus::PendingBarcode]);
            }

            return response()->json($product, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function show(Product $product)
    {
        $product->images;
        $product->categories;
        $product->suppliers;
        $product->priceLists;
        $product->barcodes;

        return $product;
    }

    public function update(Request $request, Product $product)
    {
        // 1. Reglas de Validación (solo campos escalares)
        $rules = [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'stock' => 'nullable|integer|min:0',
        ];

        try {
            // Valida solo los campos simples del Request
            $validated = $request->validate($rules);

            // 2. Actualización
            // Idealmente, usar un método en ProductService:
            $product = $this->productService->updateProduct($product, $validated);

            // 3. Retorno
            return response()->json($product, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function syncCategories(Request $request, Product $product)
    {
        $rules = [
            'categories' => 'required|array',
            'categories.*' => 'exists:categories,id',
        ];

        $params = [
            'categories.required' => 'Selecciona al menos una categoría.',
            'categories.*.exists' => 'Una de las categorías seleccionadas no existe.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $product = DB::transaction(function () use ($validated, $product) {
                $this->productService->syncCategories($product, $validated['categories']);
                return $product;
            });

            $product->categories;

            return response()->json($product, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $filters = $request->only([
                'search',
                'category_id',
                'supplier_id',
                'stock_min',
                'stock_max',
                'price_min',
                'price_max',
                'price_list_id',
                'sort_by',
                'sort_order',
                'per_page',
                'status'
            ]);

            $products = $this->productService->getFilteredProducts($filters);

            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los productos.', 'message' => $e->getMessage()], 500);
        }
    }

    public function publicIndex(Request $request)
    {
        try {
            $filters = $request->only([
                'search',
                'category_id',
                'stock_min',
                'stock_max',
                'price_min',
                'price_max',
                'price_list_id',
                'sort_by',
                'sort_order',
                'per_page'
            ]);

            $products = $this->productService->getPublicProducts($filters);

            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los productos.', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeBarcode(Request $request, Product $product)
    {

        $rules = [
            'barcode' => 'required|string|max:255',
            'is_primary' => 'boolean',
        ];

        $params = [
            'barcode.required' => 'El código de barras es obligatorio.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $this->productService->associateBarcode(
                $product,
                $validated['barcode'],
                $validated['is_primary'] ?? false,
            );

            $product->barcodes;

            if ($product->priceLists()->count() > 0) {
                $product->update(['status' => ProductStatus::Published]);
            } else {
                $product->update(['status' => ProductStatus::PendingPrices]);
            }

            return response()->json($product, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function destroyBarcode(int $barcodeId)
    {
        try {
            $result = $this->productService->dissociateBarcode($barcodeId);

            if ($result === null) {
                // El servicio retornó null, indicando que el ID no fue encontrado.
                return response()->json([
                    'message' => 'El código de barras con ID ' . $barcodeId . ' no fue encontrado.'
                ], 404);
            }

            if ($result) {
                // Eliminación exitosa. Código 200 OK.
                // También puedes usar 204 No Content si React no necesita un cuerpo de respuesta.
                return response()->json([
                    'message' => 'Código de barras eliminado correctamente.'
                ]);
            }

            // Si $result es false (falla de eliminación en DB, etc.)
            return response()->json([
                'message' => 'No se pudo eliminar el código de barras debido a un error interno.'
            ], 500); // Código 500

        } catch (\Exception $e) {
            // Manejo de errores generales
            return response()->json([
                'message' => 'Ocurrió un error en el servidor durante la eliminación.'
            ], 500); // Código 500
        }
    }

    public function getByBarcode(string $barcode)
    {
        try {
            $product = $this->productService->getProductByBarcode($barcode);
            return response()->json($product, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los productos.', 'message' => $e->getMessage()], $e->getCode());
        }
    }

    public function updatePrices(Request $request, Product $product)
    {
        // Reglas y mensajes de validación (son los mismos que en addPrices)
        $rules = [
            'price_lists' => 'required|array',
            'price_lists.*.list_id' => 'required|exists:price_lists,id',
            'price_lists.*.price' => 'required|numeric|min:1',
        ];

        $params = [
            'price_lists.required' => 'La lista de precios es obligatoria.',
            'price_lists.*.list_id.exists' => 'El ID de uno de las listas no es válido.',
            'price_lists.*.price.required' => 'El precio es obligatorio para cada lista de precio.',
            'price_lists.*.price.numeric' => 'El precio de venta debe ser un número.',
            'price_lists.*.price.min' => 'El precio debe ser mayor a 0.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            // Llama al nuevo método del servicio que maneja la transacción
            $product = $this->productService->syncPricesAndSuppliers(
                $product,
                $validated['price_lists'],
            );

            return response()->json($product, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function addImages(Request $request, Product $product)
    {
        $rules = [
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ];

        $params = [
            'images.required' => 'Debes subir al menos una imagen para el producto.',
            'images.array' => 'El campo de imágenes debe ser un conjunto de archivos.',

            'images.*.image' => 'Uno de los archivos subidos no es una imagen válida.',
            'images.*.mimes' => 'La imagen debe estar en formato PNG, JPG, JPEG o WEBP.',
            'images.*.max' => 'La imagen no debe pesar más de 2MB (2048 KB).',
        ];

        try {
            $request->validate($rules, $params);
            $files = $request->file('images');

            DB::transaction(function () use ($product, $files) {
                $this->productService->addImagesToTheEnd($product, $files);
            });

            $product->images;
            return response()->json($product, 201); // 201 Created/Accepted
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function deleteImages(Request $request, Product $product)
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:images,id', // Asumiendo que existe una tabla 'images'
        ]);

        try {
            DB::transaction(function () use ($product, $request) {
                $this->productService->deleteImages($product, $request->input('image_ids'));
            });

            return response()->json(['message' => 'Imágenes eliminadas y reordenadas correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar imágenes.', 'error' => $e->getMessage()], 500);
        }
    }

    public function reorderImages(Request $request, Product $product)
    {
        $rules = [
            'positions' => 'required|array',
            // Aseguramos que cada elemento sea un array
            'positions.*' => 'required|array',
            // Aseguramos que 'id' sea obligatorio, entero, y exista en la tabla 'images'
            'positions.*.id' => 'required|integer|exists:images,id',
            // Aseguramos que 'position' sea obligatorio y un entero no negativo
            'positions.*.position' => 'required|integer|min:0',
        ];

        $messages = [
            'positions.required' => 'La lista de posiciones es obligatoria.',
            'positions.*.id.exists' => 'Una de las IDs de imagen proporcionadas no existe.',
            'positions.*.position.min' => 'La posición debe ser un número positivo.',
        ];

        try {
            $validated = $request->validate($rules, $messages);

            // Transformar el array de entrada (de array de objetos a array asociativo [ID => POSICIÓN])
            $imagePositions = [];
            foreach ($validated['positions'] as $item) {
                // Se puede agregar una verificación extra para asegurar que la imagen realmente 
                // pertenece a este $product, aunque 'exists:images,id' ya ayuda bastante.
                $imagePositions[$item['id']] = $item['position'];
            }

            // Ejecutar la operación dentro de una transacción (opcional pero recomendable)
            DB::transaction(function () use ($product, $imagePositions) {
                $this->productService->reorderImages($product, $imagePositions);
            });

            // Cargar las imágenes actualizadas para el retorno
            $product->images;

            return response()->json($product, 200); // 200 OK para actualización
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }

    public function updateProductSuppliersPrices(Request $request, Product $product)
    {
        // Reutilizamos gran parte de las reglas del método 'addPrices' que compartiste
        $rules = [
            'suppliers' => 'array', // Permite array vacío para poder desasociar todos los proveedores
            'suppliers.*.supplier_id' => 'required|uuid|exists:suppliers,id', // 'uuid' si usas UUIDs
            'suppliers.*.purchase_price' => 'required|numeric|min:0',
            'suppliers.*.supplier_product_url' => 'nullable|url|max:512',

            'price_lists' => 'nullable|array',
            'price_lists.*.list_id' => 'required|exists:price_lists,id', // Debe existir en la tabla price_lists
            'price_lists.*.price' => 'required|numeric|min:1',

        ];

        $params = [
            'suppliers.array' => 'Los proveedores deben ser un array.',
            'suppliers.*.supplier_id.exists' => 'El ID de uno de los proveedores no es válido.',
            'suppliers.*.purchase_price.required' => 'El precio de compra es obligatorio para cada proveedor.',
            'suppliers.*.purchase_price.numeric' => 'El precio de compra debe ser un número.',
            'suppliers.*.supplier_product_url.url' => 'La URL del producto del proveedor debe ser un formato válido.',

            'price_lists.*.list_id.exists' => 'El ID de uno de los proveedores no es válido.',
            'price_lists.*.price.required' => 'El precio es obligatorio para cada lista de precio.',
            'price_lists.*.price.numeric' => 'El precio de venta debe ser un número.',
            'price_lists.*.price.min' => 'El precio debe ser mayor a 0.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $product = DB::transaction(function () use ($validated, $product) {

                // 1. Sincronizar Proveedores (asocia/actualiza el precio de compra en la tabla pivote)
                // Si suppliers no viene o está vacío, se desasocian todos los proveedores
                $suppliers = $validated['suppliers'] ?? [];
                $this->productService->syncSuppliers($product, $suppliers);

                if (isset($validated['price_lists'])) {
                    $this->productService->syncPriceLists($product, $validated['price_lists']);
                }

                return $product;
            });

            if ($product->barcodes()->count() > 0) {
                $product->update(['status' => ProductStatus::Published]);
            } else {
                $product->update(['status' => ProductStatus::PendingBarcode]);
            }

            $product->load('suppliers', 'priceLists');

            return response()->json($product, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar precios y proveedores: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, Product $product)
    {
        $rules = [
            'status' => 'required|in:published,archived',
        ];

        $params = [
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            // Llama al nuevo método del servicio que maneja la transacción
            $product = $this->productService->updateProductStatus($product, $validated['status']);

            return response()->json($product, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }
}

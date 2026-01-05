<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{

    /**
     * Crea el registro principal del producto.
     * @param array $data
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        $productData = array_intersect_key($data, array_flip([
            'name',
            'description',
            'stock',
            'status',
            'sku'
        ]));

        // Product::create() genera el UUID automáticamente
        return Product::create($productData);
    }

    /**
     * Sincroniza las listas de precios de un producto.
     *
     * @param \App\Models\Product $product Instancia del producto.
     * @param array $priceLists Array de listas de precios con sus IDs y precios.
     * Ejemplo: [['list_id' => 1, 'price' => 125.50], ...]
     * @return void
     */
    public function syncPriceLists(Product $product, array $priceLists): void
    {
        // El array $syncData mapeará la información al formato requerido por el método sync:
        // [
        //    'price_list_id' => ['price' => 125.50],
        //    ...
        // ]
        $syncData = [];

        foreach ($priceLists as $item) {
            // Usamos el 'list_id' como clave (el ID de la lista de precios)
            // y proporcionamos los campos adicionales (pivot data) dentro de un array.
            $syncData[$item['list_id']] = [
                'price' => $item['price']
            ];
        }

        // El método sync() elimina las relaciones que ya no están presentes en $syncData,
        // y adjunta/actualiza las que sí lo están.
        // Nota: Si solo quieres adjuntar y actualizar (y no eliminar accidentalmente
        // relaciones no enviadas, aunque aquí se envían todas las sugeridas),
        // puedes usar attach o detach/update por separado.
        // Pero para 'sincronizar' el estado actual, 'sync' es lo más eficiente.

        // Se asume que el modelo Product tiene una relación llamada 'priceLists'
        // que apunta a la tabla pivot 'list_product'.
        $product->priceLists()->sync($syncData);
    }

    /**
     * Aplica búsqueda fulltext con fallback a búsqueda por palabras clave.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $searchTerm
     * @return void
     */
    public function search($query, string $searchTerm): void
    {
        $query->where(function ($q) use ($searchTerm) {
            // Búsqueda fulltext (requiere índice FULLTEXT en la columna name)
            // Usa MATCH AGAINST para búsqueda más precisa y rápida
            $q->whereRaw("MATCH(name) AGAINST(? IN BOOLEAN MODE)", ['"' . $searchTerm . '"'])
                // Fallback: búsqueda por palabras clave con LIKE si fulltext no encuentra resultados
                ->orWhere(function ($q) use ($searchTerm) {
                    $keywords = array_filter(explode(' ', trim($searchTerm)));
                    if (!empty($keywords)) {
                        foreach ($keywords as $word) {
                            $q->where(function ($subQ) use ($word) {
                                $subQ->where('name', 'like', '%' . $word . '%')
                                    ->orWhere('sku', 'like', '%' . $word . '%')
                                    ->orWhere('description', 'like', '%' . $word . '%')
                                    ->orWhereHas('barcodes', function ($barcodeQuery) use ($word) {
                                        $barcodeQuery->where('barcode', 'like', '%' . $word . '%');
                                    });
                            });
                        }
                    } else {
                        $q->orWhereHas('barcodes', function ($barcodeQuery) use ($searchTerm) {
                            $barcodeQuery->where('barcode', 'like', '%' . $searchTerm . '%');
                        });
                    }
                })
                ->orWhereHas('barcodes', function ($barcodeQuery) use ($searchTerm) {
                    $barcodeQuery->where('barcode', 'like', '%' . $searchTerm . '%');
                });
        });
    }

    /**
     * Obtiene productos con filtros, búsqueda, ordenamiento y paginación.
     * @param array $filters Array con los filtros de búsqueda
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getFilteredProducts(array $filters)
    {
        $query = Product::with(['images', 'categories', 'suppliers', 'barcodes']);

        // 1. Búsqueda por nombre, SKU o descripción (fulltext con fallback)
        if (!empty($filters['search'])) {
            $this->search($query, $filters['search']);
        }

        if (isset($filters['status'])) {
            $query->where('status', '=', $filters['status']);
        }

        // 2. Filtro por categoría (puede ser array o single)
        if (isset($filters['category_id'])) {
            $categoryIds = is_array($filters['category_id'])
                ? $filters['category_id']
                : [$filters['category_id']];

            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // 3. Filtro por proveedor
        if (isset($filters['supplier_id'])) {
            $query->whereHas('suppliers', function ($q) use ($filters) {
                $q->where('suppliers.id', $filters['supplier_id']);
            });
        }

        // 4. Filtro por rango de stock
        if (isset($filters['stock_min'])) {
            $query->where('stock', '>=', $filters['stock_min']);
        }
        if (isset($filters['stock_max'])) {
            $query->where('stock', '<=', $filters['stock_max']);
        }

        if (isset($filters['price_list_id'])) {
            $listId = $filters['price_list_id'];

            $query->with(['priceLists' => function ($query) use ($listId) {
                $query->where('price_list_id', $listId);
            }]);
        } else {
            $query->with('priceLists');
        }

        // 5. Filtro por rango de precio (usando la lista de precios por defecto o especificada)
        $priceListId = $filters['price_list_id'] ?? 1;
        if (isset($filters['price_min']) || isset($filters['price_max'])) {
            $query->whereHas('priceLists', function ($q) use ($priceListId, $filters) {
                $q->where('price_list_id', $priceListId);
                if (isset($filters['price_min'])) {
                    $q->where('list_product.price', '>=', $filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $q->where('list_product.price', '<=', $filters['price_max']);
                }
            });
        }

        // 6. Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validar que sort_by sea un campo válido
        $allowedSortFields = ['name', 'stock', 'sku', 'price', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // Validar que sort_order sea válido
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        // Si el ordenamiento es por precio, necesitamos un subquery
        if ($sortBy === 'price') {
            $query->addSelect([
                'sort_price' => DB::table('list_product')
                    ->select('price')
                    ->whereColumn('list_product.product_id', 'products.id')
                    ->where('list_product.price_list_id', $priceListId)
                    ->limit(1)
            ])
                ->orderBy('sort_price', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // 7. Paginación
        $perPage = $filters['per_page'] ?? 20;
        $perPage = min(max((int)$perPage, 1), 100); // Limitar entre 1 y 100

        return $query->paginate($perPage);
    }

    /**
     * Obtiene productos con stock disponible para el catálogo PDF.
     * Filtra productos que tengan al menos una imagen y precio en la lista especificada.
     *
     * @param int $priceListId ID de la lista de precios
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsForCatalog(int $priceListId = 1)
    {
        // Obtener productos con stock > 0
        $products = Product::where('stock', '>', 0)
            ->with([
                'images' => function ($query) {
                    $query->orderBy('position', 'asc');
                },
                'priceLists' => function ($query) use ($priceListId) {
                    $query->where('price_list_id', $priceListId);
                },
                'categories'
            ])
            ->orderBy('name', 'asc')
            ->get();

        // Filtrar productos que tengan al menos una imagen y precio en la lista especificada
        return $products->filter(function ($product) {
            return $product->images->count() > 0 && $product->priceLists->count() > 0;
        });
    }

    /**
     * Asocia/sincroniza categorías con el producto.
     * @param Product $product
     * @param array $categoryIds Array de IDs de categorías.
     * @return void
     */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    /**
     * Sincroniza proveedores y sus datos pivot (precio de compra, url).
     * @param Product $product
     * @param array $supplierItems Array con la data de proveedores.
     * @return void
     */
    public function syncSuppliers(Product $product, array $supplierItems): void
    {
        $supplierPivotData = [];
        foreach ($supplierItems as $item) {
            $supplierPivotData[$item['supplier_id']] = [
                'purchase_price' => $item['purchase_price'],
                'supplier_product_url' => $item['supplier_product_url'] ?? null,
            ];
        }
        $product->suppliers()->sync($supplierPivotData);
    }

    /**
     * Procesa, guarda y adjunta las imágenes al producto (con miniaturas) usando GD nativo.
     * @param Product $product
     * @param array $imageItems Array de objetos { file: UploadedFile, position: int }
     * @return void
     */
    public function processAndAttachImages(Product $product, array $files, array $imagePositions): void
    {
        $targetThumbSize = 200; // 200x200
        $targetMaxSize = 1200;   // Máximo 1200px de ancho para la principal

        foreach ($files as $index => $imageFile) {
            /** @var UploadedFile $imageFile */

            $position = $imagePositions[$index];
            $position = (int) $position;

            $fileExtension = strtolower($imageFile->getClientOriginalExtension());
            $fileName = (string) Str::uuid();
            $basePath = "products/{$product->id}";

            // 1. Crear recurso GD desde el archivo subido
            $sourceImage = $this->createImageResourceFromUploadedFile($imageFile, $fileExtension);
            if (!$sourceImage) {
                // Podrías lanzar una excepción o registrar un error aquí
                continue;
            }

            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);

            // 2. PROCESAR Y GUARDAR LA IMAGEN ORIGINAL (Optimizada)
            $originalPath = $this->saveResizedImage(
                $sourceImage,
                $width,
                $height,
                $targetMaxSize,
                "{$basePath}/{$fileName}_full.{$fileExtension}",
                $fileExtension,
                true
            );

            // 3. CREAR Y GUARDAR LA MINIATURA (Thumbnail 200x200 crop)
            $thumbnailPath = $this->saveCroppedThumbnail(
                $sourceImage,
                $width,
                $height,
                $targetThumbSize,
                "{$basePath}/{$fileName}_thumb.{$fileExtension}",
                $fileExtension
            );

            // 4. Crear el registro en DB
            $product->images()->create([
                'path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'position' => $position,
            ]);

            imagedestroy($sourceImage);
        }
    }

    /**
     * Genera un SKU único basado en el nombre y las categorías del producto.
     * @param array $data Los datos validados del request.
     * @return string
     */
    public function generateUniqueSku(array $data): string
    {
        // 1. Obtener la categoría principal (usaremos la primera ID del array)
        $firstCategoryId = $data['categories'][0] ?? null;

        // 2. Intentar buscar la categoría para obtener un prefijo descriptivo
        $categoryPrefix = 'PRD'; // Prefijo por defecto

        if ($firstCategoryId) {
            $category = Category::find($firstCategoryId);
            if ($category) {
                // Genera un prefijo corto de la categoría (ej: las primeras 3 letras)
                $categoryPrefix = strtoupper(Str::slug(substr($category->name, 0, 3)));
            }
        }

        // 3. Obtener prefijo del nombre del producto
        $namePrefix = strtoupper(Str::slug(substr($data['name'], 0, 3)));

        do {
            // 4. Generar un sufijo único para garantizar la exclusividad
            // Usamos un sufijo alfanumérico corto (ej: 4 caracteres)
            $uniqueSuffix = strtoupper(Str::random(4));

            // 5. Construir el SKU: CAT-NOM-XXXX
            $sku = "{$categoryPrefix}-{$namePrefix}-{$uniqueSuffix}";

            // 6. Verificar si el SKU ya existe en la base de datos
            $exists = Product::where('sku', $sku)->exists();
        } while ($exists);

        return $sku;
    }

    /**
     * Summary of getProductByBarcode
     * @param string $barcode
     * @throws \Exception
     * @return Product
     */
    public function getProductByBarcode(string $barcode)
    {
        $product = Product::findByBarcode($barcode);

        if (!$product) {
            throw new \Exception("No se encuentra producto para el código: {$barcode}", 404);
        }

        $product->images;
        $product->priceLists;

        return $product;
    }


    /**
     * Elimina imágenes del producto, tanto los archivos como los registros en DB.
     * @param Product $product
     * @param array $imageIds Array de IDs de las imágenes a eliminar.
     * @return void
     */
    public function deleteImages(Product $product, array $imageIds): void
    {
        // 1. Encontrar las imágenes que pertenecen a este producto y están en el array de IDs
        $imagesToDelete = $product->images()->whereIn('id', $imageIds)->get();

        foreach ($imagesToDelete as $image) {
            /** @var \App\Models\Image $image */

            // 2. Eliminar los archivos del disco (Storage)
            Storage::disk('public')->delete([
                $image->path,         // Ej: products/uuid/nombre_full.jpg
                $image->thumbnail_path // Ej: products/uuid/nombre_thumb.jpg
            ]);

            // 3. Eliminar el registro de la base de datos
            $image->delete();
        }

        $this->reindexPositions($product);
    }

    /**
     * Procesa y adjunta nuevas imágenes al producto, asignándoles la última posición disponible.
     * Este método es ideal para la función UPDATE cuando se añaden imágenes sin reordenar.
     * * @param Product $product
     * @param array $files Array de objetos UploadedFile.
     * @return void
     */
    public function addImagesToTheEnd(Product $product, array $files): void
    {

        $nextPosition = $product->images()->max('position');
        $nextPosition = is_null($nextPosition) ? 0 : $nextPosition + 1;

        $targetThumbSize = 200;
        $targetMaxSize = 1200;

        foreach ($files as $imageFile) {
            /** @var UploadedFile $imageFile */

            // Se usa la posición calculada y se incrementa
            $position = $nextPosition;
            $nextPosition++;

            $fileExtension = strtolower($imageFile->getClientOriginalExtension());
            $fileName = (string) Str::uuid();
            $basePath = "products/{$product->id}";

            // 1. Crear recurso GD desde el archivo subido
            $sourceImage = $this->createImageResourceFromUploadedFile($imageFile, $fileExtension);
            if (!$sourceImage) {
                continue;
            }

            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);

            // 2. PROCESAR Y GUARDAR LA IMAGEN ORIGINAL (Optimizada)
            $originalPath = $this->saveResizedImage(
                $sourceImage,
                $width,
                $height,
                $targetMaxSize,
                "{$basePath}/{$fileName}_full.{$fileExtension}",
                $fileExtension,
                true
            );

            // 3. CREAR Y GUARDAR LA MINIATURA (Thumbnail 200x200 crop)
            $thumbnailPath = $this->saveCroppedThumbnail(
                $sourceImage,
                $width,
                $height,
                $targetThumbSize,
                "{$basePath}/{$fileName}_thumb.{$fileExtension}",
                $fileExtension
            );

            // 4. Crear el registro en DB con la posición calculada
            $product->images()->create([
                'path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'position' => $position, // <-- Posición final
            ]);

            imagedestroy($sourceImage);
        }
    }

    /**
     * Reindexa las posiciones de todas las imágenes restantes del producto.
     * * @param Product $product
     * @return void
     */
    protected function reindexPositions(Product $product): void
    {
        // Obtener todas las imágenes restantes, ordenadas por su posición actual
        $remainingImages = $product->images()
            ->orderBy('position', 'asc')
            ->get();

        $newPosition = 0;

        foreach ($remainingImages as $image) {
            // Si la posición actual es diferente a la nueva, actualiza
            if ($image->position !== $newPosition) {
                $image->position = $newPosition;
                $image->save(); // Usamos save() para un solo modelo
            }
            $newPosition++;
        }
    }

    /**
     * Reordena las imágenes existentes del producto.
     *
     * @param Product $product
     * @param array $imagePositions Array asociativo [image_id => new_position]
     * @return void
     */
    public function reorderImages(Product $product, array $imagePositions): void
    {
        // $imagePositions debería ser un array de arrays o un array de objetos si se
        // envía desde el front-end, pero si solo contiene el orden, un array simple
        // ['id' => position] es más fácil de usar.
        // Asumiendo que $imagePositions es: [ID_IMAGEN_1 => POSICION_1, ID_IMAGEN_2 => POSICION_2, ...]

        foreach ($imagePositions as $imageId => $newPosition) {
            // Aseguramos que la ID de imagen pertenezca al producto
            $product->images()
                ->where('id', $imageId)
                ->update(['position' => $newPosition]);
        }
    }

    /**
     * Asocia un código de barras nuevo a un producto existente.
     *
     * @param Product $product El modelo de Producto al que asociar.
     * @param string $barcode El código de barras a asociar.
     * @param bool $isPrimary Indica si debe ser el código primario.
     * @param int|null $supplierId ID del proveedor (opcional).
     * @return ProductBarcode
     * @throws ValidationException Si el código de barras ya existe en el sistema.
     */
    public function associateBarcode(Product $product, string $barcode, bool $isPrimary = false): ProductBarcode
    {
        // 1. **Verificación de Unicidad**: 
        // Se valida que el código no esté ya registrado para CUALQUIER producto.
        if (ProductBarcode::where('barcode', $barcode)->exists()) {
            // Lanza una excepción de validación para ser manejada en el controlador/formulario.
            throw ValidationException::withMessages([
                'barcode' => 'El código de barras proporcionado ya está asociado a un producto.'
            ]);
        }

        // 2. **Manejo del Código Primario**:
        // Si se pide que este código sea el primario, aseguramos que ningún otro código
        // de este producto sea primario para mantener un único código por defecto.
        if ($isPrimary) {
            $product->barcodes()->update(['is_primary' => false]);
        }

        // 3. **Creación del Registro**:
        return $product->barcodes()->create([
            'barcode' => $barcode,
            'is_primary' => $isPrimary
        ]);
    }

    /**
     * Elimina un código de barras específico de un producto.
     *
     * @param int $barcodeId El ID del registro en la tabla product_barcodes a eliminar.
     * @return bool|null
     */
    public function dissociateBarcode(int $barcodeId): ?bool
    {
        // 1. Encontrar el registro del código de barras.
        $barcodeEntry = ProductBarcode::find($barcodeId);

        // 2. Verificar si el código existe.
        if (!$barcodeEntry) {
            // Podrías lanzar una excepción o simplemente retornar false/null.
            // Retornamos null si no se encuentra.
            return null;
        }

        // 3. Eliminar el registro.
        // El método delete() retorna true si la eliminación fue exitosa.
        $wasDeleted = $barcodeEntry->delete();

        // Opcional: Si eliminas el código primario, puedes asignar un nuevo primario automáticamente
        // si el producto aún tiene otros códigos de barras.
        if ($barcodeEntry->is_primary && $barcodeEntry->product->barcodes()->count() > 0) {
            // Asigna el primer código restante como nuevo primario
            $barcodeEntry->product->barcodes()->first()->update(['is_primary' => true]);
        }

        return $wasDeleted;
    }

    /**
     * Actualiza los campos básicos de un producto.
     *
     * @param Product $product El modelo de Producto a actualizar.
     * @param array $data Los datos validados (name, description, stock, etc.).
     * @return Product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // 1. Limpiar los datos (opcional, pero útil)
        // Solo tomamos los campos que se permiten actualizar directamente
        $updatableData = Arr::only($data, [
            'name',
            'description',
            'stock',
            // Agrega aquí cualquier otro campo simple que pueda actualizarse
        ]);

        // 2. Aplicar la actualización
        $product->update($updatableData);

        // 3. Retornar el producto actualizado
        return $product;
    }

    /**
     * Sincroniza las listas de precios y los proveedores de un producto.
     * (Combina la lógica de addPrices del Controller)
     *
     * @param \App\Models\Product $product Instancia del producto.
     * @param array $priceLists Array de listas de precios con sus IDs y precios.
     * @param array $suppliers Array de proveedores con sus IDs y precios de compra.
     * @return \App\Models\Product
     */
    public function syncPricesAndSuppliers(Product $product, array $priceLists): Product
    {
        // Usamos una transacción para asegurar que, si falla una sincronización,
        // ninguna de las dos (precios o proveedores) se aplique.
        DB::transaction(function () use ($product, $priceLists) {
            // 1. Sincronizar Listas de Precios
            if (!empty($priceLists)) {
                $this->syncPriceLists($product, $priceLists);
            }
        });

        // Cargar las relaciones actualizadas para retornarlas
        $product->load(['priceLists']);

        return $product;
    }

    // ------------------------------------------------------------------
    //                         GD FUNCIONES
    // ------------------------------------------------------------------

    /**
     * Crea un recurso de imagen GD a partir de un archivo subido.
     */
    protected function createImageResourceFromUploadedFile(UploadedFile $file, string $extension)
    {
        $tempPath = $file->getRealPath();

        return match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($tempPath),
            'png' => imagecreatefrompng($tempPath),
            'gif' => imagecreatefromgif($tempPath),
            'webp' => imagecreatefromwebp($tempPath),
            default => null,
        };
    }

    /**
     * Guarda una imagen redimensionada (imagen principal).
     */
    protected function saveResizedImage($source, $srcW, $srcH, $maxDim, $fullPath, $extension, $maintainAspect = true)
    {
        // Cálculo de nuevas dimensiones
        if ($srcW <= $maxDim) {
            $newW = $srcW;
            $newH = $srcH;
        } else {
            $newW = $maxDim;
            $newH = $maintainAspect ? floor($srcH * ($maxDim / $srcW)) : $srcH;
        }

        $dest = imagecreatetruecolor($newW, $newH);

        // Pasos para la transparencia PNG
        if ($extension === 'png') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        // Capturar el contenido de la imagen
        ob_start();
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($dest, null, 85),
            'png' => imagepng($dest, null, 9),
            'gif' => imagegif($dest),
            'webp' => imagewebp($dest)
        };
        $imageContent = ob_get_clean();

        // Guardar en Storage y limpiar
        Storage::disk('public')->put($fullPath, $imageContent);
        imagedestroy($dest);

        return $fullPath;
    }

    /**
     * Guarda una miniatura cuadrada con crop (fit).
     */
    protected function saveCroppedThumbnail($source, $srcW, $srcH, $targetSize, $fullPath, $extension)
    {
        // Calcular las dimensiones para el crop (ajuste similar a 'fit')
        if ($srcW <= $targetSize && $srcH <= $targetSize) {
            // La imagen ya es más pequeña que la dimensión máxima. No se redimensiona.
            $newW = $srcW;
            $newH = $srcH;
        } else {
            // Calcula el ratio en base al lado más largo
            $ratio = max($srcW, $srcH) / $targetSize;

            // El lado más largo se ajusta a $maxDim
            $newW = floor($srcW / $ratio);
            $newH = floor($srcH / $ratio);
        }

        $dest = imagecreatetruecolor($newW, $newH);

        // Pasos para la transparencia PNG en el destino final
        if ($extension === 'png' ||  $extension === 'webp') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        // Copiar la parte central al destino (crop)

        // Capturar el contenido de la imagen
        ob_start();
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($dest, null, 85),
            'png' => imagepng($dest, null, 9),
            'gif' => imagegif($dest),
            'webp' => imagewebp($dest)
        };
        $imageContent = ob_get_clean();

        // Guardar en Storage y limpiar
        Storage::disk('public')->put($fullPath, $imageContent);
        imagedestroy($dest);

        return $fullPath;
    }
}

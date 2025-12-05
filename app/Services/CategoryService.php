<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * Obtiene todas las categorías con sus relaciones (padre e hijos).
     *
     * @return Collection
     */
    public function getAllCategories(): Collection
    {
        return Category::getTopLevelCategories();
    }

    /**
     * Crea una nueva categoría.
     *
     * @param array $data Contiene 'name' y opcionalmente 'parent_id'.
     * @return Category
     */
    public function createCategory(array $data): Category
    {
        // 1. Prepara los datos.
        // NOTA: El slug se generará automáticamente gracias al boot() method en el modelo Category.
        // Si no tuvieras el boot() en el modelo, lo harías aquí: 'slug' => Str::slug($data['name']),

        $data['parent_id'] = $data['parent_id'] ?? null;

        // 2. Crea la categoría.
        $category = Category::create($data);

        return $category;
    }

    /**
     * Busca y retorna una categoría específica.
     *
     * @param int $id
     * @return Category
     */
    public function getCategory(int $id): Category
    {
        // El controlador debe usar Route Model Binding, pero si no lo hiciera:
        $category = Category::findOrFail($id);

        $category->load('parent', 'children');

        return $category;
    }

    /**
     * Actualiza una categoría existente.
     *
     * @param Category $category El objeto Category obtenido por Route Model Binding.
     * @param array $data Contiene 'name' y opcionalmente 'parent_id'.
     * @return Category
     * @throws \Exception Si el parent_id es el mismo que el id de la categoría.
     */
    public function updateCategory(Category $category, array $data): Category
    {
        // 1. Validación de la jerarquía (evitar bucles infinitos)
        if (isset($data['parent_id']) && (int)$data['parent_id'] === $category->id) {
            throw new \Exception('Una categoría no puede ser su propia categoría padre.');
        }

        // 2. Actualiza los datos (el slug se regenerará si el nombre cambia, gracias al boot method)
        $category->update($data);

        return $category;
    }

    /**
     * Elimina una categoría.
     *
     * @param Category $category
     * @return void
     */
    public function deleteCategory(Category $category): void
    {
        // Gracias a la restricción onDelete('set null') en la migración:
        // Si esta categoría tiene hijos, sus parent_id se establecerán a NULL.
        // Si esta categoría está vinculada a productos, la vinculación en category_product se elimina (si usas onDelete('cascade') en la tabla pivot)

        $category->delete();
    }

    /**
     * Summary of getCategoryById
     * @param int $categoryId
     * @return Category
     */
    public function getCategoryById(int $categoryId): Category
    {
        // Buscar la categoría por ID y fallar si no existe (lanza 404 automáticamente en la API)
        $category = Category::findOrFail($categoryId);
        $category->load('parent', 'children');

        return $category;
    }
}

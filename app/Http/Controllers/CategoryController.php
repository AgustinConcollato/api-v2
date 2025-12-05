<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController
{
    protected CategoryService $categoryService;

    /**
     * Summary of __construct
     * @param \App\Services\CategoryService $categoryService
     */
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Summary of index
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $categories = $this->categoryService->getAllCategories();
        return response()->json($categories);
    }

    /**
     * Summary of show
     * @param \App\Models\Category $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Category $category)
    {
        try {
            $categoryWithDetails = $this->categoryService->getCategoryById($category->id);
            return response()->json($categoryWithDetails);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        }
    }

    /**
     * Summary of store
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        try {
            // Delegar la creación al servicio
            $category = $this->categoryService->createCategory($validated);
            return response()->json($category, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo crear la categoría.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of update
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Category $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Category $category)
    {
        $rules = [
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id',
        ];

        $params = [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto válida.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique' => 'Este nombre de categoría ya existe. Por favor, elija otro.',

            'parent_id.exists' => 'La categoría padre seleccionada no es válida o no existe.',
        ];

        $validated = $request->validate($rules, $params);

        try {
            $updatedCategory = $this->categoryService->updateCategory($category, $validated);

            return response()->json($updatedCategory);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo actualizar la categoría.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of destroy
     * @param \App\Models\Category $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Category $category)
    {
        try {
            $this->categoryService->deleteCategory($category);

            return response()->json(null, 204); // 204 No Content

        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo eliminar la categoría.', 'message' => $e->getMessage()], 500);
        }
    }
}

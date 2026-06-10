<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryController
{
    public function __construct(private CategoryService $categoryService) {}

    public function index()
    {
        $categories = $this->categoryService->getAllCategories();
        return response()->json($categories);
    }

    public function show(Category $category)
    {
        $categoryWithDetails = $this->categoryService->getCategoryById($category->id);
        return response()->json($categoryWithDetails);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->categoryService->createCategory($request->validated());

        return response()->json($category, 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $updatedCategory = $this->categoryService->updateCategory($category, $request->validated());

        $allCategories = $this->categoryService->getAllCategories();

        return response()->json([
            'category'   => $updatedCategory,
            'categories' => $allCategories
        ]);
    }

    public function destroy(Category $category)
    {
        $this->categoryService->deleteCategory($category);

        return response()->json(null, 204);
    }
}

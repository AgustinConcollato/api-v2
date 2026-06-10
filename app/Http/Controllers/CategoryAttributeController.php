<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryAttributeRequest;
use App\Models\Category;
use App\Models\CategoryAttribute;

class CategoryAttributeController
{
    public function index(Category $category)
    {
        $attributes = $category->attributes()->with('options')->get();
        return response()->json($attributes);
    }

    public function store(CategoryAttributeRequest $request, Category $category)
    {
        $validated = $request->validated();

        $attribute = $category->attributes()->create([
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'required'   => $validated['required'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (in_array($attribute->type, ['select', 'combo']) && !empty($validated['options'])) {
            foreach ($validated['options'] as $i => $value) {
                $attribute->options()->create(['value' => $value, 'sort_order' => $i]);
            }
        }

        return response()->json($attribute->load('options'), 201);
    }

    public function update(CategoryAttributeRequest $request, Category $category, CategoryAttribute $attribute)
    {
        if ($attribute->category_id !== $category->id) {
            return response()->json(['error' => 'El atributo no pertenece a esta categoría.'], 403);
        }

        $validated = $request->validated();

        $attribute->update([
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'required'   => $validated['required'] ?? $attribute->required,
            'sort_order' => $validated['sort_order'] ?? $attribute->sort_order,
        ]);

        if (in_array($attribute->type, ['select', 'combo']) && isset($validated['options'])) {
            $attribute->options()->delete();
            foreach ($validated['options'] as $i => $value) {
                $attribute->options()->create(['value' => $value, 'sort_order' => $i]);
            }
        }

        return response()->json($attribute->load('options'));
    }

    public function destroy(Category $category, CategoryAttribute $attribute)
    {
        if ($attribute->category_id !== $category->id) {
            return response()->json(['error' => 'El atributo no pertenece a esta categoría.'], 403);
        }

        $attribute->delete();
        return response()->json(null, 204);
    }
}

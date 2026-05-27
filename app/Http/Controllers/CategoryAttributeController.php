<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryAttributeController
{
    public function index(Category $category)
    {
        $attributes = $category->attributes()->with('options')->get();
        return response()->json($attributes);
    }

    public function store(Request $request, Category $category)
    {
        try {
            $validated = $request->validate([
                'name'       => 'required|string|max:100',
                'type'       => 'required|in:text,number,select,boolean,combo',
                'required'   => 'boolean',
                'sort_order' => 'integer|min:0',
                'options'    => 'array',
                'options.*'  => 'string|max:150',
            ]);

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
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo crear el atributo.', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Category $category, CategoryAttribute $attribute)
    {
        if ($attribute->category_id !== $category->id) {
            return response()->json(['error' => 'El atributo no pertenece a esta categoría.'], 403);
        }

        try {
            $validated = $request->validate([
                'name'       => 'required|string|max:100',
                'type'       => 'required|in:text,number,select,boolean,combo',
                'required'   => 'boolean',
                'sort_order' => 'integer|min:0',
                'options'    => 'array',
                'options.*'  => 'string|max:150',
            ]);

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
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo actualizar el atributo.', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Category $category, CategoryAttribute $attribute)
    {
        if ($attribute->category_id !== $category->id) {
            return response()->json(['error' => 'El atributo no pertenece a esta categoría.'], 403);
        }

        try {
            $attribute->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo eliminar el atributo.', 'message' => $e->getMessage()], 500);
        }
    }
}

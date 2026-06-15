<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHomeLayoutPresetRequest;
use App\Http\Requests\UpdateHomeLayoutPresetRequest;
use App\Services\HomeLayoutService;
use Illuminate\Http\Request;

class HomeLayoutController
{
    public function __construct(private HomeLayoutService $homeLayoutService) {}

    public function publicShow()
    {
        return response()->json($this->homeLayoutService->getPublished());
    }

    public function presetsIndex()
    {
        return response()->json($this->homeLayoutService->getDesigns());
    }

    public function presetsStore(StoreHomeLayoutPresetRequest $request)
    {
        $preset = $this->homeLayoutService->savePreset(
            $request->validated('name'),
            $request->validated('sections'),
        );

        return response()->json($preset, 201);
    }

    public function presetsUpdate(int $id, UpdateHomeLayoutPresetRequest $request)
    {
        $preset = $this->homeLayoutService->updatePreset(
            $id,
            $request->validated('name'),
            $request->validated('sections'),
        );

        return response()->json($preset);
    }

    public function presetsPublish(int $id)
    {
        return response()->json($this->homeLayoutService->publishPreset($id));
    }

    public function presetsDestroy(int $id)
    {
        try {
            $this->homeLayoutService->deletePreset($id);

            return response()->json(['message' => 'Diseño eliminado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function mediaIndex()
    {
        return response()->json($this->homeLayoutService->listMedia());
    }

    public function mediaStore(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'image.required' => 'La imagen es obligatoria.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'image.max' => 'La imagen no puede superar los 4 MB.',
        ]);

        try {
            $result = $this->homeLayoutService->uploadMedia($request->file('image'));

            return response()->json($result, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function mediaDestroy(int $id)
    {
        try {
            $this->homeLayoutService->deleteMedia($id);

            return response()->json(['message' => 'Imagen eliminada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

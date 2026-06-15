<?php

namespace App\Services;

use App\Models\HomeLayout;
use App\Models\HomeLayoutPreset;
use App\Models\MediaLibraryItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeLayoutService
{
    private const BANNERS_PATH = 'home/banners';
    private const BANNER_MAX_WIDTH = 1600;

    public function getLayout(): HomeLayout
    {
        return HomeLayout::firstOrCreate([], [
            'draft' => ['version' => 1, 'sections' => []],
            'published' => ['version' => 1, 'sections' => []],
        ]);
    }

    public function getPublished(): array
    {
        return $this->resolveUrls($this->getLayout()->published);
    }

    public function getDesigns(): array
    {
        $layout = $this->getLayout();

        return [
            'designs' => HomeLayoutPreset::orderByDesc('id')->get()
                ->map(fn(HomeLayoutPreset $preset) => [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'sections' => $this->resolveUrls(['sections' => $preset->sections])['sections'],
                    'created_at' => $preset->created_at,
                ])
                ->all(),
            'publishedId' => $layout->published_preset_id,
            'publishedAt' => $layout->published_at,
        ];
    }

    public function savePreset(string $name, array $sections): HomeLayoutPreset
    {
        return HomeLayoutPreset::create([
            'name' => $name,
            'sections' => $this->stripResolvedUrls($sections),
        ]);
    }

    public function publishPreset(int $id): array
    {
        $preset = HomeLayoutPreset::findOrFail($id);
        $layout = $this->getLayout();

        $layout->update([
            'published' => [
                'version' => 1,
                'sections' => $preset->sections,
            ],
            'published_at' => now(),
            'published_preset_id' => $preset->id,
        ]);

        $this->cleanOrphanImages($layout);

        return [
            'publishedId' => $preset->id,
            'publishedAt' => $layout->published_at,
        ];
    }

    public function deletePreset(int $id): void
    {
        if ($id === $this->getLayout()->published_preset_id) {
            throw new \InvalidArgumentException('No se puede eliminar el diseño que está en vivo.');
        }

        HomeLayoutPreset::findOrFail($id)->delete();
    }

    public function updatePreset(int $id, string $name, array $sections): array
    {
        $preset = HomeLayoutPreset::findOrFail($id);
        $preset->update([
            'name' => $name,
            'sections' => $this->stripResolvedUrls($sections),
        ]);

        return [
            'id' => $preset->id,
            'name' => $preset->name,
            'sections' => $this->resolveUrls(['sections' => $preset->sections])['sections'],
            'created_at' => $preset->created_at,
        ];
    }

    public function listMedia(): array
    {
        $disk = Storage::disk('public');

        return MediaLibraryItem::orderByDesc('id')->get()
            ->map(fn(MediaLibraryItem $item) => [
                'id' => $item->id,
                'path' => $item->path,
                'name' => $item->name,
                'url' => $disk->url($item->path),
                'created_at' => $item->created_at,
            ])
            ->all();
    }

    public function uploadMedia(UploadedFile $file): array
    {
        $path = $this->processBannerImage($file);

        $item = MediaLibraryItem::create([
            'path' => $path,
            'name' => $file->getClientOriginalName(),
        ]);

        return [
            'id' => $item->id,
            'path' => $item->path,
            'name' => $item->name,
            'url' => Storage::disk('public')->url($item->path),
            'created_at' => $item->created_at,
        ];
    }

    public function deleteMedia(int $id): void
    {
        $item = MediaLibraryItem::findOrFail($id);

        if (in_array($item->path, $this->collectReferencedPaths($this->getLayout()), true)) {
            throw new \InvalidArgumentException('La imagen está en uso y no se puede eliminar.');
        }

        Storage::disk('public')->delete($item->path);
        $item->delete();
    }

    private function processBannerImage(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $tempPath = $file->getRealPath();

        $source = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($tempPath),
            'png' => imagecreatefrompng($tempPath),
            'webp' => imagecreatefromwebp($tempPath),
            default => null,
        };

        if (!$source) {
            throw new \InvalidArgumentException('Formato de imagen no soportado. Usar JPG, PNG o WEBP.');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);

        if ($srcW <= self::BANNER_MAX_WIDTH) {
            $newW = $srcW;
            $newH = $srcH;
        } else {
            $newW = self::BANNER_MAX_WIDTH;
            $newH = (int) floor($srcH * (self::BANNER_MAX_WIDTH / $srcW));
        }

        $dest = imagecreatetruecolor($newW, $newH);

        if ($extension === 'png' || $extension === 'webp') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        ob_start();
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($dest, null, 85),
            'png' => imagepng($dest, null, 9),
            'webp' => imagewebp($dest),
        };
        $imageContent = ob_get_clean();

        imagedestroy($source);
        imagedestroy($dest);

        $path = self::BANNERS_PATH . '/' . (string) Str::uuid() . "_full.{$extension}";
        Storage::disk('public')->put($path, $imageContent);

        return $path;
    }

    /**
     * Agrega la URL absoluta a cada slide de banner del layout.
     */
    private function resolveUrls(array $layout): array
    {
        $layout['sections'] = array_map(function ($section) {
            if ($section['type'] === 'banner' && isset($section['settings']['slides'])) {
                $section['settings']['slides'] = array_map(function ($slide) {
                    $slide['url'] = Storage::disk('public')->url($slide['path']);
                    return $slide;
                }, $section['settings']['slides']);
            }
            return $section;
        }, $layout['sections'] ?? []);

        return $layout;
    }

    /**
     * Quita las URLs resueltas antes de persistir (solo se guarda el path).
     */
    private function stripResolvedUrls(array $sections): array
    {
        return array_map(function ($section) {
            if (($section['type'] ?? null) === 'banner' && isset($section['settings']['slides'])) {
                $section['settings']['slides'] = array_map(function ($slide) {
                    unset($slide['url']);
                    return $slide;
                }, $section['settings']['slides']);
            }
            return $section;
        }, $sections);
    }

    /**
     * Paths de banner referenciados en lo publicado y en todos los diseños guardados.
     */
    private function collectReferencedPaths(HomeLayout $layout): array
    {
        return collect([$layout->published['sections'] ?? []])
            ->merge(HomeLayoutPreset::pluck('sections'))
            ->filter()
            ->flatMap(fn($sections) => $sections)
            ->filter(fn($s) => ($s['type'] ?? null) === 'banner')
            ->flatMap(fn($s) => $s['settings']['slides'] ?? [])
            ->pluck('path')
            ->filter()
            ->unique()
            ->all();
    }

    /**
     * Borra archivos de home/banners/ no referenciados, no presentes en la biblioteca
     * de medios y con más de 1 hora de antigüedad. Best-effort.
     */
    private function cleanOrphanImages(HomeLayout $layout): void
    {
        try {
            $referenced = $this->collectReferencedPaths($layout);
            $libraryPaths = MediaLibraryItem::pluck('path')->all();

            $disk = Storage::disk('public');
            $threshold = now()->subHour()->getTimestamp();

            foreach ($disk->files(self::BANNERS_PATH) as $file) {
                if (
                    !in_array($file, $referenced, true) &&
                    !in_array($file, $libraryPaths, true) &&
                    $disk->lastModified($file) < $threshold
                ) {
                    $disk->delete($file);
                }
            }
        } catch (\Throwable) {
            // limpieza best-effort, no debe bloquear el guardado/publicación
        }
    }
}

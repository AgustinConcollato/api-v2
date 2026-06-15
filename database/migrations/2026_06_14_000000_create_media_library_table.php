<?php

use App\Models\HomeLayout;
use App\Models\HomeLayoutPreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('name');
            $table->timestamps();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library');
    }

    private function backfill(): void
    {
        $layout = HomeLayout::query()->first();
        $presets = HomeLayoutPreset::query()->get();

        $referenced = collect([$layout?->draft, $layout?->published])
            ->merge($presets->pluck('sections'))
            ->filter()
            ->flatMap(fn ($sections) => $sections['sections'] ?? $sections)
            ->filter(fn ($section) => ($section['type'] ?? null) === 'banner')
            ->flatMap(fn ($section) => $section['settings']['slides'] ?? [])
            ->pluck('path')
            ->filter()
            ->unique();

        $disk = Storage::disk('public');
        $existing = collect($disk->exists('home/banners') ? $disk->files('home/banners') : []);

        $paths = $referenced->merge($existing)->unique();

        foreach ($paths as $path) {
            if (!$disk->exists($path)) {
                continue;
            }

            \DB::table('media_library')->insertOrIgnore([
                'path' => $path,
                'name' => basename($path),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};

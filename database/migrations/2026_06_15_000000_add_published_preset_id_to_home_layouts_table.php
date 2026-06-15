<?php

use App\Models\HomeLayout;
use App\Models\HomeLayoutPreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->foreignId('published_preset_id')
                ->nullable()
                ->after('published_at')
                ->constrained('home_layout_presets')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Representa el home en vivo como un diseño guardado para que siempre
     * exista al menos un diseño y no se pierda el contenido publicado.
     */
    private function backfill(): void
    {
        $layout = HomeLayout::first();

        if (!$layout || $layout->published_preset_id) {
            return;
        }

        $sections = $layout->published['sections'] ?? [];

        if (empty($sections)) {
            return;
        }

        $preset = HomeLayoutPreset::create([
            'name' => 'Diseño en vivo',
            'sections' => $sections,
        ]);

        $layout->update(['published_preset_id' => $preset->id]);
    }

    public function down(): void
    {
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_preset_id');
        });
    }
};

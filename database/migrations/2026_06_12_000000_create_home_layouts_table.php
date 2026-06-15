<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_layouts', function (Blueprint $table) {
            $table->id();
            $table->json('draft');
            $table->json('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Layout inicial equivalente al home actual hardcodeado
        $defaultLayout = json_encode([
            'version' => 1,
            'sections' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'products',
                    'visible' => true,
                    'settings' => [
                        'title' => 'Ingresos',
                        'source' => 'new-arrivals',
                        'categoryId' => null,
                        'viewAllHref' => '/ingresos',
                        'limit' => 12,
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'promotions',
                    'visible' => true,
                    'settings' => [
                        'title' => '',
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'products',
                    'visible' => true,
                    'settings' => [
                        'title' => 'Más vendidos',
                        'source' => 'best-sellers',
                        'categoryId' => null,
                        'viewAllHref' => null,
                        'limit' => 12,
                    ],
                ],
            ],
        ]);

        DB::table('home_layouts')->insert([
            'draft' => $defaultLayout,
            'published' => $defaultLayout,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_layouts');
    }
};

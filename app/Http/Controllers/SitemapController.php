<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Str;

class SitemapController
{
    public function __invoke()
    {
        $siteUrl = rtrim(config('app.site_url', 'https://mayorista.concoypunto.com'), '/');
        $today = now()->toDateString();

        $urls = collect();

        // Rutas estáticas
        foreach (['/', '/buscar', '/ingresos'] as $path) {
            $urls->push($path);
        }

        // Categorías
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->get();

        foreach ($categories as $parent) {
            $parentSlug = $parent->slug ?: Str::slug($parent->name);
            $urls->push("/categoria/{$parentSlug}");

            foreach ($parent->children as $child) {
                $childSlug = $child->slug ?: Str::slug($child->name);
                $urls->push("/categoria/{$parentSlug}/{$childSlug}");
            }
        }

        // Productos publicados con stock
        Product::published()
            ->where(function ($q) {
                $q->where('stock', '>', 0)
                    ->orWhereHas('variants', fn($vq) => $vq->where('is_active', true)->where('stock', '>', 0));
            })
            ->select('id', 'name')
            ->chunk(500, function ($products) use ($urls) {
                foreach ($products as $product) {
                    $slug = Str::slug($product->name);
                    $path = $slug ? "/productos/{$slug}-{$product->id}" : "/productos/{$product->id}";
                    $urls->push($path);
                }
            });

        // Promociones activas y visibles
        $promotions = Promotion::active()
            ->where('show_on_web', true)
            ->select('id')
            ->get();

        foreach ($promotions as $promo) {
            $urls->push("/promocion/{$promo->id}");
        }

        // Generar XML
        $entries = $urls->map(fn($path) =>
            "  <url><loc>{$siteUrl}{$path}</loc><lastmod>{$today}</lastmod></url>"
        )->implode("\n");

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$entries}\n</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}

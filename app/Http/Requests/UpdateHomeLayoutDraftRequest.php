<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomeLayoutDraftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sections' => 'present|array',
            ...self::sectionsRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'sections.present' => 'Las secciones son obligatorias.',
            ...self::sectionsMessages(),
        ];
    }

    /**
     * Reglas de validación de cada sección, reutilizadas por StoreHomeLayoutPresetRequest.
     */
    public static function sectionsRules(): array
    {
        return [
            'sections.*.id'                     => 'required|string',
            'sections.*.type'                   => 'required|in:banner,products,promotions,text',
            'sections.*.visible'                => 'required|boolean',
            'sections.*.settings'               => 'present|array',

            // products
            'sections.*.settings.title'         => 'nullable|string|max:255',
            'sections.*.settings.source'        => 'required_if:sections.*.type,products|in:new-arrivals,best-sellers,category,keyword',
            'sections.*.settings.categoryId'    => 'nullable|integer|exists:categories,id',
            'sections.*.settings.keyword'       => 'nullable|string|max:255',
            'sections.*.settings.viewAllHref'   => 'nullable|string|max:255',
            'sections.*.settings.limit'         => 'nullable|integer|between:1,24',

            // banner (puede guardarse sin imágenes todavía)
            'sections.*.settings.slides'        => 'nullable|array',
            'sections.*.settings.slides.*.id'   => 'required|string',
            'sections.*.settings.slides.*.path' => 'required|string|starts_with:home/banners/',
            'sections.*.settings.slides.*.link' => 'nullable|string|max:2048',
            'sections.*.settings.autoplayMs'    => 'nullable|integer|between:2000,30000',

            // promotions
            'sections.*.settings.promotionId'   => 'nullable|uuid|exists:promotions,id',

            // text
            'sections.*.settings.body'          => 'nullable|string|max:5000',
        ];
    }

    /**
     * Mensajes de validación de cada sección, reutilizados por StoreHomeLayoutPresetRequest.
     */
    public static function sectionsMessages(): array
    {
        return [
            'sections.*.type.required'                    => 'Cada sección debe tener un tipo.',
            'sections.*.type.in'                          => 'El tipo de sección debe ser: banner, products, promotions o text.',
            'sections.*.visible.required'                 => 'Cada sección debe indicar si es visible.',
            'sections.*.settings.source.required_if'      => 'Las secciones de productos deben tener un origen (new-arrivals, best-sellers, category o keyword).',
            'sections.*.settings.source.in'               => 'El origen de productos debe ser: new-arrivals, best-sellers, category o keyword.',
            'sections.*.settings.categoryId.exists'       => 'La categoría seleccionada no existe.',
            'sections.*.settings.limit.between'           => 'El límite de productos debe estar entre 1 y 24.',
            'sections.*.settings.slides.*.path.required'  => 'Cada imagen del banner debe tener una ruta.',
            'sections.*.settings.slides.*.path.starts_with' => 'Ruta de imagen de banner inválida.',
            'sections.*.settings.promotionId.exists'      => 'La promoción seleccionada no existe.',
            'sections.*.settings.body.max'                => 'El texto no puede superar los 5000 caracteres.',
        ];
    }
}

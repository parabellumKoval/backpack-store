<?php

// src/Services/Vertical/AttributeResolver.php
namespace Backpack\Store\app\Services\Variant\Vertical;

use Backpack\Store\app\Models\Product;

class AttributeResolver
{
    /** какие поля наследуем */
    private array $inherit = ['brand_id','category_id','name','slug','image','images'];

    public function value(Product $p, string $field)
    {
        $locale = app()->getLocale(); // или Store::context()->locale ?? app()->getLocale()

        $isTrans = method_exists($p, 'isTranslatableAttribute')
            && $p->isTranslatableAttribute($field);

        // 1) Сначала пробуем взять у ребёнка
        if ($isTrans) {
            if ($p->hasTranslation($field, $locale)) {
                $val = $p->getTranslation($field, $locale, true); // с fallback
                if ($val !== null && $val !== '') return $val;
            }
        } else {
            $val = $p->getAttribute($field); // с мутаторами/кастами
            if ($val !== null && $val !== '') return $val;
        }

        // 2) Если ребёнок пуст — берём у родителя
        if (!$p->parent_id) return $val ?? null;

        $parent = $p->relationLoaded('parent') ? $p->parent : $p->parent()->first();

        if ($isTrans) {
            return $parent?->getTranslation($field, $locale, true);
        }

        return $parent?->getAttribute($field);
    }

    public function presentation(Product $p): array
    {
        return [
            'brand_id'    => $this->value($p, 'brand_id'),
            'category_id' => $this->value($p, 'category_id'),
            'name'        => $this->value($p, 'name'),
            'short_name'  => $p->short_name,
            'slug'        => $this->value($p, 'slug'),
            'image'       => $this->value($p, 'image'),
            'images'      => $this->value($p, 'images'),
            'popularity'  => (int) ($p->popularity ?? 0),
        ];
    }
}

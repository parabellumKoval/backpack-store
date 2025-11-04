<?php

namespace Backpack\Store\app\Services\Variant\Vertical;


use Backpack\Store\app\Models\Product;
use Illuminate\Database\Eloquent\Relations\Relation;

class EffectiveProduct
{
    public function __construct(
        private Product $p,
        private bool $raw = false,
        private bool $preferParent = false,
        private ?string $locale = null, // на случай локали из контекста
    ) {
        $this->locale = $this->locale ?: app()->getLocale();
    }

    public function __get(string $key)
    {
        // Перехват связей
        if ($this->isRelation($key)) {
            return $this->resolveRelation($key);
        }
        // Атрибут
        return $this->resolveAttribute($key);
    }

    public function __call($m, $a) {
        // 1) Отношения в __call не трогаем — прямая прокся на модель
        if ($this->isRelation($m)) {
            return $this->p->$m(...$a);
        }

        // 2) Любой обычный метод обрабатываем общей логикой
        return $this->resolveMethod($m, $a);
    }

    /* ===== helpers ===== */

    private function isRelation(string $name): bool
    {
        return method_exists($this->p, $name) && $this->p->{$name}() instanceof Relation;
    }

    private function resolveRelation(string $name)
    {
        // Родитель-сначала?
        if ($this->preferParent && $this->p->parent_id) {
            $parent = $this->p->relationLoaded('parent') ? $this->p->parent : $this->p->parent()->with($name)->first();
            return $parent?->$name;
        }

        // Иначе — связь текущей модели
        if ($this->p->relationLoaded($name)) {
            return $this->p->getRelation($name);
        }
        return $this->p->$name; // лениво загрузит
    }

    private function resolveAttribute(string $field)
    {
        // Родитель-сначала
        if ($this->preferParent && $this->p->parent_id) {
            $parent = $this->p->relationLoaded('parent') ? $this->p->parent : $this->p->parent()->first();
            $pv = $parent ? $this->fetch($parent, $field) : null;
            if ($this->filled($pv)) return $pv;
            return $this->fetch($this->p, $field);
        }

        // Ребёнок-сначала
        $cv = $this->fetch($this->p, $field);
        if ($this->filled($cv)) return $cv;

        if ($this->p->parent_id) {
            $parent = $this->p->relationLoaded('parent') ? $this->p->parent : $this->p->parent()->first();
            return $parent ? $this->fetch($parent, $field) : $cv;
        }

        return $cv;
    }

    private function resolveMethod(string $m, array $a)
    {
        // Получаем родителя, если есть
        $parent = $this->p->parent_id
            ? ($this->p->relationLoaded('parent') ? $this->p->parent : $this->p->parent()->first())
            : null;

        // Определяем порядок обхода: родитель-сначала или ребёнок-сначала
        $first  = $this->preferParent ? $parent   : $this->p;
        $second = $this->preferParent ? $this->p  : $parent;

        $called = false;
        $last   = null;

        // Пытаемся вызвать на первом объекте, если можно напрямую вызвать метод
        if ($first && is_callable([$first, $m])) {
            $called = true;
            $v = $first->$m(...$a);
            if ($this->filled($v)) {
                return $v;
            }
            $last = $v; // запомним последнее значение даже если "пустое"
        }

        // Пытаемся вызвать на втором объекте
        if ($second && is_callable([$second, $m])) {
            $called = true;
            $v = $second->$m(...$a);
            if ($this->filled($v)) {
                return $v;
            }
            $last = $v;
        }

        // Если ни там, ни там метод напрямую не вызвался (динамические скоупы/магия Eloquent),
        // отдаём на откуп исходной модели, чтобы её __call обработал это как нужно.
        if (!$called) {
            return $this->p->$m(...$a);
        }

        // Если вызывали, но везде "пусто", вернём последнее полученное значение.
        return $last;
    }


    private function fetch(Product $m, string $field)
    {
        if ($this->raw) {
            // «Сырой» JSON/строка из БД, без кастов/аксессоров/Spatie
            return $m->getRawOriginal($field);
        }

        // Учитываем Spatie Translatable, если есть
        $isTrans = method_exists($m, 'isTranslatableAttribute') && $m->isTranslatableAttribute($field);
        if ($isTrans) {
            return $m->getTranslation($field, $this->locale, true);
        }

        return $m->getAttribute($field); // с аксессорами/кастами
    }

    private function filled($v): bool
    {
        // «непустое» значение — пригодно для использования
        if (is_array($v)) return !empty($v);
        return $v !== null && $v !== '';
    }
}

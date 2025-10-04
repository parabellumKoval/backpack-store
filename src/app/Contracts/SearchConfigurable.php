<?php

namespace Backpack\Store\app\Contracts;

interface SearchConfigurable
{
    /** Базовое имя индекса (без суффикса страны) */
    public static function searchIndexBase(): string;

    /** Простые поля (одноязычные) */
    public static function searchableAttributes(): array; // напр. ['name','attrs_text']

    /** Переводимые поля (база без суффиксов локалей) */
    public static function searchableTranslatableAttributes(): array; // напр. ['name','attrs_text']

    /** Непереводимые фильтруемые поля */
    public static function filterableAttributes(): array;   // напр. ['in_stock','country_code','category_ids']

    /** Поля сортировки */
    public static function sortableAttributes(): array;     // напр. ['price','popularity','created_at']

    /** distinctAttribute либо null */
    public static function distinctAttribute(): ?string;   // напр. 'group_id' или null

    /** Дефолтные правила ранжирования (если нужна специфика модели) */
    public static function searchRankingRules(): array; // опционально
}

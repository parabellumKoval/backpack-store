<?php 
namespace Backpack\Store\app\Services\Search;

use Illuminate\Database\Eloquent\Model;
use Backpack\Store\app\Contracts\SearchConfigurable;

abstract class SearchConfigurableAbstract extends Model implements SearchConfigurable
{
  public function toSearchableArray(): array
  {
    return $this->margeSearchables();
  }

  public function margeSearchables() {
    $base = [];
    $attributes = static::searchableAttributes();
    $translatablesMap = static::searchableTranslatableAttributes();
    $languages = \Settings::get('backpack.crud.locales');

    $translatableFields = [];
    foreach ($translatablesMap as $k => $v) {
        $translatableFields[] = is_int($k) ? $v : $k;
    }

    // Translatable Attributes
    foreach ($translatableFields as $field) {
        foreach ($languages as $code => $name) {
            $base["{$field}_{$code}"] = $this->resolveTranslatable($field, $code);
        }
    }

    // Simple Attributes
    foreach($attributes as $field) {
        $base[$field] = $this->{$field};
    }

    return $base;
  }

  protected function resolveTranslatable(string $field, string $locale)
  {
      $map = static::searchableTranslatableAttributes();

      // Если указан резолвер (строковый ключ)
      if (array_key_exists($field, $map)) {
          $resolver = $map[$field];

          if (is_string($resolver) && method_exists($this, $resolver)) {
              return $this->{$resolver}($locale);
          }

          if (is_callable($resolver)) { // на случай если вернёшь замыкание
              return $resolver($this, $locale);
          }
      }

      // Обычный путь — через Spatie
      return $this->getTranslation($field, $locale);
  }

  abstract public function scoutShouldBeSearchable(): bool;
  abstract public function searchableAs(): string;

  abstract public static function searchIndexBase(): string;
  abstract public static function searchableAttributes(): array;
  abstract public static function searchableTranslatableAttributes(): array;
  abstract public static function filterableAttributes(): array;
  abstract public static function sortableAttributes(): array;
  abstract public static function distinctAttribute(): ?string;
  abstract public static function searchRankingRules(): array;
}
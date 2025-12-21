<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Brand;

use Backpack\Store\app\Contracts\QueryService;

abstract class AbstractQueryService implements QueryService
{
    protected $query;
    protected Request $request;



    /**
     * Применение всех фильтров за исключением указанных
     *
     * @param array|string $excludeFilters
     * @return self
     */
    public function applyAllFiltersExcept($excludeFilters = []): self
    {
      // Приводим $excludeFilters к массиву
      $excludeFilters = is_string($excludeFilters)? (array) $excludeFilters: $excludeFilters;

      // Список всех доступных фильтров
      $availableFilters = [
          'startQuery' => 'startQuery',
          'categories' => 'filterByCategories',
          'brandSlug' => 'filterByBrandSlug',
          'brands' => 'filterByBrands',
          'price' => 'filterByPrice',
          'attributes' => 'filterByAttributes',
          'selections' => 'filterBySelections',
          'search' => 'filterBySearch',
      ];

      // Применяем все фильтры, кроме исключенных
      foreach ($availableFilters as $filterKey => $method) {
        if(is_int($excludeFilters)) {
          $this->$method($filterKey === 'attributes'? $excludeFilters: null);
        }elseif (is_array($excludeFilters) && !in_array($filterKey, $excludeFilters)) {
          $this->$method();
        }
      }

      return $this;
    }


  
    /**
     * prepareAttributes
     *
     * @param  mixed $values
     * @return void
     */
    public function prepareAttributes($data) {
      if(empty($data) || !is_array($data)) {
        return [];
      }

      $chunks = array_values($data);
      $normalized = [];
      $currentAttrId = null;

      foreach ($chunks as $chunk) {
        if(!is_array($chunk)) {
          continue;
        }

        if(array_key_exists('attr_id', $chunk) && $chunk['attr_id'] !== null && $chunk['attr_id'] !== '') {
          $currentAttrId = (int)$chunk['attr_id'];

          if(!isset($normalized[$currentAttrId])) {
            $normalized[$currentAttrId] = ['attr_id' => $currentAttrId];
          }
        }

        if($currentAttrId === null) {
          continue;
        }

        foreach ($chunk as $key => $value) {
          if($key === 'attr_id') {
            continue;
          }

          if($key === 'attr_value_id') {
            $existing = $normalized[$currentAttrId][$key] ?? [];
            $existing = is_array($existing) ? $existing : [$existing];
            $value = is_array($value) ? $value : [$value];
            $value = array_filter($value, static function($item) {
              return $item !== null && $item !== '';
            });

            $normalized[$currentAttrId][$key] = array_merge($existing, $value);
            continue;
          }

          $normalized[$currentAttrId][$key] = $value;
        }
      }

      $attrs = [];

      foreach ($normalized as $attr) {
        $attrId = $attr['attr_id'] ?? null;

        if(!$attrId) {
          continue;
        }

        if(isset($attr['from']) && isset($attr['to'])) {
          $attrs[] = [
            'attr_id' => $attrId,
            'to' => floatval($attr['to']),
            'from' => floatval($attr['from']),
          ];

          continue;
        }

        if(!empty($attr['attr_value_id'])) {
          $valueIds = is_array($attr['attr_value_id']) ? $attr['attr_value_id'] : [$attr['attr_value_id']];
          $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

          if(!empty($valueIds)) {
            $attrs[] = [
              'attr_id' => $attrId,
              'attr_value_id' => $valueIds,
            ];
          }

          continue;
        }

        if(isset($attr['value']) && $attr['value'] !== '') {
          $attrs[] = [
            'attr_id' => $attrId,
            'value' => floatval($attr['value']),
          ];
        }
      }

      return $attrs;
    }

    /**
     * Method getQuery
     *
     * @return void
     */
    public function getQuery() {
      return $this->query;
    }

    /** Должны реализовать наследники */

    abstract public function startQuery(): self;
    abstract public function filterBySelections(): self;
    abstract public function filterByAttributes($except_attribute_id = null): self;
    abstract public function filterByCategories(): self;
    abstract public function filterByBrands(): self;
    abstract public function filterByBrandSlug(): self;
    abstract public function filterByPrice(): self;
    abstract public function filterBySearch(): self;

    // abstract public function prepareAttributes();
    // abstract public function getAttributesQuery();
    abstract public function sorting(): self;
    abstract public function getProducts();
  
}

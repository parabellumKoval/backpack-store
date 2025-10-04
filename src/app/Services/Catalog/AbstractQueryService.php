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
      $attrs = [];
      $values = array_values($data);

      for($i = 0; $i < count($values); $i++) {
        $attr = $values[$i];

        // if attribute is not isset yet
        if(!isset($attrs[$attr['attr_id']])) {
          
          // if attribute type is number (range)
          if(isset($attr['from']) && isset($attr['to'])){
            $attrs[$attr['attr_id']] = [
              'attr_id' => (int)$attr['attr_id'],
              'to' => floatval($attr['to']),
              'from' => floatval($attr['from']),
            ];
          }
          // if attribute type is checkbox / radio
          elseif(isset($attr['attr_value_id'])) {
            $attrs[$attr['attr_id']] = [
              'attr_id' => (int)$attr['attr_id'],
              'attr_value_id' => [(int)$attr['attr_value_id']]
            ];
          
          }
          // if attribute type is number (strict)
          else {
            $attrs[$attr['attr_id']] = [
              'attr_id' => (int)$attr['attr_id'],
              'value' => floatval($attr['value']),
            ];
          }
        }
        // addding values to array
        else {
          if(isset($attr['attr_value_id'])) {
            $attrs[$attr['attr_id']]['attr_value_id'][] = (int)$attr['attr_value_id'];
          }else {
            // multiple values allowed only for checkbox / radio
            continue;
          }
        }
      }

      return array_values($attrs);
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

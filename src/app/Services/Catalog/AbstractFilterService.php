<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Brand;

use Backpack\Store\app\Contracts\FilterService;

abstract class AbstractFilterService implements FilterService
{

    protected $query;
    protected Request $request;
    protected string $country;
    protected $product_service;

    protected string $itemsTableName;


  
    /**
     * Method getFiltersCount
     *
     * @return void
     */
    public function getFiltersCount(): array
    {
      $with_filter = $this->request->input('with_filter_count', []);
      $filters = [];

      if(in_array('selections', $with_filter)) {
        $filters['selections'] = $this->countSelections();
      }

      if(in_array('brands', $with_filter)) {
        $filters['brand'] = $this->countBrands();
      }
      
      if(in_array('price', $with_filter)) {
        $filters['price'] = $this->countPrices();
      }

      if(in_array('attributes', $with_filter)) {
        $attributes = $this->countAttributes() ?? [];
        $filters = $filters + $attributes;
      }

      return $filters;
    }

    /**
     * Method getFiltersData
     *
     * @return void
     */
    public function getFiltersData(): array
    {
      $with_filter = $this->request->input('with_filter', []);
      $filters = [];

      if(in_array('selections', $with_filter)) {
        $filters[] = [
          'id' => 'selections',
          'name' =>  __('backpack-store::filter.label.selections'),
          'si' => null,
          'isOpen' => true,
          'noSearch' => true,
          'isNarrowing' => true,
          'type' => 'checkbox',
          'values' => $this->getSelectionValues()
        ];
      }

      if(in_array('brands', $with_filter)) {
        $filters[] = [
          'id' => 'brand',
          'name' => __('backpack-store::filter.label.brand'),
          'si' => null,
          'isOpen' => true,
          'noMeta' => false,
          'type' => 'brand',
          'values' => $this->getBrandValues()
        ];
      }

      if(in_array('attributes', $with_filter)) {
        $attrs = $this->getAttributes();

        foreach($attrs as $attr){
          $filters[] = [
            'id' => $attr['id'],
            'name' => $attr['name'],
            'si' => $attr['si'] ?? null,
            'isOpen' => false,
            'type' => $attr['type'],
            'values' => $attr['values']
          ];
        }
      }
      
      if(in_array('price', $with_filter)) {
        $filters[] = [
          'id' => 'price',
          'name' => __('backpack-store::filter.label.price'),
          'si' => __('backpack-store::filter.label.grn'),
          'isOpen' => true,
          'type' => 'number'
        ];
      }

      return $filters;
    }

    /**
     * Method getBrandValues
     *
     * @return void
     */
    private function getBrandValues() {
      $query = (clone $this->query)
        ->select('br.id', 'br.name', 'br.slug', 'br.images')
        ->join('ak_brands as br', $this->itemsTableName . '.brand_id', '=', 'br.id')
        ->groupBy('br.id')
        ->get();

      $sortBy = 'name';

      $brands = Brand::hydrate($query->sortBy($sortBy)->all());
      
      $resource = \Settings::get('dress.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandFilterResource');
      return $resource::collection($brands);
    }


    /**
     * Method getSelectionValues
     *
     * @return void
     */
    private function getSelectionValues() {
      return [
        'with_sales' => [
          'id' => 'with_sales',
          'value' => __('backpack-store::filter.selections.with_sales')
        ],
        'top_price' => [
          'id' => 'top_price',
          'value' => __('backpack-store::filter.selections.top_price')
        ],
        'top_sales' => [
          'id' => 'top_sales',
          'value' => __('backpack-store::filter.selections.top_sales')
        ],
        'with_rating' => [
          'id' => 'with_rating',
          'value' => __('backpack-store::filter.selections.with_rating')
        ],
        'in_stock' => [
          'id' => 'in_stock',
          'value' => __('backpack-store::filter.selections.in_stock')
        ]
      ];
    }



  
    /**
     * Method countAttributes
     *
     * @return void
     */
    protected function countAttributes() {
      // $clear_q = clone $this->product_service;

      $query_attrs = $this->product_service->prepareAttributes($this->request->input('attrs', []));
      $active_attr_ids = array_column($query_attrs, 'attr_id');
      $result = [];

      if(empty($query_attrs)) {
        $product_query = $this->product_service->applyAllFiltersExcept()->getQuery();
        $result = $this->calculateAllAttributes($product_query);
      }else {
        foreach ($query_attrs as $active_attr) {
          $attr_id = $active_attr['attr_id'];
          $product_query = $this->product_service->startQuery()->applyAllFiltersExcept($attr_id)->getQuery();
          $single_result = $this->calculateSingleAttribute(clone $product_query, $active_attr);
          $result = $result + $single_result;
        }

        
        $product_query = $this->product_service->startQuery()->applyAllFiltersExcept()->getQuery();
        $all_attributes = $this->calculateAllAttributes($product_query);

        foreach ($all_attributes as $attr_id => $values) {
          if (!in_array($attr_id, $active_attr_ids)) {
            $result[$attr_id] = $values;
          }
        }
      }

      return $result;
    }

    /** Должны реализовать наследники */

    abstract protected function getAttributes();

    abstract protected function countSelections(): array;
    abstract protected function countBrands(): array;
    abstract protected function countPrices(): array;
    // abstract protected function countAttributes();
    abstract protected function calculateAllAttributes($product_query);
    abstract protected function calculateSingleAttribute($product_query, $active_attr);
  
}

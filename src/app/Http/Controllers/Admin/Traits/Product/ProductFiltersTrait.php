<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits\Product;

trait ProductFiltersTrait
{
  protected function setupFilters() {

      /* The above PHP code is adding a filter for the "brand" field in a CRUD (Create, Read, Update,
      Delete) interface. The filter allows users to select a brand from a dropdown list. If the
      user selects the option "🔴 Без бренда" (translation: "🔴 No brand"), the code filters the
      query to show only records where the "brand_id" is null. If a specific brand is selected,
      the code filters the query to show only records with that specific "brand_id". */
      $this->crud->addFilter([
        'name' => 'brand',
        'label' => 'Бренд',
        'type' => 'select2',
      ], function(){
        $list = ['empty' => '🔴 Без бренда'] + $this->filter_brands;
        return $list;
      }, function($id){
        if($id === 'empty') {
          $this->crud->query->where('brand_id', '=', null);
        }else {
          $this->crud->query->where('brand_id', $id);
        }
      });

      /* The above PHP code snippet is adding a filter for a category in a CRUD (Create, Read,
      Update, Delete) interface. The filter allows users to select a category from a dropdown
      list. */
      $this->crud->addFilter([
        'name' => 'category',
        'label' => 'Категория',
        'type' => 'select2',
      ], function(){
        $list = ['empty' => '🔴 Без категории'] + $this->filter_categories;
        return $list;
      }, function($id){
        if($id === 'empty') {
          $this->crud->query->has('categories', '=', 0);
        }else {
          $this->crud->query->whereHas('categories', function ($query) use ($id) {
              $query->where('category_id', $id);
          });
        }
      });


      /* The above PHP code snippet is adding a filter to a CRUD (Create, Read, Update, Delete)
      interface. The filter is for the 'is_active' field and is displayed as a select dropdown
      with two options: '🔴 Не активный' (Not active) and '🟢 Активный' (Active). When a user
      selects an option, the query will filter the results based on the selected 'is_active'
      value. */
      $this->crud->addFilter([
        'name' => 'is_active',
        'label' => 'Активный',
        'type' => 'select2',
      ], function(){
        return [
          0 => '🔴 Не активный',
          1 => '🟢 Активный',
        ];
      }, function($is_active){
        $this->crud->query->where('is_active', $is_active);
      });


      /* The above PHP code is adding a filter named 'modifications' to a CRUD (Create, Read, Update,
      Delete) interface. This filter is a select2 type filter with options 'Без модификаций'
      (Without modifications) and 'С модификациями' (With modifications). */
      $this->crud->addFilter([
        'name' => 'modifications',
        'label' => 'Модификации',
        'type' => 'select2',
      ], function(){
        return [
          0 => 'Без модификаций',
          1 => 'С модификациями',
        ];
      }, function($modifications){
        if($modifications) {
          $this->crud->query->has('parent')->orHas('children');
        }else {
          $this->crud->query->has('parent', '=', 0)->has('children', '=', 0);
        }
      });


      /* The above PHP code is defining a filter named 'translation' for a CRUD (Create, Read,
      Update, Delete) operation. The filter allows users to select a translation option from a
      dropdown list. */
      $this->crud->addFilter([
        'name' => 'translation',
        'label' => 'Перевод',
        'type' => 'select2',
      ], function(){
        $al = array_map(function($item) {
          return 'Нет ' . $item;
        }, $this->available_languages);

        $list = [
          0 => 'Нет (какого-то)',
          1 => 'Есть (все)'] + $al;

        return $list;
      }, function($translation){
        if($translation === '0') {
          $this->crud->query->where(function($query) {
            foreach($this->langs_list as $index => $lang_key) {
              $function = $index === 0? 'whereRaw': 'orWhereRaw';
              $query->{$function}('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) < ? ', 150);
              $query->{$function}('JSON_EXTRACT(content, "$.' . $lang_key . '") IS NULL');
            }
          });
        }else if($translation === '1') {
          $this->crud->query->where(function($query) {
            foreach($this->langs_list as $index => $lang_key) {
              $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) >= ? ', 150);
            }
          });
        }else {
          $this->crud->query->where(function($query) use($translation) {
            $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $translation . '")) < ? ', 150)
                ->orWhereRaw('JSON_EXTRACT(content, "$.' . $translation . '") IS NULL');
          });
        }
      });

      /* The above PHP code snippet is adding a filter named 'filles' to a CRUD (Create, Read,
      Update, Delete) interface. This filter allows users to select the quality of data entry from
      a dropdown list with options for 'низкое' (low), 'среднее' (medium), and 'высокое' (high). */

      $this->crud->addFilter([
        'name' => 'filles',
        'label' => 'Качество заполнения',
        'type' => 'select2',
      ], function(){
        return [
          0 => 'низкое',
          1 => 'среднее',
          2 => 'высокое',
        ];
      }, function($filles){
        if($filles == 0) {
          $this->crud->query->fillQualityLow();
        }else if($filles == 1) {
          $this->crud->query->fillQualityNormal();
        }else if($filles == 2) {
          $this->crud->query->fillQualityHight();
        }
      });


      /* The above PHP code is adding a filter for a CRUD (Create, Read, Update, Delete) operation. The
      filter is for checking the availability of a product in stock. */
      $this->crud->addFilter([
        'name' => 'in_stock',
        'label' => 'Наличие',
        'type' => 'select2',
      ], function(){
        return [
          0 => '🔴 Нет в наличие',
          1 => '🟢 В наличие',
        ];
      }, function($in_stock){
        if($in_stock == 0) {
          $this->crud->query->where(function($query) {
            $query->whereDoesntHave('suppliers', function ($query) {
              $query->where('in_stock', '>', 0);
            })->orHas('suppliers', '=', 0);
          });
        }else {
          $this->crud->query->whereHas('suppliers', function ($query) {
            $query->where('in_stock', '>', 0);
          });
        }
      });
      

      /* The above PHP code snippet is adding a filter for a price range in a CRUD (Create, Read,
      Update, Delete) system. When this filter is applied, it will filter the data based on the
      price range specified by the user. */
      $this->crud->addFilter([
        'name' => 'price',
        'label' => 'Цена',
        'type' => 'range',
      ], false, function($value){
        $range = json_decode($value);
        
        if ($range->from) {
          $this->crud->addClause('whereHas', 'sp', function($query) use ($range) {
            $query->where('in_stock', '>', 0)->where('price', '>=', $range->from);
          });
        }
        if ($range->to) {
          $this->crud->addClause('whereHas', 'sp', function($query) use ($range) {
            $query->where('in_stock', '>', 0)->where('price', '<=', $range->to);
          });
        }
      });

      /* The above PHP code is adding a filter to a CRUD (Create, Read, Update, Delete) interface
      based on a condition from the configuration file. If the configuration setting
      'backpack.store.supplier.enable' is true, then a filter for selecting suppliers is added.
      The filter allows users to filter data based on the supplier associated with it. The filter
      includes an option for selecting records without a supplier ('🔴 Без поставщика') and a list
      of suppliers to choose from. Depending on the selected supplier, the query is modified to
      filter records accordingly. If 'empty' is */
      if(\Settings::get('dress.supplier.enable')) {
        $this->crud->addFilter([
          'name' => 'supplier',
          'label' => 'Поставщик',
          'type' => 'select2',
        ], function(){
          $list = ['empty' => '🔴 Без поставщика'] + $this->suppliers_list;
          return $list;
        }, function($id){
          if($id === 'empty') {
            $this->crud->query->has('suppliers', '=', 0);
          }else {
            $this->crud->query->whereHas('suppliers', function ($query) use ($id) {
              $query->where('supplier_id', $id);
            });
          }
        });
      }
  }
}
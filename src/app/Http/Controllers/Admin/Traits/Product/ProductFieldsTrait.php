<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits\Product;

use Backpack\Store\app\Models\Category;

trait ProductFieldsTrait
{
    protected function setupFields()
    {
        // IS ACTIVE
        $this->crud->addField([
            'name' => 'is_active',
            'label' => trans('backpack-store::product-field.fields.is_active'),
            'type' => 'boolean',
            'default' => '1',
            'tab' => trans('backpack-store::product-field.tabs.main')
        ]);
  
        // CODE
        if(config('backpack.store.product.code.enable', true)) {
            $this->crud->addField([
                'name' => 'code',
                'label' => trans('backpack-store::product-field.fields.code.label'),
                'wrapper'   => [ 
                'class' => 'form-group col-md-6'
                ],
                'hint' => trans('backpack-store::product-field.fields.code.hint'),
                'tab' => trans('backpack-store::product-field.tabs.main')
            ]);
        }

        // BARCODE
        if(!config('backpack.store.supplier.enable', false)) {
            $this->crud->addField([
                'name' => 'defaultSupplier[barcode]',
                'label' => trans('backpack-store::product-field.fields.barcode'),
                'wrapper'   => [ 
                'class' => 'form-group col-md-6'
                ],
                'value' => $this->entry->defaultSupplier['barcode'] ?? null,
                'tab' => trans('backpack-store::product-field.tabs.main')
            ]);
        }

        // NAME
        $this->crud->addField([
            'name' => 'name',
            'label' => trans('backpack-store::product-field.fields.name'),
            'type' => 'text',
            'tab' => trans('backpack-store::product-field.tabs.main')
        ]);

        
        // SLUG
        $this->crud->addField([
            'name' => 'slug',
            'label' => trans('backpack-store::product-field.fields.slug.label'),
            'hint' => trans('backpack-store::product-field.fields.slug.hint'),
            'tab' => trans('backpack-store::product-field.tabs.main')
        ]);


        if(!config('backpack.store.supplier.enable', false)) {
            $this->crud->addField([
                'name' => 'defaultSupplierVirtual',
                'type' => 'hidden',
                'value' => 'fakevalue'
            ]);

            // PRICE
            if(config('backpack.store.product.price.enable', true)) {
                $this->crud->addField([
                'name' => 'defaultSupplier[price]',
                'label' => trans('backpack-store::product-field.fields.price'),
                'type' => 'number',
                'value' => $this->entry->defaultSupplier['price'] ?? null,
                'prefix' => config('backpack.store.currency.symbol'),
                'wrapper'   => [ 
                    'class' => 'form-group col-md-4'
                ],
                'attributes' => [
                    'step' => 0.01,
                    'min' => 0
                ],
                'tab' => trans('backpack-store::product-field.tabs.main')
                ]);
            }

            // OLD PRICE
            if(config('backpack.store.product.old_price.enable', true)) {
                $this->crud->addField([
                'name' => 'defaultSupplier[old_price]',
                'label' => trans('backpack-store::product-field.fields.old_price'),
                'type' => 'number',
                'value' => $this->entry->defaultSupplier['old_price'] ?? null,
                'prefix' => config('backpack.store.currency.symbol'),
                'wrapper'   => [ 
                    'class' => 'form-group col-md-4'
                ],
                'attributes' => [
                    'step' => 0.01,
                    'min' => 0
                ],
                'tab' => trans('backpack-store::product-field.tabs.main')
                ]);
            }

            // IN STOCK
            $this->crud->addField([
                'name' => 'defaultSupplier[in_stock]',
                'label' => trans('backpack-store::product-field.fields.in_stock.label'),
                'type' => 'number',
                'value' => $this->entry->defaultSupplier['in_stock'] ?? null,
                'tab' => trans('backpack-store::product-field.tabs.main'),
                'hint' => trans('backpack-store::product-field.fields.in_stock.hint'),
                'wrapper'   => [ 
                'class' => 'form-group col-md-4'
                ],
            ]);
        }
        
        // CATEGORIES
        $this->crud->addField([
            'name' => 'categories',
            'label' => trans('backpack-store::product-field.fields.categories.label'),
            'type' => 'select2_multiple',
            'entity' => 'categories',
            'attribute' => 'name',
            'model' => 'Backpack\Store\app\Models\Category',
            'tab' => trans('backpack-store::product-field.tabs.main'),
            'hint' => trans('backpack-store::product-field.fields.categories.hint'),
            'value' => $this->categories? $this->categories: null,
        ]);


        // BRAND
        if(config('backpack.store.brands.enable')) {
            $this->crud->addField([
                'name' => 'brand',
                'label' => trans('backpack-store::product-field.fields.brand'),
                'type' => 'select2',
                'entity' => 'brand',
                'attribute' => 'name',
                'model' => 'Backpack\Store\app\Models\Brand',
                'tab' => trans('backpack-store::product-field.tabs.main'),
            ]);
        }

        // DESCRIPTION
        $this->crud->addField([
            'name' => 'content',
            'label' => trans('backpack-store::product-field.fields.content'),
            'type' => 'ckeditor',
            'attributes' => [
                'rows' => 7
            ],
            'tab' => trans('backpack-store::product-field.tabs.main')
        ]);
        
        
        // CUSTOM PROPERTIES
        $this->setCustomPropertiesFields();

        // ATTRIBUTES
        if(config('backpack.store.attributes.enable', true)){
            $this->setAttributesFields();
        }

        // SUPPLIERS
        if(config('backpack.store.supplier.enable')) {

            // $regionsField = [];
            // // MULTISTORE
            // if(config('backpack.multistore.enable', true)){
            //     $regionsField = [
            //         'name'  => 'regionsString',
            //         'type'  => 'text',
            //         'label' => trans('backpack-store::product-field.fields.suppliers.regions'),
            //         'attributes' => [
            //             'readonly'  => 'readonly',
            //             'disabled'  => 'disabled'
            //         ]
            //     ];
            // }

            $this->crud->addField([
                'name'  => 'suppliersData',
                'label' => trans('backpack-store::product-field.fields.suppliers.label'),
                'type'  => 'repeatable',
                'fields' => [
                    [
                        'name'    => 'supplier',
                        'type'    => 'select_from_array',
                        'label'   => trans('backpack-store::product-field.fields.suppliers.supplier'),
                        'options'     => $this->suppliers_list,
                        'allows_null' => false,
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'    => 'code',
                        'type'    => 'text',
                        'label'   => trans('backpack-store::product-field.fields.suppliers.code'),
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'    => 'barcode',
                        'type'    => 'text',
                        'label'   => trans('backpack-store::product-field.fields.suppliers.barcode'),
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'    => 'in_stock',
                        'type'    => 'number',
                        'label'   => trans('backpack-store::product-field.fields.suppliers.in_stock'),
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'    => 'price',
                        'type'    => 'number',
                        'label'   => trans('backpack-store::product-field.fields.suppliers.price'),
                        'prefix' => config('backpack.store.currency.symbol'),
                        'attributes' => [
                        'step' => 0.01,
                        'min' => 0
                        ],
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'  => 'old_price',
                        'type'  => 'number',
                        'label' => trans('backpack-store::product-field.fields.suppliers.old_price'),
                        'prefix' => config('backpack.store.currency.symbol'),
                        'attributes' => [
                        'step' => 0.01,
                        'min' => 0
                        ],
                        'wrapper' => ['class' => 'form-group col-md-4'],
                    ],
                    [
                        'name'  => 'updated_at',
                        'type'  => 'text',
                        'label' => trans('backpack-store::product-field.fields.suppliers.updated_at'),
                        'attributes' => [
                        'readonly'  => 'readonly',
                        'disabled'  => 'disabled'
                        ]
                    ],
                    // ...$regionsField
                ],
            
                // optional
                'new_item_label'  => trans('backpack-store::product-field.fields.suppliers.add'),
                'init_rows' => 1,
                'min_rows' => 2,
                'tab' => trans('backpack-store::product-field.tabs.warehouse'),
            ]);


        }


        // MODIFICATIONS
        if(config('backpack.store.product.modifications.enable', true)) {
            $this->setModificationsFields();
        }


        // IMAGES
        if(config('backpack.store.product.images.enable', true)) {
            $this->crud->addField([
                'name'  => 'images',
                'label' => trans('backpack-store::product-field.fields.images.label'),
                'type'  => 'repeatable',
                'fields' => [
                [
                    'name' => 'src',
                    'label' => trans('backpack-store::product-field.fields.images.image'),
                    'type' => 'browse',
                ],
                [
                    'name' => 'alt',
                    'label' => trans('backpack-store::product-field.fields.images.alt')
                ],
                [
                    'name' => 'title',
                    'label' => trans('backpack-store::product-field.fields.images.title')
                ],
                [
                    'name' => 'size',
                    'type' => 'radio',
                    'label' => trans('backpack-store::product-field.fields.images.size'),
                    'options' => [
                    'cover' => 'Cover',
                    'contain' => 'Contain'
                    ],
                    'inline' => true
                ]
                ],
                'new_item_label'  => trans('backpack-store::product-field.fields.images.add'),
                'init_rows' => 1,
                'default' => [],
                'tab' => trans('backpack-store::product-field.tabs.images')
            ]);
        }


        // SEO FIELDS
        if(config('backpack.store.product.seo.enable', true)){
            $this->setSeoFields();
        }

        // Google Merchant
        $this->crud->addField([
            'name' => 'merchant_content',
            'label' => trans('backpack-store::product-field.fields.content'),
            'type' => 'ckeditor',
            'attributes' => [
                'rows' => 7
            ],
            'tab' => 'Google Merchants'
        ]);
    }

    /**
     * Method setCustomPropertiesFields
     *
     * @return void
     */
    private function setCustomPropertiesFields() {
        $this->crud->addField([
            'name' => 'delim',
            'type' => 'custom_html',
            'value' => '<h3>' . trans('backpack-store::product-field.fields.custom_properties.title') . '</h3>
                <p class="help-block">' . trans('backpack-store::product-field.fields.custom_properties.description') . '</p>',
            'tab' => trans('backpack-store::product-field.tabs.characteristics')
        ]);

        $this->crud->addField([
            'name' => 'custom_attrs',
            'label' => trans('backpack-store::product-field.fields.custom_properties.label'),
            'type' => 'table',
            'entity_singular' => 'атрибут',
            'columns'         => [
                'name'  => trans('backpack-store::product-field.fields.custom_properties.columns.name'),
                'value'  => trans('backpack-store::product-field.fields.custom_properties.columns.value'),
            ],
            'min' => 0,
            'fake' => true, 
            'store_in' => 'extras_trans',
            'tab' => trans('backpack-store::product-field.tabs.characteristics')
        ]);
    }

    /**
     * Method setModificationsFields
     *
     * @return void
     */
    private function setModificationsFields() {
        $this->crud->addField([
            'name' => 'delim_mod',
            'type' => 'custom_html',
            'value' => '<h3>' . trans('backpack-store::product-field.fields.modifications.related_products') . '</h3>',
            'tab' => trans('backpack-store::product-field.tabs.management')
        ]);

        $this->crud->addField([
            'name' => 'parent_id',
            'type' => 'hidden',
            'value' => \Request::query('parent_id') ?? null
        ]);
    
        $this->crud->addField([
            'name' => 'modifications',
            'label' => trans('backpack-store::product-field.fields.modifications.related_products'),
            'type'    => 'relationship',
            'model'     => 'Backpack\Store\app\Models\Product',
            'attribute' => 'name',
            'ajax' => true,
            'multiple' => true,
            'entity' => 'children',
            'data_source' => url("/admin/api/product"),
            'placeholder' => "Поиск по названию, ID, артикулу или штрихкоду",
            'minimum_input_length' => 0,
            'inline_create' => [
            'entity' => 'product',
            'force_select' => true,
            ],
            'hint' => trans('backpack-store::product-field.fields.modifications.hint'),
            'tab' => trans('backpack-store::product-field.tabs.management')
        ]);

        // Short name of modification
        $this->crud->addField([
            'name' => 'short_name',
            'label' => trans('backpack-store::product-field.fields.modifications.short_name.label'),
            'type' => 'text',
            'hint' => trans('backpack-store::product-field.fields.modifications.short_name.hint'),
            'tab' => trans('backpack-store::product-field.tabs.management')
        ]);
    }

    /**
     * Method setSeoFields
     *
     * @return void
     */
    private function setSeoFields() {
        $this->crud->addField([
            'name' => 'meta_title',
            'label' => trans('backpack-store::product-field.fields.seo.meta_title'),
            'type' => 'text',
            'fake' => true, 
            'store_in' => 'seo',
            'tab' => trans('backpack-store::product-field.tabs.seo')
        ]);

        $this->crud->addField([
            'name' => 'meta_description',
            'label' => trans('backpack-store::product-field.fields.seo.meta_description'),
            'type' => 'textarea',
            'fake' => true, 
            'store_in' => 'seo',
            'tab' => trans('backpack-store::product-field.tabs.seo')
        ]);
    }

    /**
     * setAttributesFields
     * 
     * Set Attributes create/update fields
     *
     * @return void
     */
    public function setAttributesFields() {
      
        $this->crud->addField([
            'name' => 'delim_2',
            'type' => 'custom_html',
            'value' => '<h3>' . trans('backpack-store::product-field.fields.attributes.title') . '</h3><p class="help-block">' . trans('backpack-store::product-field.fields.attributes.description') . '</p>',
            'tab' => trans('backpack-store::product-field.tabs.characteristics')
        ]);

        // $this->entry - current product data from DB
        // $this->attrs - collection of all attributes for attached categories
        if(isset($this->attrs) && $this->entry) {
  
          // Adding hidden field
          $this->crud->addField([
            'name' => 'props',
            'type' => 'hidden_fake_array',
            'value' => null,
          ]);
  
          $attr_fields = [];
  
          //
          foreach($this->attrs as $index => $attribute) {
            // Attribute Model ID
            $id = $attribute->id;
  
            // Attribute Model values list
            // $available_values = $attribute->values->mapWithKeys(function ($item, $key) {
            //   return [$item['id'] => $item['value']];
            // });
  
            // Attribute settings
            $settings = $attribute->extras;
            // dd($settings['min'] ?? '1');
  
            // If entry has attached attributes
            // Try find current value for this attribute 
            if($this->entry->ap) {
              // Find this attribute from already attached attributes
              $model_attribute = $this->entry->ap()->where('attribute_id', $attribute->id)->get();
            }else {
              $model_attribute = null;
            }
            
            // Create base attribute field template
            $si = $attribute->getExtrasTrans('si');
  
            $base_hint = '';
            $base_hint .= $attribute->in_properties? '<b>' . trans('backpack-store::product-field.fields.attributes.in_properties') . '</b>': '';
            $base_hint .= $base_hint && mb_strlen($base_hint) > 0 && $attribute->in_filters? ' ' . trans('backpack-store::product-field.fields.attributes.and') . ' ': '';
            $base_hint .= $attribute->in_filters? '<b>' . trans('backpack-store::product-field.fields.attributes.in_filters') . '</b>': '';
            
            $attr_fields[$index] = [
              'name' => "props[{$id}]",
              'label' => $attribute->name . ($si? ' (' . $si . ')': ''),
              'tab' => trans('backpack-store::product-field.tabs.characteristics'),
              'hint' => $base_hint
            ];
  
            // Set correct options for different attribute types
            // For checkbox
            if($attribute->type === 'checkbox')
            {
              // IMPORTANT !!!!! CHANGE THIS
              // If exists get pivot value 
              $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
              // dd($value);
              $attr_fields[$index] = array_merge(
                $attr_fields[$index],
                [
                  // 'name' => 'avsFake',
                  'type'    => 'relationship_custom',
                  'model2'     => 'Backpack\Store\app\Models\AttributeValue',
                  'attribute' => 'value',
                  'value' => $value,
                  'ajax' => true,
                  'multiple' => true,
                  // 'entity' => Backpack\Store\app\Models\AttributeValue::class,
                  // 'entity' => 'av',
                  'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                  'placeholder' => trans('backpack-store::product-field.fields.attributes.search_placeholder'),
                  'minimum_input_length' => 0,
                  'inline_create' => [
                    'entity' => 'value',
                    'force_select' => true,
                  ]
                ],
                // [
                //   'type'    => 'select2_from_ajax_multiple',
                //   'model'     => 'Backpack\Store\app\Models\AttributeValue',
                //   'attribute' => 'value',
                //   'value' => $value ?? null,
                //   'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                //   'placeholder' => "Поиск по названию параметра",
                //   'minimum_input_length' => 0
                // ]
              );
            }
            // For radio
            else if($attribute->type === 'radio')
            {
              // IMPORTANT !!!!! CHANGE THIS
              // $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
              // dd($model_attribute);
              $value = $model_attribute->first();
              // dd($value->attribute_value_id);
  
              $attr_fields[$index] = array_merge(
                $attr_fields[$index],
                [
                  'type'    => 'select2_from_ajax',
                  'model'     => 'Backpack\Store\app\Models\AttributeValue',
                  'attribute' => 'value',
                  'value' => $value->attribute_value_id ?? null,
                  'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                  'placeholder' => trans('backpack-store::product-field.fields.attributes.search_placeholder'),
                  'minimum_input_length' => 0
                ]
              );
            }
            // For number
            else if($attribute->type === 'number')
            {
              // IMPORTANT !!!!! CHANGE THIS
              $value = $model_attribute->first()->value ?? null;
  
              $options = [];
              $options['min'] = $settings['min'] ?? 0;
              $options['max'] = $settings['max'] ?? 999999999999;
              $options['step'] = $settings['step'] ?? 0.1;         
  
              $hint = $attr_fields[$index]['hint'] . ', ';
              $hint .= trans('backpack-store::product-field.fields.attributes.min_value') . ": {$options['min']}, " . trans('backpack-store::product-field.fields.attributes.max_value') . ": {$options['max']}, " . trans('backpack-store::product-field.fields.attributes.step') . ": {$options['step']}";
  
              $attr_fields[$index] = array_merge(
                $attr_fields[$index],
                [
                  'type' => 'number',
                  'attributes' => [
                    'min' => $options['min'],
                    'max' => $options['max'],
                    'step' => $options['step'],
                  ],
                  'value' => $value,
                  'hint' => $hint
                ]
              );
            }
            // For string
            else if($attribute->type === 'string')
            {
              $value = $model_attribute->first()->value_trans ?? null;
  
              $attr_fields[$index] = array_merge(
                $attr_fields[$index],
                [
                  'type' => 'text',
                  'value' => $value,
                ]
              );
            }
          }
          
  
          // Set all prepared fields
          foreach($attr_fields as $attr_field) {
            $this->crud->addField($attr_field);
          }
        }
        else {
          $this->crud->addField([
            'name'  => 'no_attributes',
            'type'  => 'custom_html',
            'value' => "
            <p>" . trans('backpack-store::product-field.fields.attributes.no_attributes.description') . "</p>
            <ul>
              <li>" . trans('backpack-store::product-field.fields.attributes.no_attributes.category_selected') . "</li>
              <li>" . trans('backpack-store::product-field.fields.attributes.no_attributes.category_has_attributes') . "</li>
              <li>" . trans('backpack-store::product-field.fields.attributes.no_attributes.data_saved') . "</li>
            </ul>",
            'tab' => trans('backpack-store::product-field.tabs.characteristics')
          ]);
        }
    }
}
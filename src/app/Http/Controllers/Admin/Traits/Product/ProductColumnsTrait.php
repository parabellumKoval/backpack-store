<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits\Product;

use Backpack\Store\app\Models\Category;
use Backpack\Reviews\app\Http\Controllers\Admin\Traits\HasRatingColumn;

trait ProductColumnsTrait
{
    use HasRatingColumn;

    protected function setupColumns()
    {

        $this->addImagesColumn(['label' => trans('backpack-store::product-column.image'), 'height' => '140px']);

        $this->crud->addColumn([
            'name' => 'adminName',
            'label' => 'Название',
            'type' => 'textarea',
            'limit' => 100,
            'priority' => 1,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere(function($query) use ($searchTerm){
                foreach($this->langs_list as $index => $lang_key) {
                    $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                    $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
                }
                });
            },
        ]);

        $this->crud->addColumnBottomRow([
            'name' => 'adminSupplierOverview',
            'label' => '<span title="Коды, наличие и цены по всем активным складам">Склады / модификации</span>',
            'type' => 'closure',
            'escaped' => false,
            'priority' => 2,
            'orderable' => true,
            'colspan_start' => 2,
            'colspan_end' => 7,
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->withSum('sp', 'in_stock')
                    ->orderBy('sp_sum_in_stock', $columnDirection);
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query
                    ->whereHas('sp', function($query) use($searchTerm) {
                        $query->where('code', 'LIKE', '%' . $searchTerm . '%')
                              ->orWhere('barcode', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->orWhere('code', 'LIKE', '%' . $searchTerm . '%');
            },
            'function' => function ($entry) {
                return view('store-crud::columns.product_supplier_overview', ['entry' => $entry])->render();
            },
        ]);

        // $this->crud->addColumn([
        //     'name' => 'adminSupplierCodes',
        //     'label' => '<span title="' . trans('backpack-store::product-column.barcode.title') . '">' . trans('backpack-store::product-column.barcode.label') . '</span>',
        //     'type' => 'closure',
        //     'escaped' => false,
        //     'priority' => 1,
        //     'function' => function ($entry) {
        //         return view('store-crud::columns.product_supplier_codes', ['entry' => $entry])->render();
        //     },
        //     'searchLogic' => function ($query, $column, $searchTerm) {
        //         $query
        //             ->whereHas('sp', function($query) use($searchTerm) {
        //                 $query->where('code', 'LIKE', '%'.$searchTerm.'%')
        //                       ->orWhere('barcode', 'LIKE', '%'.$searchTerm.'%');
        //             })
        //             ->orWhere('code', 'LIKE', '%'.$searchTerm.'%');
        //     },
        // ]);

        // $this->crud->addColumn([
        //     'name' => 'adminInStock',
        //     'label' => '<span title="' . trans('backpack-store::product-column.stock.title') . '">' . trans('backpack-store::product-column.stock.label') . '</span>',
        //     'type' => 'closure',
        //     'escaped' => false,
        //     'priority' => 4,
        //     'orderable'   => true,
        //     'orderLogic' => function ($query, $column, $columnDirection) {
        //         return $query->withSum('sp', 'in_stock')
        //                 ->orderBy('sp_sum_in_stock', $columnDirection);
        //     },
        //     'function' => function ($entry) {
        //         return view('store-crud::columns.product_supplier_stock', ['entry' => $entry])->render();
        //     },
        // ]);

        // $this->crud->addColumn([
        //     'name' => 'adminSupplierPrices',
        //     'label' => trans('backpack-store::product-column.price'),
        //     'type' => 'closure',
        //     'escaped' => false,
        //     'orderable'   => true,
        //     'orderLogic' => function ($query, $column, $columnDirection) {
        //         return $query
        //         ->leftJoin('ak_supplier_product', 'ak_supplier_product.product_id', '=', 'ak_products.id')
        //         ->orderBy('ak_supplier_product.price', $columnDirection)
        //         ->select('ak_products.*');
        //     },
        //     'priority' => 6,
        //     'function' => function ($entry) {
        //         return view('store-crud::columns.product_supplier_prices', ['entry' => $entry])->render();
        //     },
        // ]);

        $this->addToggleColumn([
            'name' => 'is_active',
            'label' => '<span title="' . trans('backpack-store::product-column.active.title') . '">' . trans('backpack-store::product-column.active.label') . '</span>',
            'priority' => 5,
            'orderable'   => true,
            'toggle' => [
                'values' => [
                    'checked' => 1,
                    'unchecked' => 0,
                ],
            ],
        ]);

        // $this->crud->addColumn([
        //     'name' => 'seo',
        //     'label' => 'SEO',
        //     'type' => 'seo_status_compact',
        //     'seo_field' => 'seo',
        //     'properties' => [
        //         'meta_title' => 'Meta Title',
        //         'meta_description' => 'Meta Description',
        //     ],
        //     'compact_labels' => [
        //         'meta_title' => 'MT',
        //         'meta_description' => 'MD',
        //     ],
        //     'empty_text' => 'Не заполнено',
        //     'priority' => 6,
        // ]);

        // $this->crud->addColumn([
        //     'name' => 'name',
        //     'label' => trans('backpack-store::product-column.name'),
        //     'type' => 'textarea',
        //     'limit' => 100,
        //     'priority' => 1,
        //     'searchLogic' => function ($query, $column, $searchTerm) {
        //         $query->orWhere(function($query) use ($searchTerm){
        //             foreach($this->langs_list as $index => $lang_key) {
        //                 $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
        //                 $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
        //             }
        //         });
        //     },
        // ]);

        $this->addRatingColumn([
            'priority' => 3,
            'reviewable_type' => $this->getProductReviewsMorphClass(),
        ]);

        // $this->crud->addColumn([
        //     'name' => 'adminTranslations',
        //     'label' => '<span title="' . trans('backpack-store::product-column.translations.title') . '">' . trans('backpack-store::product-column.translations.label') . '</span>',
        //     'escaped' => false,
        //     'limit' => 1500,
        //     'priority' => 7
        // ]);

        $this->crud->addColumn([
            'name' => 'categories',
            'label' => trans('backpack-store::product-column.categories'),
            'type'  => 'select2_multiple',
            'model' => Category::class,
            'attribute' => 'uniqHtml',
            'data_source' => route('backpack.helpers.fetch', ['key' => 'category']),
            'max_width' => '400px',
            'priority' => 7
        ]);

        $this->crud->addColumn([
            'name' => 'fillAdmin',
            'label' => '<span title="' . trans('backpack-store::product-column.quality.title') . '">' . trans('backpack-store::product-column.quality.label') . '</span>',
            'escaped' => false,
            'limit' => 5500,
            'priority' => 4
        ]);
    }

    protected function getProductReviewsMorphClass(): ?string
    {
        if (property_exists($this, 'reviewsMorphClass') && $this->reviewsMorphClass) {
            return $this->reviewsMorphClass;
        }

        if (class_exists(\App\Models\Product::class)) {
            return \App\Models\Product::class;
        }

        if (class_exists(\Backpack\Store\app\Models\Product::class)) {
            return \Backpack\Store\app\Models\Product::class;
        }

        if (property_exists($this, 'product_class') && $this->product_class) {
            return $this->product_class;
        }

        return null;
    }
}

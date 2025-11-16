<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits\Product;

use Backpack\Store\app\Models\Category;

trait ProductColumnsTrait
{
    protected function setupColumns()
    {
        // $this->crud->addColumn([
        //     'name' => 'imageSrc',
        //     'label' => trans('backpack-store::product-column.image'),
        //     'type' => 'image',
        //     'height' => '60px',
        //     'width'  => '40px',
        //     'priority' => 2,
        // ]);

        $this->addImagesColumn(['label' => trans('backpack-store::product-column.image')]);

        $this->crud->addColumn([
            'name' => 'adminSupplierCodes',
            'label' => '<span title="' . trans('backpack-store::product-column.barcode.title') . '">' . trans('backpack-store::product-column.barcode.label') . '</span>',
            'type' => 'closure',
            'escaped' => false,
            'priority' => 1,
            'function' => function ($entry) {
                return view('store-crud::columns.product_supplier_codes', ['entry' => $entry])->render();
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query
                    ->whereHas('sp', function($query) use($searchTerm) {
                        $query->where('code', 'LIKE', '%'.$searchTerm.'%')
                              ->orWhere('barcode', 'LIKE', '%'.$searchTerm.'%');
                    })
                    ->orWhere('code', 'LIKE', '%'.$searchTerm.'%');
            },
        ]);

        $this->crud->addColumn([
            'name' => 'adminInStock',
            'label' => '<span title="' . trans('backpack-store::product-column.stock.title') . '">' . trans('backpack-store::product-column.stock.label') . '</span>',
            'type' => 'closure',
            'escaped' => false,
            'priority' => 4,
            'orderable'   => true,
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->withSum('sp', 'in_stock')
                        ->orderBy('sp_sum_in_stock', $columnDirection);
            },
            'function' => function ($entry) {
                return view('store-crud::columns.product_supplier_stock', ['entry' => $entry])->render();
            },
        ]);

        $this->crud->addColumn([
            'name' => 'adminSupplierPrices',
            'label' => trans('backpack-store::product-column.price'),
            'type' => 'closure',
            'escaped' => false,
            'orderable'   => true,
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query
                ->leftJoin('ak_supplier_product', 'ak_supplier_product.product_id', '=', 'ak_products.id')
                ->orderBy('ak_supplier_product.price', $columnDirection)
                ->select('ak_products.*');
            },
            'priority' => 6,
            'function' => function ($entry) {
                return view('store-crud::columns.product_supplier_prices', ['entry' => $entry])->render();
            },
        ]);

        $this->crud->addColumn([
            'name' => 'is_active',
            'label' => '<span title="' . trans('backpack-store::product-column.active.title') . '">' . trans('backpack-store::product-column.active.label') . '</span>',
            'type' => 'toggle',
            'view_namespace' => 'store-crud::columns',
            'priority' => 5,
            'orderable'   => true,
        ]);

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

        $this->crud->addColumn([
            'name' => 'adminTranslations',
            'label' => '<span title="' . trans('backpack-store::product-column.translations.title') . '">' . trans('backpack-store::product-column.translations.label') . '</span>',
            'escaped' => false,
            'limit' => 1500,
            'priority' => 7
        ]);

        $this->crud->addColumn([
            'name' => 'categories',
            'label' => trans('backpack-store::product-column.categories'),
            'type'  => 'select2_multiple',
            'model' => Category::class,
            'attribute' => 'name',
            'data_source' => url('admin/api/category'),
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
}

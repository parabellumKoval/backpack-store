<?php

namespace Backpack\Store\app\Services\Product;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Support\Facades\Route;

class ProductOrdersAttachService
{
    public function attachToCrud(CrudPanel $crud, string $tab = null, array $options = []): void
    {
        $productId = (int) ($options['product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $fetchUrl = $options['fetch_url']
            ?? (Route::has('product.orders-tab') ? route('product.orders-tab', ['productId' => $productId]) : null);
        if (!$fetchUrl) {
            return;
        }
        $perPage = max(1, min(100, (int) ($options['per_page'] ?? 10)));
        $tabLabel = $tab ?: trans('backpack-store::product.orders_tab.tab_title');

        $crud->addField([
            'name' => 'product_orders_tab',
            'type' => 'product_orders',
            'label' => $tabLabel,
            'tab' => $tabLabel,
            'wrapper' => ['class' => 'col-12 p-0'],
            'product_id' => $productId,
            'fetch_url' => $fetchUrl,
            'per_page' => $perPage,
        ]);
    }
}

<?php

return [
    'entity_singular' => 'product',
    'entity_plural' => 'products',
    'bulk_actions' => [
        'select_items' => 'Please select items to process',
        'activated' => ':count products have been activated',
        'deactivated' => ':count products have been deactivated',
        'categories_assigned' => ':count products have been assigned to selected categories',
        'categories_removed' => ':count products have been removed from all categories',
        'invalid_action' => 'Invalid action'
    ],
    'fields' => [
        'barcode' => 'Barcode/Code',
        'name' => 'Name',
        'slug' => 'URL',
        'slug_hint' => 'Will be generated from name by default',
        'brand' => 'Brand',
        'description' => 'Description',
        'images' => 'Images',
        'price' => 'Price',
        'old_price' => 'Old Price',
        'stock' => 'Stock Quantity',
        'in_stock' => 'In Stock',
        'categories' => 'Categories',
    ],
    'orders_tab' => [
        'tab_title' => 'Orders',
        'title' => 'Orders',
        'summary' => [
            'orders' => 'Orders',
            'quantity' => 'Units sold',
            'revenue' => 'Revenue',
            'revenue_empty' => 'No revenue recorded yet.',
        ],
        'chart' => [
            'title' => 'Monthly demand',
            'quantity_label' => 'Units',
            'revenue_label' => 'Revenue',
        ],
        'table' => [
            'order' => 'Order',
            'status' => 'Statuses',
            'customer' => 'Customer',
            'quantity' => 'Qty',
            'price' => 'Unit price',
            'total' => 'Total',
        ],
        'messages' => [
            'loading' => 'Loading orders…',
            'empty' => 'No orders yet.',
            'error' => 'Failed to load orders. Please retry.',
            'chart_empty' => 'Not enough data for the chart yet.',
        ],
        'pagination' => [
            'showing' => 'Showing :from–:to of :total orders',
        ],
    ],
];
<?php

return [
    'tabs' => [
        'main' => 'Main',
        'images' => 'Images',
        'characteristics' => 'Characteristics',
        'warehouse' => 'Warehouse',
        'management' => 'Management',
        'seo' => 'SEO'
    ],
    'fields' => [
        'is_active' => 'Active',
        'code' => [
            'label' => 'Article',
            'hint' => 'Fill in if you want the product to have a fixed article, otherwise supplier articles will be displayed.'
        ],
        'barcode' => 'Barcode/Code',
        'name' => 'Name',
        'slug' => [
            'label' => 'URL',
            'hint' => 'Will be generated from name by default'
        ],
        'price' => 'Price',
        'old_price' => 'Old Price',
        'in_stock' => [
            'label' => 'Quantity in Stock',
            'hint' => 'The quantity of goods will be automatically deducted when orders are placed on the site.'
        ],
        'manual_sort' => [
            'label' => 'Manual sorting',
            'hint' => 'The higher the value, the higher the product appears in lists. Decimal values are allowed, for example 200.5.'
        ],
        'categories' => [
            'label' => 'Categories',
            'hint' => 'Product characteristics depend on selected categories. After saving the entry, characteristics will be synchronized with categories.'
        ],
        'brand' => 'Brand',
        'content' => 'Description',
        'images' => [
            'label' => 'Images',
            'add' => 'Add image',
            'image' => 'Image',
            'alt' => 'alt',
            'title' => 'title',
            'size' => 'Size'
        ],
        'custom_properties' => [
            'title' => 'Custom Properties',
            'description' => 'Unique, individual or rare product properties. Filled individually for each product. Displayed only in product characteristics (not used in filters). (Translations for each language version are filled separately).',
            'label' => 'Custom Properties',
            'columns' => [
                'name' => 'Name',
                'value' => 'Value'
            ]
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'add' => 'Add supplier',
            'supplier' => 'Supplier',
            'code' => 'Product Article',
            'barcode' => 'Code/Barcode',
            'in_stock' => 'In Stock, pcs',
            'price' => 'Price',
            'old_price' => 'Old Price',
            'updated_at' => 'Last Update'
        ],
        'modifications' => [
            'related_products' => 'Related Products',
            'hint' => 'Related products are other varieties of the same product. Find and attach modifications to the product to link them into one group.',
            'short_name' => [
                'label' => 'Short name of this modification',
                'hint' => 'Short name of this product modification, will be used in the modifications list on the site. This can be taste/color, etc.'
            ]
        ],
        'seo' => [
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta Description',
            'disable_base_canonical' => [
                'label' => 'Disable canonical to base modification',
                'hint' => 'When enabled, this modification will be indexed on its own and the canonical will point here instead of the base one.'
            ]
        ],
        'attributes' => [
            'title' => 'Attributes',
            'description' => 'Universal product properties. Created and managed separately in the Attributes section. Can be used in filters and product characteristics.',
            'search_placeholder' => 'Search by parameter name',
            'in_properties' => 'In properties',
            'in_filters' => 'In filters',
            'and' => 'and',
            'min_value' => 'min value',
            'max_value' => 'max value',
            'step' => 'step',
            'no_attributes' => [
                'description' => 'To edit characteristics, first make sure that:',
                'category_selected' => 'A category is selected for the entry',
                'category_has_attributes' => 'The selected category corresponds to at least one attribute',
                'data_saved' => 'The data has been saved at least once'
            ]
        ]
    ]
];

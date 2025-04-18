<?php

return [
    'fields' => [
        'type' => 'Value Type',
        'name' => 'Name',
        'icon' => 'Icon',
        'icon_hint' => 'html-code of the icon',
        'description' => 'Description',
        'categories' => 'Categories',
        'categories_hint' => 'Product categories to which these characteristics apply',
        'in_filters' => 'Add to Filtration',
        'si' => 'Units',
        'si_hint' => 'Units will be added after values',
        'values' => 'Allowed Values',
        'values_help' => '<h4>Allowed Values</h4>
        <ul>
            <li>Fields <b>Action Type</b> and <b>Value (for action)</b> should be filled only if value transformation is needed</li>
            <li>Value operations will be processed on schedule: every 10 minutes</li>
        </ul>',
        'min' => 'Minimum Value',
        'max' => 'Maximum Value',
        'step' => 'Step'
    ],
    'value_actions' => [
        'join' => 'Join with another value (and delete this)',
        'split' => 'Split into multiple values (and delete this)'
    ],
    'filters' => [
        'category' => 'Category',
        'type' => 'Type',
        'language' => 'Language'
    ]
];
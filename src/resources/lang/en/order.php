<?php

return [
    'title' => 'Orders',
    'single' => 'order',
    'fields' => [
        'code' => 'Order Number',
        'created_at' => 'Order Date and Time',
        'status' => 'Order Status',
        'price' => 'Order Amount',
        'pay_status' => 'Payment Status',
        'payment_method' => 'Payment Method',
        'payment_methods' => [
            'cash' => 'Cash Payment',
            'liqpay' => 'Online Payment'
        ],
        'customer' => [
            'title' => 'Customer',
            'firstname' => 'First Name',
            'lastname' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone'
        ],
        'delivery' => [
            'title' => 'Delivery',
            'status' => 'Delivery Status',
            'method' => 'Method',
            'methods' => [
                'warehouse' => 'Post Office',
                'address' => 'Courier Delivery',
                'pickup' => 'Pickup'
            ],
            'warehouse' => 'Post Office',
            'city' => 'City',
            'address' => 'Address',
            'zip' => 'Zip Code',
            'comment' => 'Customer Comment'
        ],
        'products' => [
            'title' => 'Products in Order'
        ],
        'hints' => [
            'price' => 'If left empty, the amount will be calculated automatically'
        ]
    ]
];
<?php

return [
  'model' => 'Backpack\Store\app\Models\Order',

  'model_admin' => 'Backpack\Store\app\Models\Order',

  'enable_bonus' => false,

  'per_page' => 12,

  'resource' => [
    'large' => 'Backpack\Store\app\Http\Resources\OrderLargeResource',
  ],

  // Common order statuses
  'status' => [
    'default' => 'new',
    'values' => ['new', 'canceled', 'failed', 'completed']
  ],
  // Payment statuses
  'pay_status' => [
    'default' => 'waiting',
    'values' => ['waiting', 'failed', 'paied']
  ],
  // Delivery statuses 
  'delivery_status' => [
    'default' => 'waiting',
    'values' => ['waiting', 'sent', 'failed', 'delivered', 'pickedup']
  ],
  // Validation fields
  'fields' => [
    'orderable_id' => [
      'rules' => 'nullable|uuid',
    ],

    'orderable_type' => [
      'rules' => 'nullable|max:255',
    ],

    'provider' => [
      'rules' => 'required|in:auth,data,outer',
      'store_in' => 'info'
    ],

    'payment' => [
      'rules' => 'array:method,status',
      'store_in' => 'info',
      'method' => [
        'rules' => 'required|in:liqpay,cash'
      ]
    ],
    
    'delivery' => [
      'rules' => 'array:city,address,zip,method,warehouse',
      'store_in' => 'info',
      'method' => [
        'rules' => 'required|in:address,warehouse,pickup'
      ],
      'warehouse' => [
        'rules' => 'required_if:delivery.method,warehouse|string|min:1|max:500'
      ],
      'city' => [
        'rules' => 'required_if:delivery.method,address,warehouse|string|min:2|max:255'
      ],
      'address' => [
        'rules' => 'required_if:delivery.method,address|string|min:2|max:255'
      ],
      'zip' => [
        'rules' => 'required_if:delivery.method,address|string|min:5|max:255'
      ],
    ],
    
    'products' => [
      'rules' => 'required|array',
      'hidden' => true,
    ],
    
    'bonusesUsed' => [
      'rules' => 'nullable|numeric',
      'store_in' => 'info'
    ],
    
    'promocode' => [
      'rules' => 'nullable',
      'store_in' => 'info'
    ],

    'user' => [
      'rules' => 'array:firstname,lastname,phone,email',
      'store_in' => 'info',
      'firstname' => [
        'rules' => 'required_if:provider,data|string|min:2|max:150'
      ],
      'lastname' => [
        'rules' => 'nullable|string|min:2|max:150'
      ],
      'phone' => [
        'rules' => 'required_if:provider,data|string|min:2|max:80'
      ],
      'email' => [
        'rules' => 'required_if:provider,data|email|min:2|max:150'
      ],
    ]
  ]
];
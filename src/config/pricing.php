<?php

return [
    // Округление цен ТОВАРОВ (конвертация валют)
    'product_price_decimal_places' => (int) env('STORE_PRODUCT_PRICE_DECIMALS', 2),
    
    // Округление цен ЗАКАЗОВ (расчеты)
    'order_price_decimal_places' => (int) env('STORE_ORDER_PRICE_DECIMALS', 2),
];

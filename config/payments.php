<?php

return [
    'gateway_1' => [
        'merchant_id' => env('GATEWAY_1_MERCHANT_ID', ''),
        'merchant_key' => env('GATEWAY_1_MERCHANT_KEY', ''),
    ],
    'gateway_2' => [
        'merchant_id' => env('GATEWAY_2_MERCHANT_ID', ''),
        'app_key' => env('GATEWAY_2_APP_KEY', ''),
    ]
];

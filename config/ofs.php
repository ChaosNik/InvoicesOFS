<?php

return [
    'driver' => env('OFS_DRIVER', 'fake'),

    'base_url' => env('OFS_BASE_URL', 'http://127.0.0.1:3566'),

    'api_key' => env('OFS_API_KEY'),

    'timeout' => (int) env('OFS_TIMEOUT', 15),

    'print' => env('OFS_PRINT_RECEIPT', false),

    'render_receipt_image' => env('OFS_RENDER_RECEIPT_IMAGE', false),

    'receipt_image_format' => env('OFS_RECEIPT_IMAGE_FORMAT', 'Png'),
];

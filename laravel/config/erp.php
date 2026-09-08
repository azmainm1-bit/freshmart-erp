<?php

return [
    'name' => env('ERP_STORE_NAME', 'FreshMart'),
    'currency' => 'BDT',
    // Database timestamps remain UTC; dates shown to staff use the store timezone.
    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Dhaka'),
    'address' => env('ERP_STORE_ADDRESS', ''),
    'phone' => env('ERP_STORE_PHONE', ''),
];

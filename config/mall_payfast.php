<?php

return [
    'merchant_id' => env('MALL_PAYFAST_MERCHANT_ID', env('PAYFAST_MERCHANT_ID', '')),
    'merchant_key' => env('MALL_PAYFAST_MERCHANT_KEY', env('PAYFAST_MERCHANT_KEY', '')),
    'passphrase' => env('MALL_PAYFAST_PASSPHRASE', env('PAYFAST_PASSPHRASE', '')),
    'testmode' => env('MALL_PAYFAST_TESTMODE', true),
    'validate_itn_with_server' => env('MALL_PAYFAST_VALIDATE_ITN_WITH_SERVER', false),
    'sandbox_process_url' => 'https://sandbox.payfast.co.za/eng/process',
    'production_process_url' => 'https://www.payfast.co.za/eng/process',
    'sandbox_validate_url' => 'https://sandbox.payfast.co.za/eng/query/validate',
    'production_validate_url' => 'https://www.payfast.co.za/eng/query/validate',
];

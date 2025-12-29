<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    */
    'admotum' => [
        'static_credentials' => [
            'api_key'    => env('PROLANCEE_API_KEY', ''),
            'secret_key' => env('PROLANCEE_SECRET_KEY', ''),
        ],

        'dynamic_credentials' => [
            'table' => '', // Database table name storing client credentials (string)

            'columns' => [
                'api_key'     => '', // Column name for API Key (string)
                'secret_key'  => '', // Column name for Secret Key (string)
                'client_id'   => '', // Column name for Client ID (string)
                'status'      => '', // Column name for Status (0 = inactive, 1 = active)
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Route Enabled
    |--------------------------------------------------------------------------
    */
    'intermediate_route_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | App Debug
    |--------------------------------------------------------------------------
    */
    'debug' => true,

    /*
    |--------------------------------------------------------------------------
    | Request Timeout (in seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_enabled' => true,
];
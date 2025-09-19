<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logtrail API Key
    |--------------------------------------------------------------------------
    |
    | This is the unique API key for your project.
    | Add this to your Laravel .env file as:
    |
    | LOGTRAIL_API_KEY=your_project_api_key
    |
    */
    'api_key' => env('LOGTRAIL_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Logtrail API Secret
    |--------------------------------------------------------------------------
    |
    | This is the secret token used to sign requests securely.
    | Add this to your Laravel .env file as:
    |
    | LOGTRAIL_API_SECRET=your_project_api_secret
    |
    */
    'api_secret' => env('LOGTRAIL_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Logtrail Environment Key
    |--------------------------------------------------------------------------
    |
    | Use this to separate logs per environment (e.g. local, staging, production).
    | Add this to your Laravel .env file as:
    |
    | LOGTRAIL_ENV_KEY=production
    |
    */
    'environment' => env('LOGTRAIL_ENV_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Async Log Sending
    |--------------------------------------------------------------------------
    |
    | If set to true, logs will be sent asynchronously (non-blocking).
    | Recommended: true for production, false for debugging.
    |
    | LOGTRAIL_ASYNC=true
    |
    */
    'async' => env('LOGTRAIL_ASYNC', true),
];

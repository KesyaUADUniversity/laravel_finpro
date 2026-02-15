<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Midtrans Server Key
    |--------------------------------------------------------------------------
    |
    | Ambil dari Dashboard Midtrans → Settings → Access Keys
    | Contoh: Mid-server-X48Uwr_xqpUvkHq8Ne-M4hSO
    |
    */
    'server_key' => env('MIDTRANS_SERVER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Client Key
    |--------------------------------------------------------------------------
    |
    | Ambil dari Dashboard Midtrans → Settings → Access Keys
    | Contoh: Mid-client-swFd12UTN-61dlW5
    |
    */
    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Production Mode
    |--------------------------------------------------------------------------
    |
    | true = production (uang asli)
    | false = sandbox (uang dummy - untuk testing)
    |
    */
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Sanitize Input
    |--------------------------------------------------------------------------
    |
    | Otomatis membersihkan input dari karakter berbahaya
    |
    */
    'is_sanitized' => true,

    /*
    |--------------------------------------------------------------------------
    | 3D-Secure for Credit Card
    |--------------------------------------------------------------------------
    |
    | Aktifkan verifikasi tambahan untuk kartu kredit
    |
    */
    'is_3ds' => true,
];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Tidak dipakai — SMARTSPACE AI menggunakan token Bearer murni (SPA
    | frontend Next.js mengirim token di header Authorization).
    |
    */

    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,localhost:8000,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration (menit)
    |--------------------------------------------------------------------------
    |
    | 480 menit = 8 jam, sesuai JWT_EXPIRES_IN di dokumen backend.
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 480),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session'    => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'         => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'     => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];

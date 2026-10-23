<?php

use App\Models\Usuario;

/*
 * Dos formas de entrar al broker, que nunca se mezclan:
 *
 * - Sistemas (/v1): API key de Sanctum (guard «sanctum», ver config/sanctum.php).
 * - Personas (/admin, la SPA): JWT de corta vida (guard «api») + refresh token
 *   rotado en cookie httpOnly (ver App\Http\Controllers\Admin\AuthController).
 */
return [

    'defaults' => [
        'guard' => 'api',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => Usuario::class,
        ],
    ],

    // Sin recuperación de clave por correo: la cambia un administrador con usuarios:clave.
    'passwords' => [],

    'password_timeout' => 10800,

];

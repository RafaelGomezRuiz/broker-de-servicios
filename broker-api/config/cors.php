<?php

/*
 * Solo la SPA puede llamar al broker desde un navegador. Los sistemas usan
 * /v1 de servidor a servidor, donde CORS no aplica.
 */
return [

    'paths' => ['admin/*', 'docs/api.json'],

    'allowed_methods' => ['GET', 'POST'],

    'allowed_origins' => [env('SPA_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    // Necesario para que el navegador envíe la cookie del refresh token.
    'supports_credentials' => true,

];

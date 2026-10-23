<?php

/*
 * Configuración propia del broker.
 *
 * Todo sale de variables de entorno (Secret o ConfigMap en Kubernetes);
 * aquí solo se les da forma y un valor por defecto razonable.
 */

return [

    // Origen de la SPA: CORS y cookie del refresh token.
    'spa_url' => env('SPA_URL', 'http://localhost:5173'),

    // Sesiones de la SPA. El access token (JWT) dura JWT_ACCESS_TTL: ver config/jwt.php.
    'spa' => [
        // Vida máxima de una sesión: pasado este tiempo hay que volver a entrar.
        'sesion_maxima_minutos' => (int) env('JWT_REFRESH_TTL', 480),
        // Minutos sin usar la SPA tras los cuales la sesión vence.
        'inactividad_minutos' => (int) env('JWT_INACTIVIDAD_TTL', 30),
        // Margen para que dos pestañas renueven a la vez sin que se tome como robo.
        'margen_rotacion_segundos' => 30,
        'cookie' => 'broker_refresh',
    ],

    // Servicios que muestra la SPA. Al sumar uno (p. ej. Archivos/MinIO), se agrega aquí.
    'servicios' => [
        [
            'codigo' => 'correo',
            'nombre' => 'Correo',
            'descripcion' => 'Envío de correos institucionales con copia, copia oculta, adjuntos y envíos masivos.',
            'estado' => 'activo',
            'documentacion' => '/docs/api',
            'openapi' => '/docs/api.json',
        ],
        [
            'codigo' => 'archivos',
            'nombre' => 'Archivos',
            'descripcion' => 'Almacenamiento centralizado de archivos (MinIO).',
            'estado' => 'proximamente',
        ],
    ],

    'sistemas' => [
        // Peticiones por minuto que acepta /v1 de cada sistema.
        'peticiones_por_minuto' => (int) env('BROKER_PETICIONES_POR_MINUTO', 120),
    ],

    'correo' => [
        // Mensajes por minuto que el worker entrega al SMTP (Office 365 admite ~30).
        'envio_por_minuto' => (int) env('BROKER_ENVIO_POR_MINUTO', 30),

        // Tamaño máximo de todos los adjuntos de un correo, en MB.
        'adjunto_max_mb' => (int) env('BROKER_ADJUNTO_MAX_MB', 20),

        // Días que se conservan cuerpos y adjuntos; los metadatos se quedan.
        'retencion_dias' => (int) env('BROKER_RETENCION_DIAS', 90),
    ],

];

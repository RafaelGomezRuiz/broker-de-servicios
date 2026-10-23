<?php

use App\Http\Middleware\SesionActiva;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // API sin estado y sin prefijo /api: las rutas son /v1/... y /admin/...
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No hay página de login a la que redirigir: sin credenciales siempre es 401 en JSON.
        $middleware->redirectGuestsTo(fn () => null);

        // Detrás del Ingress de Kubernetes (HTTPS terminado en el proxy), como en
        // GestionFestibal. Sin esto Laravel ve http:// (las URLs de /docs saldrían
        // con http y la SPA en https no podría leerlas) y la IP del Ingress en vez
        // de la del usuario (el bloqueo de intentos de login dejaría de funcionar).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'sesion.activa' => SesionActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Es una API: los errores siempre en JSON, nunca páginas HTML.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // El mismo mensaje para llave ausente, inválida, revocada o sistema
        // suspendido: no se le dice a quien prueba llaves cuál de los casos es.
        // En /admin (personas con JWT) el mensaje habla de la sesión.
        $exceptions->render(fn (AuthenticationException $e) => response()->json([
            'mensaje' => in_array('api', $e->guards(), true)
                ? 'Sesión no válida o vencida. Vuelve a entrar.'
                : 'Llave ausente o no válida. Envíala en el header Authorization: Bearer <llave>.',
        ], 401));

        $exceptions->render(fn (ThrottleRequestsException $e) => response()->json([
            'mensaje' => 'Demasiadas peticiones. Espera los segundos indicados en Retry-After.',
        ], 429, $e->getHeaders()));

        $exceptions->render(fn (ValidationException $e) => response()->json([
            'mensaje' => 'Los datos enviados no son válidos.',
            'errores' => $e->errors(),
        ], 422));

        // También cubre un correo de otro sistema: 404, sin confirmar que existe.
        $exceptions->render(fn (NotFoundHttpException $e) => response()->json([
            'mensaje' => 'No encontrado.',
        ], 404));

        $exceptions->render(fn (PostTooLargeException $e) => response()->json([
            'mensaje' => 'La petición supera el tamaño máximo permitido. Reduce los adjuntos o divide el lote.',
        ], 413));
    })->create();

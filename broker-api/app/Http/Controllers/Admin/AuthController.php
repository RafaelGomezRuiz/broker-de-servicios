<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\Auth\SesionesSpa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Inicio y cierre de sesión de la SPA.
 *
 * El access token se devuelve en el cuerpo (la SPA lo guarda solo en
 * memoria); el refresh token, en una cookie httpOnly.
 */
class AuthController extends Controller
{
    /** Intentos de login por correo e IP antes de bloquear un minuto. */
    private const MAX_INTENTOS = 5;

    public function __construct(private SesionesSpa $sesiones) {}

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $limite = 'login:'.Str::lower($datos['correo']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($limite);

            return response()->json([
                'mensaje' => "Demasiados intentos. Espera {$segundos} segundos.",
            ], 429, ['Retry-After' => $segundos]);
        }

        $usuario = Usuario::where('correo', Str::lower($datos['correo']))->first();

        // Mismo mensaje si el correo no existe, la clave no coincide o el
        // usuario está desactivado: no se revela cuál de los casos es.
        if (! $usuario || ! Hash::check($datos['password'], $usuario->password) || ! $usuario->estaActivo()) {
            RateLimiter::hit($limite, 60);

            return response()->json(['mensaje' => 'Correo o contraseña incorrectos.'], 401);
        }

        RateLimiter::clear($limite);
        $sesion = $this->sesiones->iniciar($usuario, $request);

        return $this->respuesta($sesion['access_token'], $usuario)
            ->withCookie($this->sesiones->cookie($sesion['refresh_token'], $sesion['sesion']));
    }

    /** Nuevo access token a partir de la cookie. La SPA lo llama al abrir y cuando el access token vence. */
    public function refresh(Request $request): JsonResponse
    {
        $renovada = $this->sesiones->renovar($request->cookie(config('broker.spa.cookie')));

        if (! $renovada) {
            return response()->json(['mensaje' => 'La sesión venció. Vuelve a entrar.'], 401)
                ->withCookie($this->sesiones->cookie(null));
        }

        $respuesta = $this->respuesta($renovada['access_token'], $renovada['sesion']->usuario);

        return $renovada['refresh_token']
            ? $respuesta->withCookie($this->sesiones->cookie($renovada['refresh_token'], $renovada['sesion']))
            : $respuesta;
    }

    /**
     * Cierra la sesión: revoca el refresh token y el access token. Funciona
     * aunque el access token ya haya vencido (se usa la cookie).
     */
    public function logout(Request $request): Response
    {
        $sesion = $this->sesiones->sesionDelRefresh($request->cookie(config('broker.spa.cookie')));

        try {
            if (auth('api')->check()) {
                $sesion ??= Sesion::find(auth('api')->payload()->get('sid'));
                auth('api')->invalidate();
            }
        } catch (Throwable) {
            // Access token vencido o inválido: basta con la cookie.
        }

        $this->sesiones->cerrar($sesion);

        return response()->noContent()->withCookie($this->sesiones->cookie(null));
    }

    public function yo(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        return response()->json($this->datosUsuario($usuario));
    }

    private function respuesta(string $accessToken, Usuario $usuario): JsonResponse
    {
        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            // Segundos de vida del access token: la SPA renueva al vencer.
            'expira_en' => auth('api')->factory()->getTTL() * 60,
            'usuario' => $this->datosUsuario($usuario),
        ])->header('Cache-Control', 'no-store');
    }

    private function datosUsuario(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
        ];
    }
}

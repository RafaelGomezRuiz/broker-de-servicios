<?php

namespace App\Http\Middleware;

use App\Models\Sesion;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Además de un JWT válido, exige que su sesión siga viva: así un logout, un
 * usuario desactivado o la inactividad cortan el acceso al instante, sin
 * esperar a que venza el access token.
 *
 * También registra el uso, que es lo que mide la inactividad.
 */
class SesionActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $sid = auth('api')->payload()->get('sid');
        } catch (Throwable) {
            $sid = null;
        }

        $sesion = $sid ? Sesion::with('usuario')->find($sid) : null;

        if (! $sesion || ! $sesion->estaVigente() || $sesion->usuario_id !== auth('api')->id()) {
            throw new AuthenticationException('Sesión no válida.', ['api']);
        }

        // Una escritura por minuto basta para medir la inactividad.
        if ($sesion->ultimo_uso_at->lessThan(now()->subMinute())) {
            $sesion->forceFill(['ultimo_uso_at' => now()])->save();
        }

        return $next($request);
    }
}

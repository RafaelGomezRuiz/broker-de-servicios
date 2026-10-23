<?php

namespace App\Services\Auth;

use App\Models\Sesion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Sesiones de la SPA: JWT de corta vida + refresh token opaco rotado.
 *
 * - El access token (JWT) viaja en memoria de la SPA y lleva el ID de la
 *   sesión («sid»), así cerrar la sesión lo invalida al instante.
 * - El refresh token viaja en una cookie httpOnly limitada a /admin/auth;
 *   JavaScript no puede leerlo. En la base solo queda su hash.
 * - Cada renovación cambia el refresh token. Si llega uno ya reemplazado
 *   fuera del margen entre pestañas, se asume robo y se revoca la sesión.
 */
class SesionesSpa
{
    /** @return array{access_token: string, refresh_token: string, sesion: Sesion} */
    public function iniciar(Usuario $usuario, Request $request): array
    {
        $refresh = $this->nuevoToken();

        $sesion = $usuario->sesiones()->create([
            'token_hash' => hash('sha256', $refresh),
            'ultimo_uso_at' => now(),
            'expira_at' => now()->addMinutes(config('broker.spa.sesion_maxima_minutos')),
            'ip' => $request->ip(),
            'agente' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        $usuario->forceFill(['ultimo_acceso_at' => now()])->save();

        return [
            'access_token' => $this->accessToken($usuario, $sesion),
            'refresh_token' => $refresh,
            'sesion' => $sesion,
        ];
    }

    /**
     * Renueva con un refresh token. Devuelve null si ya no vale.
     * refresh_token viene null cuando no hay que cambiar la cookie (carrera
     * entre pestañas dentro del margen).
     *
     * @return array{access_token: string, refresh_token: string|null, sesion: Sesion}|null
     */
    public function renovar(?string $refresh): ?array
    {
        if (! $refresh) {
            return null;
        }

        $hash = hash('sha256', $refresh);
        $sesion = Sesion::with('usuario')->where('token_hash', $hash)->first();

        if (! $sesion) {
            return $this->tokenYaRotado($hash);
        }

        if (! $sesion->estaVigente()) {
            $sesion->revocar('vencida');

            return null;
        }

        $nuevo = $this->nuevoToken();
        $sesion->forceFill([
            'token_anterior_hash' => $hash,
            'token_hash' => hash('sha256', $nuevo),
            'rotada_at' => now(),
            'ultimo_uso_at' => now(),
        ])->save();

        return [
            'access_token' => $this->accessToken($sesion->usuario, $sesion),
            'refresh_token' => $nuevo,
            'sesion' => $sesion,
        ];
    }

    public function cerrar(?Sesion $sesion): void
    {
        $sesion?->revocar('logout');
    }

    public function sesionDelRefresh(?string $refresh): ?Sesion
    {
        return $refresh ? Sesion::where('token_hash', hash('sha256', $refresh))->first() : null;
    }

    /** Cookie del refresh token: httpOnly, solo para /admin/auth, nunca visible para JavaScript. */
    public function cookie(?string $refresh, ?Sesion $sesion = null): Cookie
    {
        $nombre = config('broker.spa.cookie');
        $segura = request()->isSecure() || app()->isProduction();

        if ($refresh === null) {
            return Cookie::create($nombre, '', 1, '/admin/auth', null, $segura, true, false, Cookie::SAMESITE_STRICT);
        }

        return Cookie::create($nombre, $refresh, $sesion->expira_at, '/admin/auth', null, $segura, true, false, Cookie::SAMESITE_STRICT);
    }

    /**
     * Llegó un refresh token que ya fue reemplazado.
     * Dentro del margen: dos pestañas renovando a la vez → se da un access
     * token sin rotar otra vez. Fuera del margen: posible robo → se revoca.
     */
    private function tokenYaRotado(string $hash): ?array
    {
        $sesion = Sesion::with('usuario')->where('token_anterior_hash', $hash)->first();
        if (! $sesion) {
            return null;
        }

        $margen = config('broker.spa.margen_rotacion_segundos');
        if ($sesion->estaVigente() && $sesion->rotada_at?->greaterThan(now()->subSeconds($margen))) {
            return [
                'access_token' => $this->accessToken($sesion->usuario, $sesion),
                'refresh_token' => null,
                'sesion' => $sesion,
            ];
        }

        $sesion->revocar('reutilizacion');

        return null;
    }

    private function accessToken(Usuario $usuario, Sesion $sesion): string
    {
        return auth('api')->claims(['sid' => $sesion->id])->login($usuario);
    }

    private function nuevoToken(): string
    {
        return Str::random(64);
    }
}

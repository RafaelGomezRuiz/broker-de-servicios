<?php

namespace App\Providers;

use App\Documentacion\AjustesOpenApi;
use App\Models\Sistema;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Documentación OpenAPI: todas las rutas /v1 exigen la llave del sistema.
        Scramble::configure()->withDocumentTransformers([
            fn (OpenApi $openApi) => $openApi->secure(
                SecurityScheme::http('bearer')->setDescription('Llave del sistema: `Authorization: Bearer <llave>`.')
            ),
            AjustesOpenApi::class,
        ]);

        // Un correo se devuelve tal cual, sin envolver en «data».
        // Los listados paginados siguen usando «data» + «meta».
        JsonResource::withoutWrapping();

        // En personal_access_tokens se guarda «sistema», no el nombre de la clase.
        Relation::enforceMorphMap([
            'sistema' => Sistema::class,
        ]);

        // Una llave solo vale si pertenece a un sistema activo: suspender un
        // sistema corta todas sus llaves al instante, sin borrarlas.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $llave, bool $esValida) => $esValida
                && $llave->tokenable instanceof Sistema
                && $llave->tokenable->estaActivo()
        );

        // Límite de peticiones por sistema (no por IP: varios sistemas pueden
        // salir por la misma IP del clúster).
        RateLimiter::for('sistemas', fn (Request $request) => Limit::perMinute(
            config('broker.sistemas.peticiones_por_minuto')
        )->by('sistema:'.$request->user()?->getKey()));

        // Ritmo de entrega al SMTP, compartido por todos los workers
        // (Office 365 corta si una cuenta pasa de ~30 mensajes por minuto).
        RateLimiter::for('envio-correo', fn () => Limit::perMinute(
            config('broker.correo.envio_por_minuto')
        ));
    }
}

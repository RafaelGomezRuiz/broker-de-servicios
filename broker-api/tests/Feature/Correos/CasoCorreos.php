<?php

namespace Tests\Feature\Correos;

use App\Models\Sistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Base de las pruebas del servicio de correo.
 *
 * En phpunit.xml la cola es «sync» y el mailer «array»: el job corre dentro
 * de la misma petición y los mensajes quedan en memoria para revisarlos.
 */
abstract class CasoCorreos extends TestCase
{
    use RefreshDatabase;

    protected Sistema $sistema;

    protected string $llave;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sistema = Sistema::factory()->create(['codigo' => 'sgrh']);
        $this->llave = $this->sistema->generarLlave()->plainTextToken;
    }

    /** Petición autenticada como el sistema indicado (por defecto, sgrh). */
    protected function como(?string $llave = null, array $cabeceras = []): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.($llave ?? $this->llave)] + $cabeceras);
    }

    protected function correoMinimo(array $extra = []): array
    {
        return array_merge([
            'para' => ['ana@cultura.gob.do'],
            'asunto' => 'Prueba',
            'html' => '<p>Hola</p>',
        ], $extra);
    }

    /** @return Collection<int, Email> Mensajes entregados al mailer de pruebas. */
    protected function enviados(): Collection
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($enviado) => $enviado->getOriginalMessage());
    }

    /** @param  list<Address>  $direcciones */
    protected function correosDe(array $direcciones): array
    {
        return array_map(fn (Address $a) => $a->getAddress(), $direcciones);
    }
}

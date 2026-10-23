# Integrar un sistema Laravel

## 1. Variables

En el Secret y el ConfigMap de tu sistema (en local, en su `.env`):

```bash
# ConfigMap
BROKER_URL=https://<broker-api>
# Secret
BROKER_API_KEY=12|brk_...
```

Y en `config/services.php`:

```php
'broker' => [
    'url' => env('BROKER_URL'),
    'llave' => env('BROKER_API_KEY'),
],
```

## 2. Un cliente reutilizable

```php
use Illuminate\Support\Facades\Http;

function broker(): \Illuminate\Http\Client\PendingRequest
{
    return Http::baseUrl(config('services.broker.url').'/v1')
        ->withToken(config('services.broker.llave'))
        ->acceptJson()
        ->timeout(30);
}
```

## 3. Enviar un correo

```php
$respuesta = broker()
    ->withHeaders(['Idempotency-Key' => "volante-{$volante->id}"])
    ->post('/correos', [
        'para' => [$empleado->company_email],
        'cco' => ['rrhh@cultura.gob.do'],
        'asunto' => 'Volante de pago — octubre 2026',
        'html' => view('emails.volante_pago', compact('volante'))->render(),
        'solicitado_por' => auth()->user()?->email,
    ]);

$respuesta->throw();                    // 4xx/5xx → excepción
$correoId = $respuesta->json('id');     // guárdalo para consultar el estado
```

`Idempotency-Key` debe identificar **ese** correo (por ejemplo, el volante y el mes). Si tu sistema reintenta la petición por un timeout, el broker no envía otro.

## 4. Con adjuntos

Con archivos se usa multipart. Los campos de lista llevan `[]`:

```php
$respuesta = broker()
    ->withHeaders(['Idempotency-Key' => "certificacion-{$cert->id}"])
    ->attach('adjuntos[]', $pdf, 'certificacion.pdf', ['Content-Type' => 'application/pdf'])
    ->post('/correos', [
        'para[]' => $empleado->company_email,
        'asunto' => 'Tu certificación laboral',
        'html' => '<p>Adjuntamos tu certificación.</p>',
    ]);
```

## 5. Errores

| Respuesta | Qué hacer |
|---|---|
| `202` | Recibido. Guarda el `id`. |
| `401` | Revisa `BROKER_API_KEY`: falta, está mal copiada o fue revocada. |
| `422` | Datos inválidos. `errores` dice qué campo falló; corrígelo, no reintentes igual. |
| `429` | Demasiadas peticiones. Espera los segundos de `Retry-After` y reintenta. |
| `5xx` o sin conexión | Reintenta con la **misma** `Idempotency-Key`. |

## Próximamente

Un transporte de correo `broker` para Laravel: los Mailables actuales seguirán funcionando igual y solo cambiará `MAIL_MAILER=broker`.

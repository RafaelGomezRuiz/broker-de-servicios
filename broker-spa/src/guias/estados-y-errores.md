# Límites, estados y errores

## Estados de un correo

| Estado | Qué significa |
|---|---|
| `en_cola` | Recibido; espera su turno o un reintento. |
| `enviando` | Se está entregando a Office 365. |
| `enviado` | **Office 365 aceptó el mensaje.** No significa que llegó al buzón: un rebote posterior no se ve desde el broker. |
| `fallido` | No se pudo enviar. El campo `error` explica por qué. |

`GET /v1/correos/{id}` muestra además el historial: cada cambio de estado con su fecha y detalle.

## Reintentos

- **Errores técnicos** (no hay conexión con Office 365, falló el inicio de sesión, error temporal `4xx`): el broker reintenta solo hasta 5 veces, a los 1, 5, 15 y 30 minutos.
- **Rechazos del servidor** (`5xx`, por ejemplo un destinatario que no existe): el correo pasa a `fallido` sin reintentar, porque repetir daría el mismo resultado.

## Límites

| Límite | Valor |
|---|---|
| Peticiones a `/v1` por sistema | 120 por minuto |
| Correos entregados a Office 365 | ~30 por minuto, entre todos los sistemas |
| Destinatarios por correo | 500 entre `para`, `cc` y `cco` |
| Adjuntos por correo | 20 archivos y 20 MB en total |
| Correos por lote | 200 |
| Conservación del contenido | 90 días; después se borran el cuerpo y los adjuntos, y queda el registro |

## Errores de la API

Todas las respuestas de error traen un `mensaje` en español.

| Código | Causa | Qué hacer |
|---|---|---|
| `401` | Llave ausente, mal copiada o revocada, o sistema suspendido | Revisa `BROKER_API_KEY`; si sigue fallando, pide una llave nueva |
| `404` | El correo no existe o es de otro sistema | Revisa el `id` |
| `413` | La petición es demasiado grande | Reduce los adjuntos o divide el lote |
| `422` | Datos inválidos; `errores` lista los campos | Corrige y vuelve a enviar |
| `429` | Pasaste el límite de peticiones | Espera los segundos de `Retry-After` |

Un `422` de validación se ve así:

```json
{
  "mensaje": "Los datos enviados no son válidos.",
  "errores": {
    "para": ["Indica al menos un destinatario en para, cc o cco."]
  }
}
```

## Idempotencia

Con la misma `Idempotency-Key` y el mismo contenido, el broker devuelve el correo original (cabecera `Idempotent-Replayed: true`) sin enviar otro. Con la misma llave y otro contenido responde `422`: usa una llave nueva para un correo nuevo.

API central de envío de correos del Ministerio de Cultura. Los sistemas (SGRH, GestionFestibal, Intranet…) dejan de tener sus propias credenciales de correo y le piden al broker que envíe por ellos.

## Autenticación

Cada sistema tiene su propia **llave**, que se entrega una sola vez al registrarlo. Va en cada petición:

```
Authorization: Bearer 12|brk_xxxxxxxxxxxxxxxx
```

- Sin llave, con una llave revocada o con el sistema suspendido: `401`.
- Cada sistema solo ve sus propios correos; consultar el de otro responde `404`.
- Límite por sistema: `BROKER_PETICIONES_POR_MINUTO` (120 por defecto). Al pasarlo: `429` con la cabecera `Retry-After`.
- Para comprobar una llave: `GET /v1/sistema`.

## Idempotencia

Envía siempre una cabecera `Idempotency-Key` única por correo (por ejemplo `volante-2026-10-4471`). Si la petición se repite por un timeout o un reintento:

- **mismo contenido** → devuelve el correo original con `Idempotent-Replayed: true`, **sin enviar otro**;
- **contenido distinto** → `422`.

La llave es por sistema: dos sistemas pueden usar el mismo valor sin chocar.

## Cómo se envía

1. `POST /v1/correos` guarda el correo y responde **`202`** de inmediato, con el correo en `en_cola`.
2. Un proceso aparte lo entrega al servidor de correo (Office 365), respetando su límite por minuto.
3. Consulta el resultado con `GET /v1/correos/{id}`.

| Estado | Significado |
|---|---|
| `en_cola` | Esperando turno, o esperando un reintento. |
| `enviando` | Entregándose al servidor de correo. |
| `enviado` | **Office 365 aceptó el mensaje. No significa que llegó al buzón:** un rebote posterior no se ve desde el broker. |
| `fallido` | No se pudo enviar. El campo `error` dice por qué. |

**Reintentos.** Los errores técnicos (no hay conexión, falló el inicio de sesión, error temporal `4xx`) se reintentan solos hasta 5 veces: a los 1, 5, 15 y 30 minutos. Los rechazos del servidor (`5xx`, como un destinatario inexistente) pasan a `fallido` sin reintentar.

## Destinatarios y adjuntos

- `para`, `cc` y `cco` son listas. Hace falta al menos un destinatario entre las tres, y como máximo 500 en total (límite de Office 365).
- Todos los correos salen desde la cuenta institucional configurada en el broker.
- **Sin adjuntos** se puede enviar JSON. **Con adjuntos**, `multipart/form-data` con `adjuntos[]`: hasta 20 archivos y `BROKER_ADJUNTO_MAX_MB` (20 MB por defecto) en total. El destinatario los ve con su nombre original.
- Envíos masivos (volantes, recordatorios): `POST /v1/correos/lote`, hasta 200 correos individuales por petición. Si uno no es válido, no se encola ninguno.

## Privacidad y retención

- Las respuestas nunca devuelven el cuerpo del correo.
- Los adjuntos se borran del broker en cuanto el correo se envía.
- Pasados `BROKER_RETENCION_DIAS` (90 por defecto) se borran el cuerpo y los adjuntos que queden. Se conservan sistema, destinatarios, asunto, estado e historial.

## Errores

Todas las respuestas de error son JSON con un `mensaje` en español. Las de validación (`422`) incluyen además `errores`, con la lista de problemas por campo:

```json
{
  "mensaje": "Los datos enviados no son válidos.",
  "errores": { "para": ["Indica al menos un destinatario en para, cc o cco."] }
}
```

## Probar con Postman

1. Importa esta especificación: *Import → URL →* `/docs/api.json`.
2. Crea un Environment con `api_key` (tipo *secret*) y usa *Authorization → Bearer Token → `{{api_key}}`* en la colección.
3. Agrega el header `Idempotency-Key: {{$guid}}`.
4. Para adjuntos: *Body → form-data* con campos `adjuntos[]` de tipo *File*.

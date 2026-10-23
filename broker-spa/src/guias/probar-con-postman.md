# Probar con Postman

Para probar necesitas una llave de un sistema de pruebas. En desarrollo, los correos no salen de verdad: los atrapa Mailpit.

## 1. Importa el contrato

En Postman: **Import → URL** y pega la dirección del contrato OpenAPI del broker:

```
https://<broker-api>/docs/api.json
```

Se crea una colección con todos los endpoints de `/v1`, sus campos y ejemplos. Si la API cambia, vuelve a importar.

## 2. Crea un Environment

| Variable | Valor | Tipo |
|---|---|---|
| `baseUrl` | `https://<broker-api>/v1` | default |
| `api_key` | tu llave de pruebas | secret |

## 3. Configura la colección

- **Authorization → Bearer Token → `{{api_key}}`**. Todas las peticiones lo heredan.
- **Headers →** `Idempotency-Key: {{$guid}}`. Genera un valor nuevo en cada envío.

## 4. Comprueba la llave

`GET {{baseUrl}}/sistema` debe responder `200` con el código de tu sistema. Un `401` significa que la llave falta, está mal copiada o fue revocada.

## 5. Envía un correo

`POST {{baseUrl}}/correos` con **Body → raw → JSON**:

```json
{
  "para": ["tu.correo@cultura.gob.do"],
  "asunto": "Prueba desde Postman",
  "html": "<p>Hola desde el broker.</p>"
}
```

Responde `202` con el correo en `en_cola`. Copia el `id` y consulta `GET {{baseUrl}}/correos/<id>` hasta ver `enviado`.

## 6. Con adjuntos

Cambia a **Body → form-data**:

| Key | Tipo | Valor |
|---|---|---|
| `para[]` | Text | `tu.correo@cultura.gob.do` |
| `asunto` | Text | `Prueba con adjunto` |
| `html` | Text | `<p>Va un archivo.</p>` |
| `adjuntos[]` | File | elige un PDF |

Para varios destinatarios o adjuntos, repite la fila con la misma key.

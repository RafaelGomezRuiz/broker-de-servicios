# Copia oculta, adjuntos y envíos masivos

## Destinatarios

`para`, `cc` y `cco` son listas. Necesitas al menos un destinatario entre las tres, y como máximo 500 en total (es el límite de Office 365 por mensaje).

- **`para`**: destinatarios principales.
- **`cc`**: con copia; todos los ven.
- **`cco`**: con copia oculta; nadie ve a estos destinatarios, ni entre ellos.

Se puede enviar **solo con `cco`**, por ejemplo una felicitación a toda una lista sin que se vean las direcciones:

```json
{
  "cco": ["ana@cultura.gob.do", "luis@cultura.gob.do", "maria@cultura.gob.do"],
  "asunto": "¡Feliz cumpleaños!",
  "html": "<p>…</p>"
}
```

## Adjuntos

- Solo con `multipart/form-data`, en el campo `adjuntos[]` (repítelo para varios).
- Hasta **20 archivos** y **20 MB en total** por correo (el administrador puede cambiar el total).
- El destinatario ve el **nombre original** del archivo.
- El broker borra los archivos en cuanto el correo se envía.

## Envíos masivos: el lote

Para volantes de pago, recordatorios o cualquier envío donde **cada persona recibe su propio correo**, usa `POST /v1/correos/lote`:

```json
{
  "correos": [
    { "para": ["empleado1@cultura.gob.do"], "asunto": "Tu volante de octubre", "html": "<p>…</p>" },
    { "para": ["empleado2@cultura.gob.do"], "asunto": "Tu volante de octubre", "html": "<p>…</p>" }
  ]
}
```

- Hasta **200 correos por petición**. Para 1.400 volantes, envía 7 lotes.
- **Todo o nada**: si un correo no es válido, no se encola ninguno y el error indica cuál (por ejemplo `correos.3.para.0`).
- Cada correo puede llevar sus propios adjuntos. En multipart los campos se nombran `correos[0][para][]`, `correos[0][adjuntos][]`, etc.
- Usa una `Idempotency-Key` por lote (por ejemplo `volantes-2026-10-lote-3`).
- La respuesta trae `lote_id`; sigue el avance con `GET /v1/correos?lote_id=<lote_id>`.

Los correos de un lote salen al ritmo que permite Office 365 (unos 30 por minuto): 1.400 volantes tardan cerca de 50 minutos en salir todos.

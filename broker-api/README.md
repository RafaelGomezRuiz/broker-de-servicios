# broker-api

API central de envío de correos del Broker de Servicios (Laravel 12, PHP 8.3, MySQL).
El alcance y el avance están en [`../docs/plan-de-trabajo.md`](../docs/plan-de-trabajo.md).

## Desarrollo local (Laragon)

PHP y Composer vienen de Laragon. En Git Bash:

```bash
export PATH="/c/laragon/bin/php/php-8.3.16-Win32-vs16-x64:/c/laragon/bin/composer:$PATH"
```

1. Base de datos (MySQL de Laragon, usuario `root` sin contraseña):

   ```bash
   /c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS broker_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

2. Instalar y preparar (copia `.env.example` a `.env`, genera `APP_KEY` y migra):

   ```bash
   composer setup
   php artisan jwt:secret
   ```

3. Mailpit, para que ningún correo salga de verdad. Laragon ya lo trae:

   ```bash
   /c/laragon/bin/mailpit/1.22.3/mailpit.exe --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025
   ```

   Bandeja: <http://localhost:8025>

4. Servidor y cola:

   ```bash
   php artisan serve --port=8091
   php artisan queue:listen --queue=correos,default
   ```

   Usa cualquier puerto libre; en esta máquina el 8000 suele estar ocupado.
   Sin `--queue=correos` la cola no procesa los envíos: quedan `en_cola`.

5. Comprobar: `GET http://localhost:8091/health` → `{"estado":"ok", ...}`

6. Sistema de pruebas y su llave (se muestra una sola vez):

   ```bash
   php artisan db:seed
   ```

   Con esa llave: `GET /v1/sistema` con el header `Authorization: Bearer <llave>`.
   Si la pierdes: `php artisan sistemas:nueva-llave pruebas`.

## Sistemas y llaves

| Comando | Qué hace |
|---|---|
| `sistemas:crear sgrh --nombre="..." --responsable=correo` | Registra el sistema y muestra su primera llave |
| `sistemas:listar` | Todos los sistemas, su estado y último uso |
| `sistemas:listar sgrh` | Las llaves de un sistema (ID, nombre, último uso) |
| `sistemas:nueva-llave sgrh` | Otra llave (rotación o pérdida); las anteriores siguen activas |
| `sistemas:revocar-llave sgrh <id>` | Revoca una llave al instante |
| `sistemas:suspender sgrh [--reactivar]` | Corta o devuelve el acceso de todas sus llaves |

En Kubernetes se ejecutan en el pod de la API: `kubectl exec deploy/broker-api -- php artisan sistemas:listar`.

## Enviar correos

Todas las peticiones llevan `Authorization: Bearer <llave>`. Se recomienda `Idempotency-Key` en cada envío: si la petición se repite con la misma llave y el mismo contenido, devuelve el correo original y no envía otro.

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | `/v1/correos` | Encola un correo. `202` con el correo en `en_cola`. |
| `POST` | `/v1/correos/lote` | Hasta 200 correos individuales en `correos[]`. Todo o nada. |
| `GET` | `/v1/correos/{id}` | Estado, destinatarios, adjuntos e historial. |
| `GET` | `/v1/correos` | Listado paginado. Filtros: `estado`, `lote_id`, `destinatario`, `solicitado_por`, `desde`, `hasta`, `por_pagina`. |

Campos de un correo: `para[]`, `cc[]`, `cco[]` (al menos uno, máximo 500 entre los tres), `asunto`, `html` y/o `texto`, `solicitado_por` (opcional) y `adjuntos[]` (multipart, hasta 20 y `BROKER_ADJUNTO_MAX_MB` en total).

Sin adjuntos se puede enviar JSON; con adjuntos, `multipart/form-data`:

```bash
curl -H "Authorization: Bearer $LLAVE" -H "Idempotency-Key: volante-2026-10-4471"   -F "para[]=ana@cultura.gob.do" -F "cco[]=rrhh@cultura.gob.do" -F "asunto=Volante de octubre"   --form-string "html=<p>Adjunto tu volante</p>" -F "adjuntos[]=@volante.pdf;type=application/pdf"   http://localhost:8091/v1/correos
```

(En curl, `html` va con `--form-string`: con `-F`, un valor que empieza con `<` se lee como archivo.)

Estados: `en_cola → enviando → enviado | fallido`. **`enviado` significa que Office 365 aceptó el mensaje, no que llegó al buzón.** Los errores técnicos (conexión, inicio de sesión, `4xx`) se reintentan hasta 5 veces (1, 5, 15 y 30 min); los rechazos del servidor (`5xx`) fallan sin reintentar. Los adjuntos se borran del broker al enviarse; cuerpos y adjuntos de correos con más de `BROKER_RETENCION_DIAS` los borra `correos:purgar` cada noche.

## Usuarios de la SPA

Personas que entran al portal (solo lectura). La contraseña se pide de forma oculta, nunca como argumento.

| Comando | Qué hace |
|---|---|
| `usuarios:crear correo --nombre="..."` | Crea un usuario |
| `usuarios:listar` | Usuarios, estado, sesiones abiertas y último acceso |
| `usuarios:clave correo` | Cambia la contraseña y cierra sus sesiones |
| `usuarios:desactivar correo [--reactivar]` | Corta su acceso al instante (cierra sus sesiones) o lo devuelve |

### Cómo funciona la sesión

| Pieza | Dónde vive | Dura |
|---|---|---|
| Access token (JWT) | En memoria de la SPA; se envía como `Authorization: Bearer` | `JWT_ACCESS_TTL` (15 min) |
| Refresh token (opaco) | Cookie `broker_refresh`: httpOnly, `SameSite=Strict`, solo en `/admin/auth`, `Secure` en producción. En la base, solo su hash | Hasta `JWT_REFRESH_TTL` (8 h) de vida máxima, o `JWT_INACTIVIDAD_TTL` (30 min) sin uso |

- `POST /admin/auth/login` → access token en el cuerpo + cookie. 5 intentos fallidos por correo e IP bloquean un minuto.
- `POST /admin/auth/refresh` → access token nuevo y **cookie nueva** (rotación). Si llega una cookie ya reemplazada, se asume robo y la sesión se revoca; dos pestañas que renuevan a la vez (30 s de margen) no cuentan.
- `POST /admin/auth/logout` → revoca la sesión y el access token al instante, aunque este ya haya vencido.
- Cada petición al panel comprueba que la sesión siga viva: un logout, un usuario desactivado o la inactividad cortan el acceso sin esperar a que venza el JWT.
- Solo lectura: `GET /admin/auth/me`, `/admin/servicios`, `/admin/sistemas` (sin llaves), `/admin/correo/estado`.
- CORS: solo `SPA_URL`, con credenciales. Los sistemas (`/v1`) no usan CORS.

## Documentación de la API

- Página: <http://localhost:8091/docs/api>
- Contrato OpenAPI 3.1: <http://localhost:8091/docs/api.json> (para Postman y para la SPA)

Se genera sola a partir del código con [Scramble](https://scramble.dedoc.co). Dónde se escribe cada parte:

| Qué | Dónde |
|---|---|
| Portada (guía general) | `resources/docs/introduccion.md` |
| Resumen y explicación de un endpoint | PHPDoc del método del controlador |
| Campos del cuerpo | Comentarios en `EnviarCorreoRequest::rules()` |
| Campos de la respuesta | Comentarios en `CorreoResource::toArray()` |
| Código de respuesta (`202`) | PHPDoc `@status` / `@body` sobre el `return` |
| Formato de errores, `404`, `413`, `429` | `app/Documentacion/AjustesOpenApi.php` |

Solo se documenta `/v1`; `/health` y `/admin` quedan fuera. `tests/Feature/DocumentacionApiTest.php` falla si la documentación deja de coincidir con la API.

## Cuenta de correo

```bash
php artisan correo:verificar                                  # conexión + login
php artisan correo:verificar --enviar-prueba=tu@cultura.gob.do  # además envía un correo real
```

Cada verificación se guarda en `correo_verificaciones` (30 días) y el scheduler la repite cada hora. Si falla, muestra en qué etapa (conexión, autenticación o envío), qué revisar y el detalle técnico del servidor.

## Pruebas

```bash
php artisan test
```

Usan SQLite en memoria (`phpunit.xml`), no tocan la base local.

## Despliegue

QA, producción, Secret, ConfigMap y primer arranque: [`DESPLIEGUE.md`](DESPLIEGUE.md).

## Variables

Todas están en [`.env.example`](.env.example), separadas según vengan del **Secret** (`broker-api-secrets`) o del **ConfigMap** (`broker-api-config`). En Kubernetes no existe `.env`.

## Docker

Una sola imagen; la variable `ROL` decide qué corre cada Deployment:

| `ROL` | Qué arranca |
|---|---|
| `api` (por defecto) | php-fpm + nginx; además corre las migraciones |
| `worker` | `queue:work` sobre las colas `correos,default` |
| `scheduler` | `schedule:work` |

```bash
docker build -t broker-api .
```

Sin `APP_KEY` el contenedor no arranca: a propósito, para no generar una clave distinta en cada pod.

## Rutas

| Ruta | Estado |
|---|---|
| `GET /health` | Listo |
| `GET /v1/sistema` | Listo (requiere llave) |
| `/v1/correos`, `/v1/correos/lote`, `/v1/correos/{id}` | Listo (requiere llave) |
| `/admin/auth/*`, `/admin/servicios`, `/admin/sistemas`, `/admin/correo/estado` | Listo (sesión de la SPA) |

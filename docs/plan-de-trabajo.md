# Broker de Servicios — Plan de trabajo

> Fase actual: **v1, servicio de Correo**. MinIO y otros servicios vienen después.
> Marca cada tarea con `[x]` al terminarla.

---

## 1. Qué vamos a construir

Dos proyectos:

| Proyecto | Tecnología | Qué hace |
|---|---|---|
| `broker-api` | Laravel 12, PHP 8.3, MySQL | Envía los correos de todos los sistemas. Los sistemas la consumen con una **API key**. |
| `broker-spa` | Vue 3, Vite, TypeScript, Tailwind, Pinia | Portal con **login JWT**. Muestra los servicios, los sistemas integrados, el estado de la cuenta de correo y la documentación. **Solo lectura.** |

Reglas fijas:

- **Credenciales → Secret.** Hosts, puertos, correos y demás valores → **ConfigMap**. Igual que en SGRH.
- **Una sola cuenta de correo** (SMTP de Office 365, `aplicaciones@`), con las mismas variables `MAIL_*` que ya usan los sistemas. Todos los correos salen con ese remitente.
- **Sistemas y llaves** se guardan en la base de datos y se gestionan con comandos `artisan`.
- **Sin permisos por sistema en la v1:** un sistema con una llave válida puede usar todos los endpoints `/v1`.
- Las **personas** usan JWT (en la SPA); los **sistemas** usan una API key (en `/v1`). Nunca se mezclan.

```
SGRH, Festival, ...  ──(API key)──►  broker-api  /v1/correos  ──►  cola  ──►  worker  ──►  SMTP Office 365
                                         ▲
Tú (navegador)  ──(JWT)──►  broker-spa ──┘  /admin/... (solo lectura) + /docs/api.json
```

---

## 2. Variables

### `broker-api`

**Secret** (`broker-api-secrets`)

| Variable | Para qué |
|---|---|
| `APP_KEY` | Clave de Laravel |
| `DB_PASSWORD` | Base de datos |
| `JWT_SECRET` | Firma de los JWT de la SPA |
| `MAIL_USERNAME` | Cuenta `aplicaciones@cultura.gob.do` (en Secret, como en GestionFestibal) |
| `MAIL_PASSWORD` | Contraseña de `aplicaciones@cultura.gob.do` |

**ConfigMap** (`broker-api-config`)

| Variable | Ejemplo |
|---|---|
| `APP_URL` | `https://broker-api.minc.gob.do` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` | |
| `QUEUE_CONNECTION` | `database` |
| `SPA_URL` | Origen permitido por CORS y para la cookie de refresh |
| `JWT_ACCESS_TTL` | `15` (minutos) |
| `JWT_REFRESH_TTL` | `480` (minutos, vida máxima de la sesión) |
| `JWT_INACTIVIDAD_TTL` | `30` (minutos sin uso) |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp.office365.com` |
| `MAIL_PORT` | `587` |
| `MAIL_FROM_ADDRESS` | `aplicaciones@cultura.gob.do` |
| `MAIL_FROM_NAME` | `Ministerio de Cultura` |
| `MAIL_EHLO_DOMAIN` | `cultura.gob.do` |
| `BROKER_ENVIO_POR_MINUTO` | `30` (límite de Office 365) |
| `BROKER_PETICIONES_POR_MINUTO` | `120` (peticiones a `/v1` por sistema) |
| `BROKER_ADJUNTO_MAX_MB` | `20` (tamaño total por correo) |
| `BROKER_RETENCION_DIAS` | `90` |

### `broker-spa`

**ConfigMap** (`broker-spa-config`). La URL se lee al arrancar, como en `SGRH-frontend` (`entrypoint.sh` → `config.js`):

| Variable | Ejemplo |
|---|---|
| `API_BASE_URL` | `https://broker-api.minc.gob.do` |

---

## 3. Endpoints v1

### Para los sistemas (API key en `Authorization: Bearer …`)

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | `/v1/correos` | Encola un correo con `para[]`, `cc[]`, `cco[]`, `asunto`, `html`, `texto` (opcional) y `adjuntos[]`. Header `Idempotency-Key`. Responde `202` con el `id`. |
| `POST` | `/v1/correos/lote` | Un mensaje individual por destinatario (volantes, recordatorios). |
| `GET` | `/v1/correos/{id}` | Estado e historial del correo. |
| `GET` | `/v1/correos` | Listado de los correos **del sistema que consulta**, con filtros por fecha y estado. |
| `GET` | `/v1/sistema` | Comprueba la llave: devuelve qué sistema es. |

### Para la SPA (JWT, no se documentan ni se prueban a mano)

| Método | Ruta |
|---|---|
| `POST` | `/admin/auth/login` · `/admin/auth/refresh` · `/admin/auth/logout` |
| `GET` | `/admin/auth/me` |
| `GET` | `/admin/servicios` |
| `GET` | `/admin/sistemas` |
| `GET` | `/admin/correo/estado` (resultado de la última verificación de la cuenta) |

### Otros

| Ruta | Qué hace |
|---|---|
| `GET /docs/api.json` | Contrato OpenAPI, **solo de `/v1`** |
| `GET /health` | Sondas de Kubernetes |

---

## 4. Plan por fases

### Fase 0 — Fundaciones (`broker-api`)

- [x] Crear el proyecto Laravel 12 `broker-api` (base de datos local `broker_api`)
- [x] Repositorio git local en `BrokerDeServicios` (rama `main`)
- [ ] Primer commit y remoto en Azure DevOps
- [x] Toda la configuración leída solo de variables de entorno; `.env.example` con todas las variables de la sección 2
- [x] Dockerfile + supervisord con una imagen y tres roles (`ROL=api|worker|scheduler`), tomando como base `api-SGRH/docker`
- [x] Probar `docker build`
- [x] Mailpit en desarrollo para atrapar los correos (el de Laragon; probado con un envío con CCO)
- [x] `GET /health` (200 / 503, sin revelar detalles) con sus pruebas
- [x] Pipeline de Azure DevOps (`azure-pipelines.yml`), solo la etapa de build
- [ ] Primera corrida del pipeline (requiere el remoto)

### Fase 1 — Sistemas y llaves

- [x] Tabla `sistemas` (código, nombre, responsable, suspendido). Sin columna de ambiente: cada ambiente tiene su propio broker y su propia base
- [x] Llaves con Sanctum, asociadas al modelo `Sistema`, con prefijo `brk_`; se guarda solo el hash
- [x] Comandos: `sistemas:crear`, `sistemas:listar [codigo]`, `sistemas:nueva-llave`, `sistemas:revocar-llave`, `sistemas:suspender [--reactivar]`
- [x] La llave se muestra **una sola vez** al crearla
- [x] `/v1` protegido: sin llave, llave inválida, revocada o sistema suspendido → `401` (mismo mensaje en todos los casos)
- [x] Registrar el último uso de cada llave
- [x] Límite de peticiones por sistema (`BROKER_PETICIONES_POR_MINUTO`) → `429` con `Retry-After`
- [x] *Seeder* local: sistema `pruebas` con su llave (no corre en producción)
- [x] `GET /v1/sistema` para comprobar una llave
- [x] Pruebas automáticas (16 nuevas)

### Fase 2 — Conexión de correo

- [x] Usar el mailer `smtp` estándar de Laravel con las variables `MAIL_*` (tiempo de espera de 30 s)
- [x] Comando `correo:verificar`: conexión + TLS + login SMTP con la cuenta configurada
- [x] Opción `--enviar-prueba=<correo>`: envía un correo real de prueba
- [x] Guardar el resultado (etapa, código SMTP, mensaje traducido, detalle técnico) en `correo_verificaciones`; se conservan 30 días
- [x] Traducir los errores comunes: conexión, DNS, TLS, `5.7.139` (login bloqueado/MFA), `5.7.3` (contraseña), `5.7.60` (Send As), `5.7.57`, errores temporales `4xx`
- [x] Verificación automática cada hora en el scheduler (`--programada`)
- [x] Pruebas automáticas (14 nuevas) y prueba en vivo con Mailpit (éxito, envío y fallo de conexión)
- [x] Probado en local con la cuenta real de Office 365 (2026-10-22)

### Fase 3 — Servicio de Correo

- [x] Tablas `correos` (con `sistema_id`, IDs ULID), `correo_lotes`, `correo_destinatarios` (tipo `para`/`cc`/`cco`), `correo_adjuntos` (metadatos), `correo_eventos` (historial)
- [x] `POST /v1/correos`: validación de correos, al menos un destinatario, asunto y cuerpo (`html` y/o `texto`), mensajes en español
- [x] Varios destinatarios en `para`, `cc` y `cco` (máximo 500 entre los tres, límite de Office 365); se puede enviar solo con `cco`
- [x] Adjuntos en `multipart/form-data`, hasta 20 por correo, con límite total `BROKER_ADJUNTO_MAX_MB`; conservan su nombre original
- [x] Guardado temporal de los adjuntos en el disco privado; se borran al enviarse
- [x] Idempotencia: misma `Idempotency-Key` del mismo sistema → devuelve el correo existente (`Idempotent-Replayed: true`); con otro contenido → `422`
- [x] Job `EnviarCorreo` en la cola `correos`
- [x] Reintentos solo ante errores técnicos (conexión, login, `4xx`): hasta 5 intentos a 1, 5, 15 y 30 min; los rechazos `5xx` fallan sin reintentar
- [x] Respetar `BROKER_ENVIO_POR_MINUTO`, compartido entre todos los workers
- [x] Estados `en_cola → enviando → enviado | fallido`, cada cambio registrado en `correo_eventos`
- [x] `POST /v1/correos/lote`: hasta 200 correos individuales por petición, todo o nada, idempotente
- [x] `GET /v1/correos/{id}` y `GET /v1/correos` (filtros y paginación); un correo de otro sistema responde `404`
- [x] Campo opcional `solicitado_por` (el usuario del sistema que originó el correo)
- [x] `correos:purgar` cada noche a las 2:00: borra cuerpos y adjuntos con más de `BROKER_RETENCION_DIAS` (los metadatos se conservan)
- [x] Logs solo con IDs: sin cuerpos, destinatarios ni adjuntos
- [x] Pruebas automáticas (23 nuevas) y prueba en vivo con worker real y Mailpit (para/cc/cco, adjunto PDF, lote, idempotencia)

### Fase 4 — Documentación de la API

- [x] Scramble documenta solo el prefijo `/v1` y publica `/docs/api.json` (OpenAPI 3.1) y `/docs/api` (página de consulta, sin botón de pruebas)
- [x] Portada con la guía general (`resources/docs/introduccion.md`): autenticación, idempotencia, estados, reintentos, adjuntos, retención, errores y Postman
- [x] Descripciones y ejemplos en cada endpoint y en cada campo (cuerpo y respuesta)
- [x] Documentar que `enviado` significa "aceptado por Office 365", no "entregado"
- [x] Errores documentados con el formato real (`mensaje`/`errores`), `202` en los envíos, `404` y `429`
- [x] Pruebas automáticas que avisan si la documentación deja de coincidir con la API (4 nuevas)
- [ ] Probar la importación en Postman desde `/docs/api.json`

### Fase 5 — Autenticación y endpoints para la SPA

- [x] Tablas `usuarios` y `sesiones` (reemplazan `users`, `sessions` y `password_reset_tokens` de Laravel)
- [x] Comandos `usuarios:crear`, `usuarios:listar`, `usuarios:clave`, `usuarios:desactivar [--reactivar]`; la contraseña se pide oculta (mínimo 12 caracteres)
- [x] JWT con `tymon/jwt-auth` (la misma librería que SGRH); el JWT lleva el ID de su sesión
- [x] Access token de `JWT_ACCESS_TTL` minutos
- [x] Refresh token opaco en cookie `httpOnly` + `SameSite=Strict` + `Secure` (en producción), limitada a `/admin/auth`, guardado con hash y rotado en cada uso
- [x] Detección de reutilización de un refresh viejo (revoca la sesión), con margen de 30 s para varias pestañas
- [x] Expiración por inactividad (`JWT_INACTIVIDAD_TTL`) y absoluta (`JWT_REFRESH_TTL`)
- [x] `logout` revoca el refresh token y el access token al instante (aunque el access token ya haya vencido)
- [x] Cada petición del panel comprueba que la sesión siga viva (logout, usuario desactivado o inactividad cortan al instante)
- [x] Login: mismo mensaje para cualquier fallo y bloqueo tras 5 intentos por correo e IP
- [x] Endpoints de lectura: `me`, `servicios`, `sistemas` (nunca devuelven llaves), `correo/estado` (última verificación y correos de las últimas 24 h)
- [x] CORS limitado a `SPA_URL`, con credenciales
- [x] Limpieza diaria de sesiones vencidas hace más de 30 días
- [x] Pruebas automáticas (24 nuevas) y prueba en vivo (login, panel, rotación, CORS, logout)

### Fase 6 — `broker-spa`

- [x] Proyecto Vue 3 + Vite + TypeScript + Tailwind + Pinia, con las versiones de `SGRH-frontend`
- [x] `config.js` en runtime desde el ConfigMap (`entrypoint.sh`)
- [x] Login (mensajes del broker en español, recuerda a qué página se iba)
- [x] Al abrir: `refresh` con la cookie **antes** de mostrar cualquier ruta protegida, con *splash* "Comprobando tu sesión…"
- [x] Sesión válida → entra (también al recargar la página); vencida → login de inmediato
- [x] Access token solo en memoria (nunca en `localStorage`)
- [x] Ante varios `401` simultáneos, un solo refresh y las demás peticiones esperan; renovación previa si el token está por vencer
- [x] Sin renovaciones ni consultas por temporizador: la sesión vence por inactividad
- [x] Inicio: estado de la cuenta, recorrido de los correos de las últimas 24 h y servicios (Correo activo; Archivos "próximamente")
- [x] Servicio Correo → cuenta de envío y referencia de la API desde `/docs/api.json` con Scalar, sin consola de pruebas, sin telemetría ni promociones
- [x] Guías:
  - [x] Qué es el broker y cómo pedir una llave
  - [x] Probar con Postman
  - [x] Integrar un sistema Laravel
  - [x] CCO, adjuntos y lote
  - [x] Límites, estados y códigos de error
- [x] Integraciones: sistemas registrados (llaves activas, correos de 30 días, último uso) y estado de la cuenta de correo
- [x] Dockerfile con nginx, como `SGRH-frontend`, y build en `azure-pipelines.yml`
- [x] Prueba en vivo en el navegador: login, recarga con sesión, inicio, correo, referencia, sistemas, guías, móvil y cierre de sesión
- [ ] Probar `docker build` de `broker-spa`

### Fase 7 — Despliegue en Kubernetes

> Detalle completo, con los valores de QA y producción: [`broker-api/DESPLIEGUE.md`](../broker-api/DESPLIEGUE.md) y [`broker-spa/DESPLIEGUE.md`](../broker-spa/DESPLIEGUE.md).

- [x] Documentar el despliegue: objetos de Kubernetes, Secret, ConfigMap, base de datos, primer arranque y diferencias QA/producción
- [x] `trustProxies` para funcionar detrás del Ingress (HTTPS e IP real del usuario)

- [ ] Secret y ConfigMap de `broker-api` y de `broker-spa`
- [ ] Deployments `broker-api` (HTTP), `broker-worker-correo` (cola) y `broker-scheduler`
- [ ] Volumen compartido para los adjuntos temporales (`storage/app/private`): la API los guarda y el worker los lee
- [ ] Sondas con `/health`
- [ ] Decidir la exposición: Service interno si todos los sistemas están en el clúster; Ingress si alguno está fuera
- [ ] Ejecutar `correo:verificar` en producción
- [ ] Crear el sistema `pruebas` en producción y hacer un envío real a un correo propio

### Fase 8 — Piloto con SGRH

- [ ] Transporte de correo `broker` en SGRH: los 21 Mailables siguen igual
- [ ] Llave de SGRH en su Secret (`BROKER_API_KEY`); URL en su ConfigMap (`BROKER_URL`)
- [ ] Cambiar `MAIL_MAILER=broker` en desarrollo y probar todos los flujos
- [ ] Volantes de pago por `/v1/correos/lote`
- [ ] Pasar a producción
- [ ] Quitar `MAIL_PASSWORD` del Secret de SGRH

---

## 5. Cómo probar como desarrollador

1. En local, el *seeder* crea el sistema `pruebas` y su llave (o `php artisan sistemas:crear pruebas`).
2. Postman → *Import* → `http://localhost:8000/docs/api.json`.
3. Environment: `broker_url` y `api_key` (tipo *secret*).
4. En la colección: *Authorization → Bearer Token → `{{api_key}}`* y el header `Idempotency-Key: {{$guid}}`.
5. Adjuntos: *Body → form-data*, campos `adjuntos[]` de tipo *File*.
6. Los correos aparecen en Mailpit: `http://localhost:8025`.

---

## 6. Después de la v1 (no entra ahora)

- [ ] Sección para varias cuentas y proveedores (SMTP, Graph) y remitentes por sistema (p. ej. `rrhh@` para cumpleaños)
- [ ] Proveedor Graph y retiro de correos (*recall*): solo funciona con correos enviados por Graph
- [ ] Integrar GestionFestibal e Intranet
- [ ] Permisos por sistema (remitentes y acciones)
- [ ] Gestión de sistemas y llaves desde la SPA
- [ ] Plantillas centralizadas
- [ ] Servicio de Archivos (MinIO)
- [ ] OAuth / Entra ID para los sistemas

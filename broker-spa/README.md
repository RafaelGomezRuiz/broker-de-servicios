# broker-spa

Portal del Broker de Servicios (Vue 3, Vite, TypeScript, Tailwind, Pinia). Muestra los servicios, el estado del correo, los sistemas integrados, la referencia de la API y las guías de integración. **Solo lectura**: la gestión se hace con los comandos `artisan` del broker-api.

El alcance y el avance están en [`../docs/plan-de-trabajo.md`](../docs/plan-de-trabajo.md).

## Desarrollo local

Necesita el broker-api corriendo (ver su README) y un usuario creado con `php artisan usuarios:crear`.

```bash
cp .env.example .env
npm install
npm run dev
```

- La SPA corre en <http://localhost:5173> y la API en <http://localhost:8091>. Usa **localhost** en las dos: la cookie de sesión es `SameSite=Strict` y con `127.0.0.1` no viaja.
- El puerto de la SPA debe coincidir con `SPA_URL` del broker-api (CORS). Si el 5173 está ocupado, usa otro (`npx vite --port 5180`) y arranca la API con ese origen: `SPA_URL=http://localhost:5180 php artisan serve --host=localhost --port=8091`.
- `php artisan serve` atiende de a una petición (en Windows no admite varios workers), así que el portal tarda un par de segundos en cargar cada pantalla. Para que vaya rápido, sirve el broker-api con el nginx o Apache de Laragon.

```bash
npm run build       # tipos + build de producción
npm run type-check  # solo tipos
```

## Pantallas

| Ruta | Qué muestra |
|---|---|
| `/entrar` | Inicio de sesión |
| `/` | Estado de la cuenta de correo, recorrido de los correos de las últimas 24 h y servicios |
| `/correo` | Cuenta de envío, enlaces a la referencia y a las guías |
| `/correo/referencia` | Referencia de la API (Scalar, leída de `/docs/api.json`), sin consola de pruebas |
| `/integraciones` | Sistemas registrados, sus llaves activas y uso |
| `/guias`, `/guias/:slug` | Guías en Markdown (`src/guias/*.md`) |

## Sesión

- El **access token** vive solo en memoria (`src/stores/sesion.ts`), nunca en `localStorage`.
- Al abrir la app se pide un token con la cookie httpOnly del refresh **antes** de mostrar cualquier pantalla (se ve "Comprobando tu sesión…"). Sin sesión válida, va directo a `/entrar`.
- Antes de cada petición, si el token está por vencer se renueva; ante un `401` se renueva **una vez** y se repite. Varias peticiones a la vez comparten **una sola** renovación (`src/api/cliente.ts`).
- **No hay renovaciones ni consultas por temporizador**: sin actividad, la sesión vence por inactividad (`JWT_INACTIVIDAD_TTL` en el broker-api), como debe.

## Docker y Kubernetes

QA, producción y lo que debe coincidir con el broker-api: [`DESPLIEGUE.md`](DESPLIEGUE.md).

Una sola imagen para todos los ambientes, como SGRH-frontend. La URL del API no se hornea en el build: `docker/entrypoint.sh` escribe `/config.js` al arrancar con el ConfigMap.

| ConfigMap `broker-spa-config` | Ejemplo |
|---|---|
| `API_BASE_URL` | `https://broker-api.minc.gob.do` |

```bash
docker build -t broker-spa .
docker run -p 8080:8080 -e API_BASE_URL=http://localhost:8091 broker-spa
```

## Agregar una guía

1. Escribe el Markdown en `src/guias/<slug>.md`.
2. Regístrala en `src/guias/index.ts` (título y resumen).

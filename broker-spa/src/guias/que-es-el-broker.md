# Qué es el broker y cómo pedir una llave

El Broker de Servicios es una API central del Ministerio. Los sistemas (SGRH, GestionFestibal, Intranet…) le piden que haga tareas comunes por ellos. El primer servicio es el **correo**; después vendrá el almacenamiento de archivos.

## Qué cambia en tu sistema

Hoy cada sistema guarda en su configuración la contraseña de la cuenta de correo y se conecta por SMTP. Con el broker:

- Tu sistema **ya no guarda la contraseña del correo**. Solo guarda su llave del broker.
- Envías el correo con una petición HTTP y el broker se encarga de la cola, los reintentos y el límite de Office 365.
- Queda registro de cada correo: quién lo pidió, a quién, cuándo y cómo terminó.
- Si una llave se filtra, se revoca solo esa; los demás sistemas siguen funcionando.

## Cómo pedir una llave

1. Pide al administrador del broker que registre tu sistema. Indica el nombre del sistema y el correo del responsable técnico.
2. El administrador te entrega la llave **una sola vez**. Tiene esta forma: `12|brk_…`.
3. Guárdala en el **Secret** de Kubernetes de tu sistema como `BROKER_API_KEY`. La URL del broker va en el **ConfigMap** como `BROKER_URL`.
4. Comprueba que funciona: `GET /v1/sistema` con el header `Authorization: Bearer <llave>` devuelve el nombre de tu sistema.

Cada ambiente tiene su propia llave: la de desarrollo no sirve en producción.

## Si pierdes la llave

No se puede recuperar: en el broker solo queda su huella. Pide una nueva; la anterior se puede mantener activa unos días mientras haces el cambio y luego se revoca.

## Lo que el broker no hace (todavía)

- Todos los correos salen desde la cuenta institucional configurada en el broker; aún no se puede elegir otro remitente.
- No confirma la llegada al buzón: «enviado» significa que Office 365 aceptó el mensaje.
- No retira correos ya enviados.

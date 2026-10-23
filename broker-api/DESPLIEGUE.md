# Despliegue del broker-api

Qué necesita el broker-api para correr en QA y en producción: objetos de Kubernetes, variables (Secret y ConfigMap), base de datos y pasos del primer arranque.

| | QA | Producción |
|---|---|---|
| Imagen | `acrmincprodca6382.azurecr.io/broker-api:<tag>` | La **misma** imagen y tag que se probó en QA |
| Host | `broker-api-qa.minc.gob.do` | `broker-api.minc.gob.do` ⚠ |
| SPA que lo usa | `broker-qa.minc.gob.do` | `broker.minc.gob.do` ⚠ |
| Namespace | `broker` ⚠ | `broker` ⚠ |
| Manifiesto (repo `infra`) | `clusters/noprod/broker/broker.yaml` | ⚠ Por confirmar con Plataforma |

⚠ = propuesta siguiendo el patrón de los demás sistemas (`api-festival-qa.minc.gob.do`, `api-sgrh-qa.minc.gob.do`…). **Confirmar antes de usar.**

---

## 1. Cómo llega a cada ambiente

```
commit en main ──► azure-pipelines.yml ──► az acr build ──► ACR (broker-api:v<build>-dev)
                                                              │
                         (pendiente) bump del tag en infra ◄──┘
                                       │
                                       ▼
                         ArgoCD aplica clusters/noprod/broker ──► QA
                                       │
                     promoción del MISMO tag (⚠ proceso por confirmar)
                                       ▼
                                  Producción
```

- **Una sola imagen para todos los ambientes.** Lo que cambia entre QA y producción es el Secret y el ConfigMap, nunca la imagen. Lo que se probó en QA es exactamente lo que llega a producción.
- **Falta la etapa de despliegue del pipeline.** Hoy `azure-pipelines.yml` solo construye. Cuando exista `clusters/noprod/broker/broker.yaml` en `infra`, se agrega la etapa que actualiza el tag, igual que en SGRH y la Feria del Libro.

---

## 2. Objetos de Kubernetes

La imagen arranca distinto según la variable `ROL` (ver `docker/start.sh`):

| Objeto | Tipo | Detalle |
|---|---|---|
| `broker-api` | Deployment | `ROL=api`: php-fpm + nginx en el puerto **80**. Corre las migraciones al arrancar. **1 réplica** (ver nota). |
| `broker-worker-correo` | Deployment | `ROL=worker`: envía los correos de la cola. 1 réplica; se puede subir, el límite por minuto es compartido. |
| `broker-scheduler` | Deployment | `ROL=scheduler`: verificación horaria de la cuenta, purga nocturna y limpieza de sesiones. **Exactamente 1 réplica.** |
| `broker-api` | Service | ClusterIP, puerto 80 → `broker-api`. |
| `broker-api` | Ingress | Host del ambiente, TLS. **Tamaño máximo del cuerpo: 30 MB** (ver nota). |
| `broker-adjuntos` | PersistentVolumeClaim | **ReadWriteMany**, 5 Gi. Montado en `/var/www/html/storage/app/private` en `broker-api` y `broker-worker-correo`. |
| `broker-api-config` | ConfigMap | Sección 4. Los tres Deployments lo cargan con `envFrom`. |
| `broker-api-secrets` | Secret | Sección 3. Los tres Deployments lo cargan con `envFrom`. |

### Notas que evitan problemas

- **Un solo scheduler.** Con dos réplicas, la verificación de la cuenta y la purga corren dos veces.
- **Una réplica de la API al principio.** Las migraciones corren al arrancar el pod de la API; con varias réplicas arrancando a la vez pueden pisarse. Para escalar la API, primero despliega con 1 réplica y luego sube.
- **El volumen debe ser ReadWriteMany** (por ejemplo, Azure Files): la API guarda los adjuntos y el worker, que puede estar en otro nodo, los lee para enviarlos. Con ReadWriteOnce los correos con adjunto fallan con «Un adjunto ya no está disponible». ⚠ Confirmar la StorageClass disponible.
- **Tamaño del cuerpo en el Ingress.** El ingress-nginx acepta 1 MB por defecto; los adjuntos llegan a 20 MB. Anotación: `nginx.ingress.kubernetes.io/proxy-body-size: "30m"`. El nginx interno de la imagen ya acepta 30 MB.
- **Sondas:** liveness y readiness con `GET /health` en el puerto 80 (responde 200, o 503 si no hay base de datos). El worker y el scheduler no exponen puerto: no llevan sonda HTTP.

### Ejemplo de referencia

Esqueleto para el manifiesto de `infra`, no para aplicar tal cual:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: broker-api
  namespace: broker
spec:
  replicas: 1
  selector: { matchLabels: { app: broker-api } }
  template:
    metadata: { labels: { app: broker-api } }
    spec:
      containers:
        - name: broker-api
          image: acrmincprodca6382.azurecr.io/broker-api:v123-dev
          env:
            - { name: ROL, value: api }   # worker / scheduler en los otros dos Deployments
          envFrom:
            - configMapRef: { name: broker-api-config }
            - secretRef: { name: broker-api-secrets }
          ports: [{ containerPort: 80 }]
          readinessProbe: { httpGet: { path: /health, port: 80 }, initialDelaySeconds: 15 }
          livenessProbe: { httpGet: { path: /health, port: 80 }, initialDelaySeconds: 30 }
          volumeMounts:
            - { name: adjuntos, mountPath: /var/www/html/storage/app/private }
      volumes:
        - name: adjuntos
          persistentVolumeClaim: { claimName: broker-adjuntos }
---
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: broker-api
  namespace: broker
  annotations:
    nginx.ingress.kubernetes.io/proxy-body-size: "30m"
spec:
  rules:
    - host: broker-api-qa.minc.gob.do
      http:
        paths:
          - path: /
            pathType: Prefix
            backend: { service: { name: broker-api, port: { number: 80 } } }
```

---

## 3. Secret `broker-api-secrets`

Solo nombres. Los valores **nunca** van en este documento ni en Git: pídelos a quien indica «Origen».

| Variable | Para qué | Origen | ¿Difiere en producción? |
|---|---|---|---|
| `APP_KEY` | Clave de cifrado de Laravel | `php artisan key:generate --show` | **Sí, obligatorio.** Una por ambiente |
| `JWT_SECRET` | Firma los tokens de sesión de la SPA | `php artisan jwt:secret --show` | **Sí, obligatorio.** Una por ambiente |
| `DB_PASSWORD` | Clave del usuario de MySQL del broker | DBA | **Sí** |
| `MAIL_USERNAME` | Cuenta de Office 365 que envía (`aplicaciones@cultura.gob.do`) | Infraestructura | Probablemente no |
| `MAIL_PASSWORD` | Clave de esa cuenta | Infraestructura | Probablemente no |

- **`APP_KEY` y `JWT_SECRET` no se comparten entre ambientes.** Con el mismo `JWT_SECRET`, un token de QA abriría sesión en producción.
- **Sin `APP_KEY` el contenedor no arranca**, a propósito: generar una clave al vuelo daría una distinta en cada pod.
- **Rotar `MAIL_PASSWORD`** solo exige actualizar el Secret y reiniciar los pods (sección 7). Ningún sistema consumidor se entera: esa es la ventaja del broker.

---

## 4. ConfigMap `broker-api-config`

| Variable | QA | Producción |
|---|---|---|
| `APP_NAME` | `Broker de Servicios` | `Broker de Servicios` |
| `APP_ENV` | `production` | `production` |
| `APP_DEBUG` | `false` | `false` |
| `APP_URL` | `https://broker-api-qa.minc.gob.do` | `https://broker-api.minc.gob.do` ⚠ |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` | `es` |
| `LOG_CHANNEL` | `stderr` | `stderr` |
| `LOG_LEVEL` | `info` | `warning` |
| `DB_CONNECTION` | `mysql` | `mysql` |
| `DB_HOST` | `mysql-minc-noprod-6ff0e3.mysql.database.azure.com` | ⚠ Pedir al DBA |
| `DB_PORT` | `3306` | `3306` |
| `DB_DATABASE` | `broker_api_qa` ⚠ | `broker_api` ⚠ |
| `DB_USERNAME` | ⚠ Pedir al DBA | ⚠ Pedir al DBA |
| `SESSION_DRIVER` | `array` | `array` |
| `CACHE_STORE` | `database` | `database` |
| `QUEUE_CONNECTION` | `database` | `database` |
| `FILESYSTEM_DISK` | `local` | `local` |
| `SPA_URL` | `https://broker-qa.minc.gob.do` | `https://broker.minc.gob.do` ⚠ |
| `JWT_ACCESS_TTL` | `15` | `15` |
| `JWT_REFRESH_TTL` | `480` | `480` |
| `JWT_INACTIVIDAD_TTL` | `30` | `30` |
| `MAIL_MAILER` | `smtp` | `smtp` |
| `MAIL_HOST` | `smtp.office365.com` | `smtp.office365.com` |
| `MAIL_PORT` | `587` | `587` |
| `MAIL_FROM_ADDRESS` | `aplicaciones@cultura.gob.do` | `aplicaciones@cultura.gob.do` |
| `MAIL_FROM_NAME` | `Ministerio de Cultura` | `Ministerio de Cultura` |
| `MAIL_EHLO_DOMAIN` | `cultura.gob.do` | `cultura.gob.do` |
| `BROKER_PETICIONES_POR_MINUTO` | `120` | `120` |
| `BROKER_ENVIO_POR_MINUTO` | `30` | `30` |
| `BROKER_ADJUNTO_MAX_MB` | `20` | `20` |
| `BROKER_RETENCION_DIAS` | `90` | `90` |

### Las que más se olvidan

- **`SPA_URL`** debe ser el origen **exacto** de la SPA de ese ambiente (con `https://`, sin barra final). Es el único origen que CORS acepta: si no coincide, la SPA no puede iniciar sesión.
- **`MAIL_MAILER`** por defecto vale `log`: sin ella, el broker «envía» escribiendo en el log y marca los correos como enviados sin que salga nada.
- **`MAIL_EHLO_DOMAIN`**: sin ella el saludo SMTP se deriva de `APP_URL`; Office 365 puede rechazarlo.
- **`LOG_CHANNEL=stderr`**: con `stack` los logs se escriben dentro del contenedor y se pierden al reiniciar.

### Variables que no deben ponerse

- `MAIL_ENCRYPTION`: Laravel 12 ya no la lee.
- `MAIL_SCHEME=tls`: falla («scheme not supported»). En el puerto 587 el cifrado STARTTLS se negocia solo; déjala sin definir.
- `SANCTUM_TOKEN_PREFIX`: el prefijo `brk_` de las llaves está fijo en el código.

### MySQL de Azure y TLS

Si el servidor exige conexión cifrada (`require_secure_transport`), agrega `MYSQL_ATTR_SSL_CA` con la ruta al certificado de la CA. ⚠ Verificar con el DBA; GestionFestibal se conecta al mismo servidor de QA.

---

## 5. Variable por Deployment

No va en el ConfigMap porque cambia en cada Deployment:

| Deployment | `ROL` |
|---|---|
| `broker-api` | `api` |
| `broker-worker-correo` | `worker` |
| `broker-scheduler` | `scheduler` |

---

## 6. Base de datos

El DBA crea una base vacía y un usuario por ambiente:

```sql
CREATE DATABASE broker_api_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'broker_qa'@'%' IDENTIFIED BY '<clave para el Secret>';
GRANT ALL PRIVILEGES ON broker_api_qa.* TO 'broker_qa'@'%';
```

Las tablas **no se crean a mano**: el pod de la API ejecuta `php artisan migrate --force` en cada arranque, y solo aplica las migraciones nuevas.

---

## 7. Primer arranque en un ambiente

1. Crear la base y el usuario (sección 6), el Secret y el ConfigMap.
2. Desplegar los cuatro objetos principales (API, worker, scheduler, volumen) y el Ingress.
3. Comprobar que la API responde:
   ```bash
   curl https://broker-api-qa.minc.gob.do/health
   ```
   Debe responder `{"estado":"ok", ...}`.
4. Verificar la cuenta de correo y enviar una prueba a un correo propio:
   ```bash
   kubectl exec -n broker deploy/broker-api -- php artisan correo:verificar --enviar-prueba=tu.correo@cultura.gob.do
   ```
5. Crear los usuarios de la SPA (pide la contraseña de forma oculta, por eso `-it`):
   ```bash
   kubectl exec -it -n broker deploy/broker-api -- php artisan usuarios:crear tu.correo@cultura.gob.do --nombre="Tu Nombre"
   ```
6. Registrar cada sistema y entregar su llave al equipo responsable, que la guarda en **su** Secret como `BROKER_API_KEY`:
   ```bash
   kubectl exec -it -n broker deploy/broker-api -- php artisan sistemas:crear sgrh --nombre="Sistema de Gestión de RRHH" --responsable=correo@cultura.gob.do
   ```
7. Abrir la documentación: `https://broker-api-qa.minc.gob.do/docs/api`.

Las llaves son **por ambiente**: la de QA no sirve en producción, y cada sistema necesita una en cada ambiente.

---

## 8. Aplicar un cambio de configuración

Las variables se leen al arrancar el pod (y la imagen cachea la configuración). Editar el ConfigMap o el Secret **no basta**:

```bash
kubectl apply -f broker.yaml
kubectl rollout restart deployment/broker-api deployment/broker-worker-correo deployment/broker-scheduler -n broker
```

Reinicia los **tres**: un worker con la configuración vieja seguiría enviando con la cuenta anterior.

### Comprobar qué quedó activo

```bash
kubectl exec -n broker deploy/broker-api -- php artisan tinker --execute="echo config('mail.default'), ' ', config('mail.mailers.smtp.host');"
```

Debe decir `smtp smtp.office365.com`. Si dice `log`, la variable no llegó.

---

## 9. QA y producción: qué cambia

| | QA | Producción |
|---|---|---|
| Imagen | La del último build de `main` | La misma que se aprobó en QA |
| Secret | `APP_KEY`, `JWT_SECRET` y `DB_PASSWORD` propios | Propios, distintos de QA |
| Base de datos | Servidor `noprod` | ⚠ Servidor de producción |
| Llaves de sistemas | Las de QA | Otras, generadas en producción |
| Usuarios de la SPA | Los de QA | Otros, creados en producción |
| `LOG_LEVEL` | `info` | `warning` |
| Correo | **Sale de verdad** por Office 365 | Sale de verdad |

**En QA los correos salen de verdad.** El broker de QA usa la misma cuenta de Office 365 que producción, así que un sistema de QA conectado al broker puede escribirle a empleados reales. Conviene que en QA los sistemas usen destinatarios de prueba. Si QA debe quedar aislado, `MAIL_HOST` puede apuntar a un Mailpit dentro del clúster.

---

## 10. Pendientes antes del primer despliegue

- ⚠ Confirmar los hosts de producción, el namespace y la ruta del manifiesto de producción en `infra`.
- ⚠ Pedir al DBA las bases, los usuarios y si MySQL exige TLS.
- ⚠ Confirmar una StorageClass **ReadWriteMany** para `broker-adjuntos`.
- ⚠ Confirmar cómo se promueve un tag de QA a producción (no hay pipeline de producción en los repos).
- Agregar la etapa de despliegue a `azure-pipelines.yml` cuando exista el manifiesto de QA en `infra`.

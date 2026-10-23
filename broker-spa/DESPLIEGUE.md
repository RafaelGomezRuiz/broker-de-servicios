# Despliegue del broker-spa

Qué necesita el portal del broker para correr en QA y en producción.

| | QA | Producción |
|---|---|---|
| Imagen | `acrmincprodca6382.azurecr.io/broker-spa:<tag>` | La **misma** imagen y tag que se probó en QA |
| Host | `broker-qa.minc.gob.do` | `broker.minc.gob.do` ⚠ |
| API que usa | `https://broker-api-qa.minc.gob.do` | `https://broker-api.minc.gob.do` ⚠ |
| Namespace | `broker` ⚠ | `broker` ⚠ |
| Manifiesto (repo `infra`) | `clusters/noprod/broker/broker.yaml` (junto al broker-api) | ⚠ Por confirmar con Plataforma |

⚠ = propuesta siguiendo el patrón de los demás sistemas. **Confirmar antes de usar.**

---

## 1. Cómo llega a cada ambiente

Igual que el broker-api: un commit en `main` construye la imagen en el ACR (`azure-pipelines.yml`), y ArgoCD la despliega desde `infra`. La etapa que actualiza el tag en `infra` está pendiente (ver el `DESPLIEGUE.md` del broker-api).

**Una sola imagen para QA y producción.** A diferencia de los frontends de GestionFestibal y de la Feria del Libro, la URL del API **no se hornea** en el build: `docker/entrypoint.sh` escribe `/config.js` al arrancar con el valor del ConfigMap. Por eso:

- No hay build-args ni imagen `-prod`.
- Cambiar de API es editar el ConfigMap y reiniciar el pod, sin reconstruir.
- Lo que se aprobó en QA es exactamente lo que llega a producción.

---

## 2. Objetos de Kubernetes

| Objeto | Tipo | Detalle |
|---|---|---|
| `broker-spa` | Deployment | nginx en el puerto **8080**. 1 réplica basta (archivos estáticos); se puede subir. |
| `broker-spa` | Service | ClusterIP, puerto 80 → 8080. |
| `broker-spa` | Ingress | Host del ambiente, TLS. |
| `broker-spa-config` | ConfigMap | Sección 3. |

- **Sin Secret**: la SPA no guarda credenciales. El login va contra el broker-api.
- **Sondas:** `GET /` en el puerto 8080.

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: broker-spa
  namespace: broker
spec:
  replicas: 1
  selector: { matchLabels: { app: broker-spa } }
  template:
    metadata: { labels: { app: broker-spa } }
    spec:
      containers:
        - name: broker-spa
          image: acrmincprodca6382.azurecr.io/broker-spa:v123-dev
          envFrom:
            - configMapRef: { name: broker-spa-config }
          ports: [{ containerPort: 8080 }]
          readinessProbe: { httpGet: { path: /, port: 8080 } }
          livenessProbe: { httpGet: { path: /, port: 8080 } }
```

---

## 3. ConfigMap `broker-spa-config`

| Variable | QA | Producción |
|---|---|---|
| `API_BASE_URL` | `https://broker-api-qa.minc.gob.do` | `https://broker-api.minc.gob.do` ⚠ |

Sin barra final y sin `/admin` ni `/v1`: la SPA agrega esas rutas. Si falta, la SPA cae al valor de desarrollo (`http://localhost:8091`) y el log del pod lo avisa al arrancar.

---

## 4. Lo que tiene que coincidir con el broker-api

La sesión de la SPA usa una cookie del broker-api. Funciona solo si se cumplen estas tres condiciones; si falla una, **el login parece funcionar pero la sesión se pierde al recargar**, o no se puede entrar:

| Condición | QA | Producción |
|---|---|---|
| `SPA_URL` del broker-api = host de la SPA (exacto, con `https://`) | `https://broker-qa.minc.gob.do` | `https://broker.minc.gob.do` |
| `API_BASE_URL` de la SPA = host del broker-api | `https://broker-api-qa.minc.gob.do` | `https://broker-api.minc.gob.do` |
| SPA y API bajo el **mismo dominio** (`minc.gob.do`) | ✔ | ✔ |

La tercera condición existe porque la cookie es `SameSite=Strict`: solo viaja entre sitios del mismo dominio. Si algún día la SPA y el API quedan en dominios distintos (por ejemplo, uno en `cultura.gob.do` y otro en `minc.gob.do`), la sesión deja de funcionar.

---

## 5. Aplicar un cambio de configuración

```bash
kubectl apply -f broker.yaml
kubectl rollout restart deployment/broker-spa -n broker
```

Comprobar el valor activo:

```bash
curl https://broker-qa.minc.gob.do/config.js
```

Debe mostrar `API_BASE_URL: "https://broker-api-qa.minc.gob.do"`. Nginx sirve `config.js` sin caché, así que el navegador toma el cambio al recargar.

---

## 6. Primer arranque en un ambiente

1. El broker-api del ambiente ya está desplegado, con `SPA_URL` apuntando a este host.
2. Hay al menos un usuario creado (`usuarios:crear`, ver el `DESPLIEGUE.md` del broker-api).
3. Desplegar la SPA y abrir `https://broker-qa.minc.gob.do`: debe mostrar el login.
4. Entrar, recargar la página y comprobar que la sesión sigue abierta. Es la prueba de que la cookie y CORS están bien.
5. Abrir **Correo → Ver la referencia**: debe cargar la referencia de la API. Si queda en blanco, revisa `API_BASE_URL` y que `/docs/api.json` del broker-api responda.

---

## 7. QA y producción: qué cambia

| | QA | Producción |
|---|---|---|
| Imagen | La del último build de `main` | La misma que se aprobó en QA |
| `API_BASE_URL` | API de QA | API de producción |
| Usuarios | Los creados en el broker-api de QA | Otros, creados en producción |

## 8. Pendientes

- ⚠ Confirmar los hosts de producción y el namespace (los mismos que el broker-api).
- Probar `docker build` de la imagen.

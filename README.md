# Broker de Servicios — MINC

Plataforma que centraliza los servicios compartidos de los sistemas del Ministerio. Los sistemas (SGRH, GestionFestibal, Intranet…) dejan de tener sus propias credenciales y llaman al broker.

- **v1:** servicio de Correo.
- **Después:** Archivos (MinIO) y otros.

## Estructura

| Carpeta | Contenido |
|---|---|
| `broker-api/` | API en Laravel 12. Los sistemas la consumen con una API key. Ver su [README](broker-api/README.md). |
| `broker-spa/` | Portal en Vue 3 + Tailwind con login JWT: servicios, integraciones y documentación. Ver su [README](broker-spa/README.md). |
| `docs/` | Documentación del proyecto. |
| `azure-pipelines.yml` | Build de las imágenes en el ACR. |

## Documentos

- [Plan de trabajo](docs/plan-de-trabajo.md): alcance, variables, endpoints y checklist por fases.

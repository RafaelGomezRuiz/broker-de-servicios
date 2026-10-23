# syntax=docker/dockerfile:1.6
# ============================================================
# broker-spa — SPA de Vue servida por nginx
# ============================================================
# UNA SOLA IMAGEN para todos los ambientes, como SGRH-frontend: la URL del
# broker-api NO se hornea en el build. La lee en runtime desde el ConfigMap
# (docker/entrypoint.sh escribe /config.js al arrancar y src/config/runtime.ts
# lo consume). No agregues build-args de URL: uno que falta no rompe nada y
# por eso no se nota.

ARG BASE_REGISTRY=docker.io/library/

# ---------- Etapa 1: build ----------
FROM ${BASE_REGISTRY}node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build

# ---------- Etapa 2: runtime ----------
FROM ${BASE_REGISTRY}nginx:1.27-alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 8080 para que coincida con el containerPort del Deployment.
EXPOSE 8080

CMD ["/usr/local/bin/entrypoint.sh"]

#!/bin/sh
set -e

# ============================================================
# Traduce las variables del ConfigMap a config.js
# ============================================================
# El bundle de Vite ya está compilado; la URL del broker-api se lee en runtime
# desde window.__APP_CONFIG__ (ver src/config/runtime.ts). Cambiar de ambiente =
# editar el ConfigMap y reiniciar el pod, sin reconstruir la imagen.

CONFIG_FILE=/usr/share/nginx/html/config.js
API_BASE_URL="${API_BASE_URL:-}"

cat > "$CONFIG_FILE" <<EOF
// Generado al arrancar el contenedor. NO editar a mano: se sobrescribe en cada arranque.
window.__APP_CONFIG__ = {
  API_BASE_URL: "${API_BASE_URL}",
};
EOF

if [ -z "$API_BASE_URL" ]; then
  echo "AVISO: API_BASE_URL no está definida. La SPA usará el valor por defecto del"
  echo "       código (localhost), que no sirve en este ambiente. Defínela en el"
  echo "       ConfigMap broker-spa-config y reinicia el pod."
else
  echo "API_BASE_URL = ${API_BASE_URL}"
fi

exec nginx -g 'daemon off;'

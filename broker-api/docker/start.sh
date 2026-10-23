#!/bin/sh
set -e

cd /var/www/html

# ============================================================
# 1. Validar configuración mínima
# ============================================================
# A diferencia de SGRH, aquí NO se genera una APP_KEY si falta: una clave
# nueva en cada arranque invalidaría todo lo cifrado. Sin clave no se arranca.
if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY no está definida (debe venir del Secret broker-api-secrets)."
  exit 1
fi

# ============================================================
# 2. Cachear configuración y rutas con las variables del pod
# ============================================================
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

# ============================================================
# 3. Arrancar según el rol del Deployment
# ============================================================
case "${ROL:-api}" in
  api)
    # Las migraciones las corre solo el pod de la API, nunca el worker ni el scheduler.
    php artisan migrate --force
    echo "Iniciando broker-api (php-fpm + nginx)..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
    ;;
  worker)
    echo "Iniciando worker de correo..."
    # --max-jobs reinicia el proceso cada cierto tiempo para liberar memoria.
    exec su -s /bin/sh www-data -c "php artisan queue:work --queue=correos,default --sleep=3 --timeout=120 --max-jobs=500"
    ;;
  scheduler)
    echo "Iniciando scheduler..."
    exec su -s /bin/sh www-data -c "php artisan schedule:work"
    ;;
  *)
    echo "ERROR: ROL desconocido '${ROL}'. Valores válidos: api, worker, scheduler."
    exit 1
    ;;
esac

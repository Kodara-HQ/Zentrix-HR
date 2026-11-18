#!/bin/sh
# Container start: listen on the host's port, prepare storage, update the database, then serve.
set -e
cd /var/www/html

PORT="${PORT:-8080}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# storage/ may be a fresh persistent volume, so recreate the folders Laravel expects.
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Secret key: use APP_KEY if the host provides one, otherwise create one once and keep it on the
# persistent disk (changing it would sign everyone out and break encrypted values).
if [ -z "$APP_KEY" ]; then
  if [ ! -s storage/app/private/.app_key ]; then
    echo "base64:$(head -c 32 /dev/urandom | base64)" > storage/app/private/.app_key
    chmod 600 storage/app/private/.app_key
  fi
  APP_KEY="$(cat storage/app/private/.app_key)"
  export APP_KEY
fi
# Render's generated values are bare base64; Laravel expects the "base64:" prefix.
case "$APP_KEY" in
  base64:*) ;;
  *) export APP_KEY="base64:${APP_KEY}" ;;
esac

# Hosted MySQL (Aiven, TiDB Cloud, …) needs TLS. Use the provider's CA certificate if one was pasted,
# otherwise the system's public CA bundle. Set DB_SSL=false for a database without TLS.
remote_db=""
if [ -n "$DB_URL" ]; then remote_db=1; fi
case "${DB_HOST:-127.0.0.1}" in
  127.0.0.1|localhost|mysql|db) ;;
  *) remote_db=1 ;;
esac
if [ "${DB_SSL:-true}" != "false" ] && [ -z "$MYSQL_ATTR_SSL_CA" ] && [ -n "$remote_db" ]; then
  if [ -n "$DB_SSL_CA_PEM" ]; then
    printf '%b\n' "$DB_SSL_CA_PEM" > /etc/ssl/certs/db-ca.pem
    export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/db-ca.pem
  else
    export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
  fi
fi

# Render publishes the public address as RENDER_EXTERNAL_URL.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

chown -R www-data:www-data storage

# Wait for the database (MySQL can take a minute to start on the first deploy).
tries=0
until php artisan migrate --force; do
  tries=$((tries + 1))
  if [ "$tries" -ge 40 ]; then echo "Database not reachable; giving up." >&2; exit 1; fi
  echo "Waiting for the database ($tries)..."
  sleep 3
done
php artisan db:seed --force          # settings every install needs; safe to run on every start
# Trial sites only: SEED_DEMO=true adds the sample company (18 staff and the "test" admin, all with the
# demo password from DemoSeeder). It skips itself once employees exist. Never set this on a real system.
if [ "$SEED_DEMO" = "true" ]; then
  php artisan db:seed --class=DemoSeeder --force
fi
# First administrator from the host's settings. Created once; later starts leave it alone, so a password
# changed in the app is never reset. ADMIN_PASSWORD is a secret set in the host's dashboard, never in git.
if [ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_PASSWORD" ]; then
  php artisan hr:admin ${ADMIN_EMAIL:+"$ADMIN_EMAIL"} --username="$ADMIN_USERNAME" \
    --first="${ADMIN_FIRST_NAME:-System}" --last="${ADMIN_LAST_NAME:-Administrator}" \
    --password="$ADMIN_PASSWORD" --if-missing
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Monthly payroll and hourly reminders.
su -s /bin/sh www-data -c "php artisan schedule:work >> storage/logs/scheduler.log 2>&1" &

exec apache2-foreground

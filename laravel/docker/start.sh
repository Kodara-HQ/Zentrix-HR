#!/bin/sh
# Container start: listen on the host's port, prepare storage, update the database, then serve.
set -e
cd /var/www/html

PORT="${PORT:-8080}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# storage/ may be a fresh persistent volume, so recreate the folders Laravel expects.
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage

# Wait for the database (it can start a little after the app on first deploy).
tries=0
until php artisan migrate --force; do
  tries=$((tries + 1))
  if [ "$tries" -ge 10 ]; then echo "Database not reachable; giving up." >&2; exit 1; fi
  echo "Waiting for the database ($tries)..."
  sleep 3
done
php artisan db:seed --force          # settings every install needs; safe to run on every start
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Monthly payroll and hourly reminders.
su -s /bin/sh www-data -c "php artisan schedule:work >> storage/logs/scheduler.log 2>&1" &

exec apache2-foreground

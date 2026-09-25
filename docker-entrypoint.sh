#!/bin/sh
set -e

# Support dynamic PORT from cloud platforms like Render / Railway
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Automatically run database migrations if AUTO_MIGRATE=true
if [ "$AUTO_MIGRATE" = "true" ] || [ "$AUTO_MIGRATE" = "1" ]; then
    echo "Running database migrations..."
    php /var/www/html/sql/migrate.php || echo "Migration encountered an issue, continuing startup..."
fi

# Hand over to Apache
exec apache2-foreground

#!/bin/sh
set -e

# Support dynamic PORT from cloud platforms (Railway, Render)
PORT="${PORT:-80}"

# Configure Apache to listen on port 80, 8080, AND $PORT
cat << 'EOF' > /etc/apache2/ports.conf
Listen 80
Listen 8080
EOF

if [ -n "$PORT" ] && [ "$PORT" != "80" ] && [ "$PORT" != "8080" ]; then
    echo "Listen $PORT" >> /etc/apache2/ports.conf
fi

# Make default VirtualHost accept all ports
sed -i "s/<VirtualHost .*>/<VirtualHost *:*>/g" /etc/apache2/sites-available/000-default.conf

# Automatically run database migrations if AUTO_MIGRATE=true
if [ "$AUTO_MIGRATE" = "true" ] || [ "$AUTO_MIGRATE" = "1" ]; then
    echo "Running database migrations..."
    php /var/www/html/sql/migrate.php || echo "Migration encountered an issue, continuing startup..."
fi

echo "Starting Apache on ports: $(cat /etc/apache2/ports.conf | grep Listen | tr '\n' ' ')"

# Hand over to Apache
exec apache2-foreground

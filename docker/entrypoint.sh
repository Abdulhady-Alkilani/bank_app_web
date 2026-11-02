#!/bin/sh
# Container entrypoint: wait for MySQL, migrate (+ seed when SEED=true and the DB is empty), then exec the command.
# Only the php-fpm "app" container migrates (RUN_MIGRATIONS=true); workers wait for it to be healthy.
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.docker.example .env 2>/dev/null || cp .env.example .env
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    if ! grep -qE '^APP_KEY=.+' .env; then
        php artisan key:generate --force
    fi

    echo "Waiting for database at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
    tries=0
    until php -r '
        try {
            new PDO(
                "mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $e) { exit(1); }
    '; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "Database not reachable after 60 attempts" >&2
            exit 1
        fi
        sleep 2
    done

    php artisan migrate --force

    if [ "${SEED:-false}" = "true" ]; then
        users=$(php -r '
            $p = new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
            echo (int) $p->query("select count(*) from users")->fetchColumn();
        ')
        if [ "$users" = "0" ]; then
            php artisan db:seed --force
        else
            echo "Database already seeded, skipping."
        fi
    fi

    php artisan storage:link 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

touch /tmp/ready
exec "$@"

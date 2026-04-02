#!/usr/bin/env sh
set -eu

echo "Waiting for database ${DB_HOST:-postgres}:${DB_PORT:-5432}..."

php -r '
$host = getenv("DB_HOST") ?: "postgres";
$port = getenv("DB_PORT") ?: "5432";
$db   = getenv("DB_DATABASE") ?: "recipe_service";
$user = getenv("DB_USERNAME") ?: "postgres";
$pass = getenv("DB_PASSWORD") ?: "postgres";

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

for ($i = 0; $i < 60; $i++) {
    try {
        new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
        echo "Database is ready.\n";
        exit(0);
    } catch (Throwable $e) {
        echo "Database not ready yet, retrying...\n";
        sleep(2);
    }
}

fwrite(STDERR, "Database connection timeout.\n");
exit(1);
'

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000


<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

require __DIR__ . '/api/vendor/autoload.php';

$app = require __DIR__ . '/api/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', $app->version());
}

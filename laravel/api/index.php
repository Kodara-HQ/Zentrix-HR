<?php

/**
 * Vercel entry point (vercel-php runtime). Every request that is not a static file lands here.
 *
 * Vercel functions are read-only except /tmp, so Laravel's storage and caches live there, and the
 * non-secret settings below are filled in unless the Vercel project overrides them. Secrets (APP_KEY,
 * DB_PASSWORD, CRON_SECRET) are set in the Vercel dashboard only.
 */

$defaults = [
    'APP_NAME' => 'Zentrix HR',
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'database',
    'SESSION_SECURE_COOKIE' => 'true',
    'CACHE_STORE' => 'database',
    'QUEUE_CONNECTION' => 'sync',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'gateway01.eu-central-1.prod.aws.tidbcloud.com',
    'DB_PORT' => '4000',
    'DB_DATABASE' => 'zentrix_hr',
    'DB_USERNAME' => '3wg7TG7dWjxhpRf.root',
    // Writable locations on Vercel.
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'APP_CONFIG_CACHE' => '/tmp/bootstrap/config.php',
    'APP_EVENTS_CACHE' => '/tmp/bootstrap/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/bootstrap/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/bootstrap/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/bootstrap/services.php',
];

// TiDB Cloud requires TLS; verify it against the system's public CA bundle.
foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $bundle) {
    if (is_readable($bundle)) {
        $defaults['MYSQL_ATTR_SSL_CA'] = $bundle;
        break;
    }
}

// The public address Vercel assigns, for links in emails and PDFs.
if (getenv('VERCEL_PROJECT_PRODUCTION_URL')) {
    $defaults['APP_URL'] = 'https://'.getenv('VERCEL_PROJECT_PRODUCTION_URL');
}

foreach ($defaults as $key => $value) {
    if (getenv($key) === false || getenv($key) === '') {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

foreach (['/tmp/storage/app/private', '/tmp/storage/framework/cache/data', '/tmp/storage/framework/sessions', '/tmp/storage/framework/views', '/tmp/storage/logs', '/tmp/bootstrap'] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

require __DIR__.'/../public/index.php';

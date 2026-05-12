<?php

$tempRoot = getenv('LARAVEL_TMP_DIR');
$tempRoot = $tempRoot !== false && $tempRoot !== '' ? $tempRoot : sys_get_temp_dir();
$tempRoot = rtrim($tempRoot, DIRECTORY_SEPARATOR);
$laravelTempRoot = $tempRoot.DIRECTORY_SEPARATOR.'laravel';

$tmpDirectories = [
    $laravelTempRoot.DIRECTORY_SEPARATOR.'views',
    $laravelTempRoot.DIRECTORY_SEPARATOR.'cache',
    $laravelTempRoot.DIRECTORY_SEPARATOR.'sessions',
];

foreach ($tmpDirectories as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

$defaultEnv = [
    'APP_CONFIG_CACHE' => $laravelTempRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php',
    'APP_EVENTS_CACHE' => $laravelTempRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'events.php',
    'APP_PACKAGES_CACHE' => $laravelTempRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'packages.php',
    'APP_ROUTES_CACHE' => $laravelTempRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'routes.php',
    'APP_SERVICES_CACHE' => $laravelTempRoot.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'services.php',
    'VIEW_COMPILED_PATH' => $laravelTempRoot.DIRECTORY_SEPARATOR.'views',
    'LOG_CHANNEL' => 'stderr',
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'cookie',
];

foreach ($defaultEnv as $key => $value) {
    $existing = getenv($key);
    if ($existing === false || $existing === '') {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());


<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
|  index.php para despliegue en subcarpeta (httpdocs/gesol/), con el
|  contenido de public/ movido a esta raiz (junto a vendor/, build/, etc.).
|--------------------------------------------------------------------------
|  El servidor entrega el REQUEST_URI con el prefijo del subdirectorio
|  (/gesol/...), pero las rutas de Laravel estan definidas SIN ese prefijo
|  (login, parametros, ...). Aqui se elimina el prefijo del subdirectorio
|  del REQUEST_URI antes de que Laravel enrute, para que las rutas coincidan.
*/

// Prefijo del subdirectorio, derivado de la ubicacion de este index.php
// respecto al DOCUMENT_ROOT (p. ej. "/gesol"). Robusto ante cambios de nombre.
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$baseDir = rtrim(str_replace('\\', '/', __DIR__), '/');
$prefijo = '';
if ($docRoot !== '' && str_starts_with($baseDir, $docRoot)) {
    $prefijo = substr($baseDir, strlen($docRoot)); // "/gesol"
}

if ($prefijo !== '' && $prefijo !== '/') {
    // Quitar el prefijo del subdirectorio del REQUEST_URI (una sola vez, al inicio),
    // preservando la query string. Deja "/login", "/parametros", etc.
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    // Separar path y query
    $hashPos = strpos($uri, '?');
    $path = $hashPos === false ? $uri : substr($uri, 0, $hashPos);
    $query = $hashPos === false ? '' : substr($uri, $hashPos);

    // Colapsar cualquier repeticion consecutiva del prefijo (/gesol/gesol -> /gesol).
    while (str_starts_with($path, $prefijo . $prefijo)) {
        $path = substr($path, strlen($prefijo));
    }
    // Quitar el prefijo del subdirectorio.
    if (str_starts_with($path, $prefijo . '/')) {
        $path = substr($path, strlen($prefijo));
    } elseif ($path === $prefijo) {
        $path = '/';
    }
    if ($path === '') {
        $path = '/';
    }
    $_SERVER['REQUEST_URI'] = $path . $query;
}

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);

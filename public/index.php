<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;

// Works for BOTH layouts:
// 1) index.php + app/ in the same directory (single-dir docroot, e.g. cPanel subdomain folder)
// 2) index.php inside public/, app/ one level up (Option A/B with separate docroot)
$bootstrap = __DIR__ . '/app/bootstrap.php';
if (!file_exists($bootstrap)) {
    $bootstrap = dirname(__DIR__) . '/app/bootstrap.php';
}
require_once $bootstrap;

ErrorHandler::register();

header('X-Request-ID: ' . Request::currentRequestId());
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$app = App::instance();
$request = $app->request();

try {
    $app->bootstrap()->registerRoutes();
    $app->handle();
} catch (\Throwable $e) {
    ErrorHandler::handle($e);
}
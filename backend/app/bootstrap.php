<?php
declare(strict_types=1);

require_once __DIR__ . '/Core/Env.php';
Env::load(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Response.php';
require_once __DIR__ . '/Core/Request.php';
require_once __DIR__ . '/Core/Cors.php';
require_once __DIR__ . '/Core/Validator.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Core/Audit.php';
require_once __DIR__ . '/Core/helpers.php';
require_once __DIR__ . '/Services/InventoryService.php';
require_once __DIR__ . '/Services/RequestService.php';
require_once __DIR__ . '/Services/CatalogService.php';
require_once __DIR__ . '/Services/DashboardService.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

Cors::apply();

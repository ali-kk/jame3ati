<?php
// backend/bootstrap.php

// 1) Load .env (two levels up)
require_once __DIR__ . '/config/db.php';

// 2) Turn off display_errors, log everything instead
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// 3) Enable OPCache
ini_set('opcache.enable', '1');
ini_set('opcache.enable_cli', '1');

// 4) CORS
$allowed = array_filter(array_map('trim', explode(',', $_ENV['ALLOWED_ORIGINS'] ?? '')));
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
// short-circuit preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
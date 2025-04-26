<?php
// backend/bootstrap.php


require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load();
use Aws\S3\S3Client; use Aws\Exception\AwsException; // For S3 URLs if needed later

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
    // Inform caches that responses vary by Origin
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 86400");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");
// short-circuit preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
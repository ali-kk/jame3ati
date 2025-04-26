<?php
// backend/config/db.php

// --- Load Composer’s autoloader (project-root/vendor) ---
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

try {
    // __DIR__ already points at backend/config
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    http_response_code(500);
    error_log("FATAL: Could not load .env file: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// now your $_ENV['host'], etc.
$host     = $_ENV['host'];
$username = $_ENV['username'];
$password = $_ENV['password'];
$database = $_ENV['database'];

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Set character set to UTF-8
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

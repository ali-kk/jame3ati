<?php
// backend/security.php
// Security helper functions and headers

/**
 * Set secure headers including Content Security Policy
 */
function set_security_headers() {
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://*.amazonaws.com; connect-src 'self'");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Enable XSS protection in browsers
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent iframe embedding (clickjacking protection)
    header("X-Frame-Options: DENY");
    
    // HSTS (HTTP Strict Transport Security) - uncomment in production with HTTPS
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

/**
 * Safely escape HTML to prevent XSS
 */
function escape_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Regenerate session ID securely
 */
function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please refresh the page and try again.'
        ]);
        exit;
    }
    return true;
}
?>

<?php
// backend/logout.php

// Ensure session is started before trying to destroy it
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie 
// This will delete the session cookie, make sure it matches your session settings if modified
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set expiry in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to the login page
// Adjust the relative path if your login page is elsewhere
// This path goes up one level from /backend/ and then into /frontend/
header('Location: ../login.html');
exit; // Ensure no further code executes after redirection

?>

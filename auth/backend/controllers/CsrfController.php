<?php
/**
 * CSRF Controller
 * Returns/generates a CSRF token for frontends
 */
require_once __DIR__ . '/../config/config.php';
handleCors();

// Ensure token exists
$token = CSRF::generate();

jsonResponse([
    'success' => true,
    'token' => $token,
    'token_name' => CSRF_TOKEN_NAME
]);

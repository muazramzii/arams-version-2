<?php
// ============================================================
//  ARAMS — CSRF protection helpers
//  Usage:
//    require_once .../includes/csrf.php;
//    Inside a form, echo csrf_field()
//    On POST, reject the request if csrf_verify() is false
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Get (or create) the per-session CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside a form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Verify the token submitted via POST. Returns true if valid. */
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}
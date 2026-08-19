<?php
// ============================================================
//  ARAMS — Session & Auth Helpers
// ============================================================

// Defensively include database.php in case auth.php is included
// directly by an API file that hasn't loaded database.php yet.
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin(): void {
    // Idle session timeout (30 minutes of inactivity)
    $idleLimit = 1800;
    if (isLoggedIn() && isset($_SESSION['last_activity'])
        && (time() - (int)$_SESSION['last_activity']) > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        header('Location: /arams/index.php?timeout=1');
        exit;
    }
    if (!isLoggedIn()) {
        header('Location: /arams/index.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /arams/index.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'user_id'     => $_SESSION['user_id']     ?? null,
        'role'        => $_SESSION['role']         ?? null,
        'name'        => $_SESSION['name']         ?? '',
        'email'       => $_SESSION['email']        ?? '',
        'lecturer_id' => $_SESSION['lecturer_id']  ?? null,
        'admin_id'    => $_SESSION['admin_id']     ?? null,
        'faculty'     => $_SESSION['faculty']      ?? '',
    ];
}

function logout(): void {
    session_destroy();
    header('Location: /arams/index.php');
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function formatRM(float $amount): string {
    return 'RM ' . number_format($amount, 2);
}

function getUnreadNotifCount(int $user_id): int {
    $db  = getDB();
    $sql = "SELECT COUNT(*) FROM tbl_notification WHERE user_id = ? AND is_read = 0";
    $st  = $db->prepare($sql);
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}
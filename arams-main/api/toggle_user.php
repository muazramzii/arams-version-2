<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');
header('Content-Type: application/json');
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$userId   = (int)($body['user_id']  ?? 0);
$isActive = (int)($body['is_active'] ?? 0);
if (!$userId) jsonResponse(false, 'Missing user_id.');
$db = getDB();
$db->prepare("UPDATE tbl_user SET is_active=? WHERE user_id=?")->execute([$isActive, $userId]);
jsonResponse(true, 'User status updated.');

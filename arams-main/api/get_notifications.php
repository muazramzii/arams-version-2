<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();
$db   = getDB();
$st   = $db->prepare("SELECT * FROM tbl_notification WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$st->execute([$user['user_id']]);
$notifs = $st->fetchAll();
jsonResponse(true, 'OK', $notifs);

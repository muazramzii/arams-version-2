<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$db = getDB();
$db->prepare("UPDATE tbl_notification SET is_read=1 WHERE user_id=?")->execute([$user['user_id']]);
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/arams/index.php'));
exit;

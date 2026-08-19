<?php
// ============================================================
//  ARAMS — API: Delete KPI Task (TDPP only)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'TDPP') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$db = getDB();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$taskId = (int)($body['task_id'] ?? 0);
if (!$taskId) { echo json_encode(['success'=>false,'message'=>'Invalid task']); exit; }

// Verify this task belongs to this TDPP
$tdpp = $db->prepare("SELECT tdpp_id FROM tbl_tdpp WHERE user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdppId = (int)$tdpp->fetchColumn();

$chk = $db->prepare("SELECT tdpp_id FROM tbl_kpi_task WHERE task_id=?");
$chk->execute([$taskId]);
$owner = (int)$chk->fetchColumn();

if ($owner !== $tdppId) {
    echo json_encode(['success'=>false,'message'=>'Not your task']); exit;
}

$db->prepare("DELETE FROM tbl_kpi_task WHERE task_id=?")->execute([$taskId]);
echo json_encode(['success'=>true]);
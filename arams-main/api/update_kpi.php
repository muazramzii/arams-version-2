<?php
// ============================================================
//  ARAMS — API: Update KPI Task (TDPP only)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'TDPP') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$db = getDB();

// Resolve TDPP
$tdpp = $db->prepare("SELECT tdpp_id, faculty_id FROM tbl_tdpp WHERE user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
if (!$tdpp) { echo json_encode(['success'=>false,'message'=>'TDPP profile not found']); exit; }

$taskId = (int)($_POST['task_id'] ?? 0);
$lecId  = (int)($_POST['lecturer_id'] ?? 0);
if (!$taskId) { echo json_encode(['success'=>false,'message'=>'Invalid task']); exit; }

// Verify the task belongs to this TDPP
$chk = $db->prepare("SELECT tdpp_id FROM tbl_kpi_task WHERE task_id=?");
$chk->execute([$taskId]);
if ((int)$chk->fetchColumn() !== (int)$tdpp['tdpp_id']) {
    echo json_encode(['success'=>false,'message'=>'Not your task']); exit;
}

// Verify the chosen lecturer is in this faculty
$lc = $db->prepare("SELECT faculty_id FROM tbl_lecturer WHERE lecturer_id=?");
$lc->execute([$lecId]);
$lcFac = $lc->fetchColumn();
if ($lcFac === false || (int)$lcFac !== (int)$tdpp['faculty_id']) {
    echo json_encode(['success'=>false,'message'=>'Lecturer not in your faculty']); exit;
}

// Update the task
$stmt = $db->prepare(
    "UPDATE tbl_kpi_task SET
        lecturer_id=?, task_title=?, task_desc=?, task_type=?, target_count=?,
        criteria_quartile=?, criteria_indexing=?, criteria_grant_level=?, criteria_min_amount=?,
        deadline=?
     WHERE task_id=?"
);
$stmt->execute([
    $lecId,
    trim($_POST['task_title'] ?? ''),
    trim($_POST['task_desc'] ?? ''),
    $_POST['task_type'] ?? 'Publication',
    max(1, (int)($_POST['target_count'] ?? 1)),
    $_POST['criteria_quartile']    ?? 'Any',
    $_POST['criteria_indexing']    ?? 'Any',
    $_POST['criteria_grant_level'] ?? 'Any',
    (float)($_POST['criteria_min_amount'] ?? 0),
    $_POST['deadline'] ?? date('Y-m-d', strtotime('+30 days')),
    $taskId,
]);

echo json_encode(['success'=>true]);
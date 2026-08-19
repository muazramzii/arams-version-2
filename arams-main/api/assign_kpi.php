<?php
// ============================================================
//  ARAMS — API: Assign KPI Task (TDPP only)
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'TDPP') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$db = getDB();

// Resolve TDPP id from session
$tdpp = $db->prepare("SELECT tdpp_id, faculty_id FROM tbl_tdpp WHERE user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
if (!$tdpp) { echo json_encode(['success'=>false,'message'=>'TDPP profile not found']); exit; }

// Verify lecturer belongs to TDPP's faculty
$lecId = (int)($_POST['lecturer_id'] ?? 0);
$check = $db->prepare("SELECT faculty_id FROM tbl_lecturer WHERE lecturer_id=?");
$check->execute([$lecId]);
$lec = $check->fetch();
if (!$lec || $lec['faculty_id'] != $tdpp['faculty_id']) {
    echo json_encode(['success'=>false,'message'=>'Lecturer not in your faculty']); exit;
}

// Insert task
$stmt = $db->prepare(
    "INSERT INTO tbl_kpi_task
        (tdpp_id, lecturer_id, task_title, task_desc, task_type, target_count,
         criteria_quartile, criteria_indexing, criteria_grant_level, criteria_min_amount,
         assigned_date, deadline, status, progress_count)
     VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),?,'Pending',0)"
);
$stmt->execute([
    $tdpp['tdpp_id'],
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
]);

// Notify the lecturer
$lecUser = $db->prepare("SELECT user_id FROM tbl_lecturer WHERE lecturer_id=?");
$lecUser->execute([$lecId]);
$uid = $lecUser->fetchColumn();
if ($uid) {
    $db->prepare("INSERT INTO tbl_notification (user_id, message, is_read, created_at) VALUES (?,?,0,NOW())")
       ->execute([$uid, 'New KPI task assigned: ' . trim($_POST['task_title'] ?? '')]);
}

echo json_encode(['success'=>true]);
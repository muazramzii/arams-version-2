<?php
// ============================================================
//  ARAMS — API: Bulk Assign KPI Task (TDPP only)
//  Creates the same task for multiple lecturers at once
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

// Get the list of lecturer IDs (sent as lecturer_ids[])
$lecIds = $_POST['lecturer_ids'] ?? [];
if (!is_array($lecIds) || count($lecIds) === 0) {
    echo json_encode(['success'=>false,'message'=>'Please select at least one lecturer']); exit;
}

// Prepare reusable statements
$insert = $db->prepare(
    "INSERT INTO tbl_kpi_task
        (tdpp_id, lecturer_id, task_title, task_desc, task_type, target_count,
         criteria_quartile, criteria_indexing, criteria_grant_level, criteria_min_amount,
         assigned_date, deadline, status, progress_count)
     VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),?,'Pending',0)"
);
$notify = $db->prepare("INSERT INTO tbl_notification (user_id, message, data_id) VALUES (?,?,NULL)");
$lecUser = $db->prepare("SELECT user_id, faculty_id FROM tbl_lecturer WHERE lecturer_id=?");

$title  = trim($_POST['task_title'] ?? '');
$desc   = trim($_POST['task_desc'] ?? '');
$type   = $_POST['task_type'] ?? 'Publication';
$target = max(1, (int)($_POST['target_count'] ?? 1));
$cq     = $_POST['criteria_quartile']    ?? 'Any';
$ci     = $_POST['criteria_indexing']    ?? 'Any';
$cgl    = $_POST['criteria_grant_level'] ?? 'Any';
$cma    = (float)($_POST['criteria_min_amount'] ?? 0);
$deadline = $_POST['deadline'] ?? date('Y-m-d', strtotime('+30 days'));

$assigned = 0;
$skipped  = 0;

foreach ($lecIds as $lid) {
    $lid = (int)$lid;
    // Verify lecturer is in this TDPP's faculty
    $lecUser->execute([$lid]);
    $lec = $lecUser->fetch();
    if (!$lec || (int)$lec['faculty_id'] !== (int)$tdpp['faculty_id']) {
        $skipped++;
        continue;
    }
    // Insert the task
    $insert->execute([
        $tdpp['tdpp_id'], $lid, $title, $desc, $type, $target,
        $cq, $ci, $cgl, $cma, $deadline
    ]);
    // Notify lecturer
    if ($lec['user_id']) {
        $notify->execute([$lec['user_id'], 'New KPI task assigned: ' . $title]);
    }
    $assigned++;
}

echo json_encode([
    'success'  => true,
    'assigned' => $assigned,
    'skipped'  => $skipped,
    'message'  => "Assigned to $assigned lecturer(s)" . ($skipped ? ", skipped $skipped" : '')
]);
<?php
// ============================================================
//  ARAMS — Admin: Update a research record (full edit)
//  Updates the type-specific child row by data_id and logs
//  the change. Record keeps its current validation status.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/research_insert.php';
requireRole('Admin');

header('Content-Type: application/json');

$user   = currentUser();
$uid    = (int)$user['user_id'];
$dataId = (int)($_POST['data_id'] ?? 0);
$type   = $_POST['type'] ?? '';

if (!$dataId || !$type) jsonResponse(false, 'Missing record or type.');

$db = getDB();

// Record must exist and not be soft-deleted
$chk = $db->prepare(
    "SELECT rd.is_deleted, l.full_name
     FROM tbl_research_data rd
     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
     WHERE rd.data_id = ?"
);
$chk->execute([$dataId]);
$rec = $chk->fetch();
if (!$rec)                   jsonResponse(false, 'Record not found.');
if ((int)$rec['is_deleted']) jsonResponse(false, 'Cannot edit a deleted record.');

$db->beginTransaction();
try {
    updateResearchRecord($db, $type, $dataId, $_POST);

    $db->prepare(
        "INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
         VALUES (?,?,?,?,?)"
    )->execute([
        $uid,
        'Admin Edited ' . ucfirst($type),
        $dataId,
        ucfirst($type),
        "edited record for {$rec['full_name']} (data_id=$dataId)"
    ]);

    $db->commit();
    jsonResponse(true, 'Record updated.');

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Update failed: ' . $e->getMessage());
}
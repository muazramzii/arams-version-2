<?php
// ============================================================
//  ARAMS — Admin: Add Research Record on behalf of a lecturer
//  Creates an APPROVED parent row (admin_id set, validated now)
//  then the type-specific child row, and writes an audit entry.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/research_insert.php';
requireRole('Admin');

header('Content-Type: application/json');

$user  = currentUser();
$uid   = (int)$user['user_id'];
$lecId = (int)($_POST['lecturer_id'] ?? 0);
$type  = $_POST['type'] ?? '';

if (!$lecId || !$type) jsonResponse(false, 'Missing lecturer or record type.');

$db = getDB();

// Lecturer must exist
$chk = $db->prepare("SELECT full_name FROM tbl_lecturer WHERE lecturer_id = ?");
$chk->execute([$lecId]);
$lecName = $chk->fetchColumn();
if (!$lecName) jsonResponse(false, 'Lecturer not found.');

// Resolve admin_id (for parent row + audit)
$adm = $db->prepare("SELECT admin_id FROM tbl_admin WHERE user_id = ?");
$adm->execute([$uid]);
$adminId = (int)($adm->fetchColumn() ?: 0);

$db->beginTransaction();
try {
    // 1. Parent row — auto-approved because an admin entered it
    $db->prepare(
        "INSERT INTO tbl_research_data (submission_date, status, validated_at, lecturer_id, admin_id)
         VALUES (CURDATE(), 'Approved', NOW(), ?, ?)"
    )->execute([$lecId, $adminId ?: null]);
    $dataId = (int)$db->lastInsertId();

    // 2. Type-specific child row (shared helper)
    insertResearchRecord($db, $type, $dataId, $_POST);

    // 3. Audit trail — who, what, on whom
    $db->prepare(
        "INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
         VALUES (?,?,?,?,?)"
    )->execute([
        $uid,
        'Admin Added ' . ucfirst($type),
        $dataId,
        ucfirst($type),
        "admin_id=$adminId; added for lecturer_id=$lecId ($lecName); auto-approved"
    ]);

    $db->commit();
    jsonResponse(true, "Record added for {$lecName} and marked as validated.", ['data_id' => $dataId]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to add record: ' . $e->getMessage());
}
<?php
// ============================================================
//  ARAMS — Admin: Soft-delete a research record
//  Sets is_deleted=1 on the parent (recoverable). The record
//  then drops out of every analytics/validation view, but the
//  row and its data remain in the database. Audit-logged.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

header('Content-Type: application/json');

$user   = currentUser();
$uid    = (int)$user['user_id'];
$dataId = (int)($_POST['data_id'] ?? 0);

if (!$dataId) jsonResponse(false, 'Missing record id.');

$db = getDB();

// Identify the record (type + owner) for the audit detail
$info = $db->prepare(
    "SELECT rd.is_deleted, l.full_name,
            CASE WHEN p.publication_id IS NOT NULL THEN 'Publication'
                 WHEN g.grant_id      IS NOT NULL THEN 'Grant'
                 WHEN h.hindex_id     IS NOT NULL THEN 'HIndex'
                 WHEN ip.ip_id        IS NOT NULL THEN 'IP'
                 WHEN inc.income_id   IS NOT NULL THEN 'Income'
                 ELSE 'Record' END AS rtype
     FROM tbl_research_data rd
     JOIN tbl_lecturer l       ON l.lecturer_id = rd.lecturer_id
     LEFT JOIN tbl_publication p     ON p.data_id = rd.data_id
     LEFT JOIN tbl_grant g           ON g.data_id = rd.data_id
     LEFT JOIN tbl_hindex h          ON h.data_id = rd.data_id
     LEFT JOIN tbl_ip_record ip      ON ip.data_id = rd.data_id
     LEFT JOIN tbl_research_income inc ON inc.data_id = rd.data_id
     WHERE rd.data_id = ?"
);
$info->execute([$dataId]);
$rec = $info->fetch();

if (!$rec)                   jsonResponse(false, 'Record not found.');
if ((int)$rec['is_deleted']) jsonResponse(false, 'Record is already deleted.');

$db->beginTransaction();
try {
    $db->prepare(
        "UPDATE tbl_research_data
         SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
         WHERE data_id = ?"
    )->execute([$uid, $dataId]);

    $db->prepare(
        "INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
         VALUES (?,?,?,?,?)"
    )->execute([
        $uid,
        'Admin Deleted ' . $rec['rtype'],
        $dataId,
        $rec['rtype'],
        "soft-deleted record for {$rec['full_name']} (data_id=$dataId)"
    ]);

    $db->commit();
    jsonResponse(true, 'Record deleted (recoverable).');

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Delete failed: ' . $e->getMessage());
}

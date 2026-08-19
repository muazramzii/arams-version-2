<?php
// ============================================================
//  ARAMS — Submit Research Data API (Lecturer)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/research_insert.php';
requireRole('Lecturer');

header('Content-Type: application/json');
$user      = currentUser();
$lecId     = (int)$user['lecturer_id'];
$type      = $_POST['type'] ?? ''; // publication|grant|hindex|ip|income

if (!$lecId || !$type) jsonResponse(false, 'Missing required fields.');

$db = getDB();
$db->beginTransaction();
try {
    // 1. Insert parent Research_Data row
    $db->prepare("INSERT INTO tbl_research_data (submission_date, status, lecturer_id)
                  VALUES (CURDATE(), 'Pending', ?)")
       ->execute([$lecId]);
    $dataId = (int)$db->lastInsertId();

    // 2. Insert specific record (shared helper)
    insertResearchRecord($db, $type, $dataId, $_POST);

    // Notify admin
    $db->prepare("INSERT INTO tbl_notification (user_id, message, data_id)
                  SELECT u.user_id, CONCAT('New ', ?, ' submission pending validation from ', l.full_name), ?
                  FROM tbl_admin a JOIN tbl_user u ON u.user_id = a.user_id
                  JOIN tbl_lecturer l ON l.lecturer_id = ?")
       ->execute([ucfirst($type), $dataId, $lecId]);

    $db->commit();
    jsonResponse(true, 'Submitted successfully. Pending admin validation.', ['data_id' => $dataId]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Submission failed: ' . $e->getMessage());
}
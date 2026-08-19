<?php
// ============================================================
//  ARAMS — API: Validate Research Data (Admin + TDPP)
//  Approves/rejects submissions + triggers KPI auto-complete
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow both Admin and TDPP to validate
if (($_SESSION['role'] ?? '') !== 'TDPP') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$dataId  = (int)($body['data_id'] ?? 0);
$action  = $body['action']  ?? ''; // 'approve' or 'reject'
$remarks = trim($body['remarks'] ?? '');

if (!$dataId || !in_array($action, ['approve','reject'])) {
    jsonResponse(false, 'Invalid request parameters.');
}

$user    = currentUser();
$isTDPP  = ($user['role'] === 'TDPP');
$adminId = $isTDPP ? null : ($user['admin_id'] ?? null);
$status  = $action === 'approve' ? 'Approved' : 'Rejected';

$db = getDB();

// Get the submission + lecturer info
$st = $db->prepare(
    "SELECT rd.lecturer_id, l.user_id AS lec_user_id, l.faculty_id,
            l.full_name AS lec_name, u.email AS lec_email
     FROM tbl_research_data rd
     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
     JOIN tbl_user u ON u.user_id = l.user_id
     WHERE rd.data_id = ?"
);
$st->execute([$dataId]);
$row = $st->fetch();
if (!$row) jsonResponse(false, 'Record not found.');

// If TDPP — verify the submission is in their faculty (cast both to int)
if ($isTDPP) {
    $tdpp = $db->prepare("SELECT faculty_id FROM tbl_tdpp WHERE user_id = ?");
    $tdpp->execute([$user['user_id']]);
    $tdppFac = $tdpp->fetchColumn();
    if ((int)$tdppFac !== (int)$row['faculty_id']) {
        jsonResponse(false, 'This submission is not in your faculty.');
    }
}

// Update status
$db->prepare(
    "UPDATE tbl_research_data
     SET status = ?, remarks = ?, admin_id = ?, validated_at = NOW()
     WHERE data_id = ?"
)->execute([$status, $remarks, $adminId, $dataId]);

// Log audit (skip silently if table/columns differ)
try {
    $db->prepare(
        "INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
         VALUES (?, ?, ?, 'Research_Data', ?)"
    )->execute([$user['user_id'], ucfirst($action) . 'd Submission', $dataId,
                "data_id=$dataId status=$status" . ($remarks ? " remarks=$remarks" : '')]);
} catch (Exception $e) { /* ignore audit errors */ }

// Notify lecturer
$msg = $action === 'approve'
    ? 'Your research submission (ID: ' . $dataId . ') has been approved.'
    : 'Your research submission (ID: ' . $dataId . ') has been rejected.' . ($remarks ? ' Reason: ' . $remarks : '');
$db->prepare("INSERT INTO tbl_notification (user_id, message, data_id) VALUES (?, ?, ?)")
   ->execute([$row['lec_user_id'], $msg, $dataId]);

// Email the lecturer the result (best-effort; never block the response)
try {
    if (!empty($row['lec_email']) && filter_var($row['lec_email'], FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/../includes/mailer.php';
        // Determine submission type from its child table
        $type = 'Research';
        $typeMap = [
            'tbl_publication'     => 'Publication',
            'tbl_grant'           => 'Grant',
            'tbl_hindex'          => 'H-Index',
            'tbl_ip_record'       => 'Intellectual Property',
            'tbl_research_income' => 'Research Income',
        ];
        foreach ($typeMap as $tbl => $label) {
            $c = $db->prepare("SELECT 1 FROM {$tbl} WHERE data_id = ? LIMIT 1");
            $c->execute([$dataId]);
            if ($c->fetchColumn()) { $type = $label; break; }
        }
        aramsSendValidationResult($row['lec_email'], $row['lec_name'], $type, $status, $remarks);
    }
} catch (Exception $e) { /* ignore email errors — in-app notification already sent */ }

// ── KPI AUTO-COMPLETE — fires only on approval ──
if ($action === 'approve') {
    require_once __DIR__ . '/../includes/kpi_autocomplete.php';
    runKpiAutoComplete($db, (int)$row['lecturer_id']);
}

jsonResponse(true, 'Record ' . $status . ' successfully.');
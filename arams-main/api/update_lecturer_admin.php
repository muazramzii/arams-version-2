<?php
// ============================================================
//  ARAMS — API: Admin edits lecturer profile + minimal
//  research-record fields. Every change is audit-logged.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

// Admin only
if (($_SESSION['role'] ?? '') !== 'Admin') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$db  = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);

// Resolve admin_id (for audit detail). Falls back to user_id.
$adm = $db->prepare("SELECT admin_id FROM tbl_admin WHERE user_id=?");
$adm->execute([$uid]);
$adminId = (int)($adm->fetchColumn() ?: 0);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $body['type'] ?? '';   // 'profile' | 'publication' | 'grant' | 'ip'
$id   = (int)($body['id'] ?? 0);

// Helper: write to audit log
function audit($db, $uid, $action, $targetId, $targetType, $details) {
    $s = $db->prepare(
        "INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
         VALUES (?,?,?,?,?)"
    );
    $s->execute([$uid, $action, $targetId, $targetType, $details]);
}

try {
    if ($type === 'profile') {
        // ---- Lecturer profile fields ----
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid lecturer']); exit; }

        $rc  = trim($body['research_centre'] ?? '');
        $rgc = trim($body['research_group_category'] ?? '');
        $sr  = trim($body['status_researcher'] ?? '');
        $mp  = !empty($body['managerial_position']) ? 1 : 0;

        $st = $db->prepare(
            "UPDATE tbl_lecturer
             SET research_centre=?, research_group_category=?, status_researcher=?, managerial_position=?
             WHERE lecturer_id=?"
        );
        $st->execute([$rc ?: null, $rgc ?: null, $sr ?: null, $mp, $id]);

        audit($db, $uid, 'Edited Lecturer Profile', $id, 'Lecturer',
              "admin_id=$adminId; centre=$rc; group=$rgc; status=$sr; managerial=$mp");

        echo json_encode(['success'=>true]); exit;
    }

    if ($type === 'publication') {
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid publication']); exit; }
        $allowed = ['Journal','Proceeding / Seminar','Book Chapter','Book','Others'];
        $val = $body['pub_type'] ?? '';
        if (!in_array($val, $allowed, true)) { echo json_encode(['success'=>false,'message'=>'Invalid type']); exit; }

        $st = $db->prepare("UPDATE tbl_publication SET pub_type=? WHERE publication_id=?");
        $st->execute([$val, $id]);

        audit($db, $uid, 'Edited Publication Type', $id, 'Publication',
              "admin_id=$adminId; pub_type=$val");

        echo json_encode(['success'=>true]); exit;
    }

    if ($type === 'grant') {
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid grant']); exit; }
        $allowedCat = ['Tier 1','RE-GG','Contract','GPPS','GPP','ICI','UTHM Internal (VoT)',
                       'Geran Tanpa Dana (X)','FRGS','PRGS','TRGS','LRGS','Geran Kontrak Kementerian',
                       'Lain-Lain Geran Kebangsaan','KKP','PPRN','Sepadan RESIP','Sepadan MTUN',
                       'International','NGO','Industries','Others'];
        $funder = trim($body['funder'] ?? '');
        $level  = trim($body['grant_level'] ?? '');
        $cat    = $body['grant_category'] ?? '';
        if (!in_array($cat, $allowedCat, true)) { echo json_encode(['success'=>false,'message'=>'Invalid category']); exit; }

        $st = $db->prepare("UPDATE tbl_grant SET funder=?, grant_level=?, grant_category=? WHERE grant_id=?");
        $st->execute([$funder ?: null, $level ?: null, $cat, $id]);

        audit($db, $uid, 'Edited Grant Fields', $id, 'Grant',
              "admin_id=$adminId; funder=$funder; level=$level; category=$cat");

        echo json_encode(['success'=>true]); exit;
    }

    if ($type === 'ip') {
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid IP record']); exit; }
        $allowed = ['Patent','Copyright','Trademark','Industrial Design','Trade Secret','Others'];
        $val = $body['ip_type'] ?? '';
        if (!in_array($val, $allowed, true)) { echo json_encode(['success'=>false,'message'=>'Invalid IP type']); exit; }

        $st = $db->prepare("UPDATE tbl_ip_record SET ip_type=? WHERE ip_id=?");
        $st->execute([$val, $id]);

        audit($db, $uid, 'Edited IP Type', $id, 'IP',
              "admin_id=$adminId; ip_type=$val");

        echo json_encode(['success'=>true]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown type']);

} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
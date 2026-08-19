<?php
// ============================================================
//  ARAMS — Update Lecturer Profile (photo + password + fields)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Lecturer');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── Always fetch lecturer_id fresh from DB using user_id ──
// Never rely on $_SESSION['lecturer_id'] — it may be 0 or missing
$lecRow = $db->prepare("SELECT lecturer_id FROM tbl_lecturer WHERE user_id = ?");
$lecRow->execute([$userId]);
$lecRow = $lecRow->fetch();

if (!$lecRow) {
    header('Location: /arams/pages/lecturer/profile.php?error=notfound');
    exit;
}
$lecId = (int)$lecRow['lecturer_id'];
$_SESSION['lecturer_id'] = $lecId; // fix session for future requests

// ── Handle profile photo upload ───────────────────────────
$photoSql = '';
$photoVal = null;

if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['profile_photo'];
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize  = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed)) {
        header('Location: /arams/pages/lecturer/profile.php?error=filetype'); exit;
    }
    if ($file['size'] > $maxSize) {
        header('Location: /arams/pages/lecturer/profile.php?error=filesize'); exit;
    }

    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename  = 'profile_' . $lecId . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../assets/images/profiles/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        // Delete old photo
        $oldSt = $db->prepare("SELECT profile_photo FROM tbl_lecturer WHERE lecturer_id = ?");
        $oldSt->execute([$lecId]);
        $oldRow = $oldSt->fetch();
        if (!empty($oldRow['profile_photo'])) {
            $oldFile = $uploadDir . $oldRow['profile_photo'];
            if (file_exists($oldFile)) unlink($oldFile);
        }
        $photoSql  = ', profile_photo = ?';
        $photoVal  = $filename;
        $_SESSION['profile_photo'] = $filename;
    }
}

// ── Build params array ────────────────────────────────────
$params = [
    sanitize($_POST['full_name']               ?? ''),
    sanitize($_POST['phone']                   ?? ''),
    sanitize($_POST['department']              ?? ''),
    sanitize($_POST['specialisation']          ?? ''),
    sanitize($_POST['research_centre']         ?? ''),
    sanitize($_POST['research_group_category'] ?? ''),
    sanitize($_POST['status_researcher']       ?? ''),
    (int)($_POST['managerial_position']        ?? 0),
    sanitize($_POST['scopus_id']               ?? ''),
    sanitize($_POST['orcid_id']                ?? ''),
    sanitize($_POST['researcher_id']           ?? ''),
    sanitize($_POST['lens_id']                 ?? ''),
];
if ($photoVal !== null) $params[] = $photoVal;
$params[] = $lecId;

// ── Update tbl_lecturer ───────────────────────────────────
$db->prepare(
    "UPDATE tbl_lecturer
     SET full_name               = ?,
         phone                   = ?,
         department              = ?,
         specialisation          = ?,
         research_centre         = ?,
         research_group_category = ?,
         status_researcher       = ?,
         managerial_position     = ?,
         scopus_id               = ?,
         orcid_id                = ?,
         researcher_id           = ?,
         lens_id                 = ?
         $photoSql
     WHERE lecturer_id           = ?"
)->execute($params);

// Update session name
$_SESSION['name'] = sanitize($_POST['full_name'] ?? $_SESSION['name']);

// ── Change password ───────────────────────────────────────
$newPw  = trim($_POST['new_password']     ?? '');
$confPw = trim($_POST['confirm_password'] ?? '');

if ($newPw !== '') {
    if (strlen($newPw) < 8) {
        header('Location: /arams/pages/lecturer/profile.php?error=pwshort'); exit;
    }
    if ($newPw !== $confPw) {
        header('Location: /arams/pages/lecturer/profile.php?error=pwmismatch'); exit;
    }
    $db->prepare("UPDATE tbl_user SET password = ? WHERE user_id = ?")
       ->execute([password_hash($newPw, PASSWORD_BCRYPT), $userId]);
}

header('Location: /arams/pages/lecturer/profile.php?saved=1');
exit;
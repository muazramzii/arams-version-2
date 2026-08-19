<?php
// ============================================================
//  ARAMS — Add Lecturer / Admin API
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

header('Content-Type: application/json');

$name      = sanitize($_POST['full_name']      ?? '');
$staffNo   = sanitize($_POST['staff_no']       ?? '');
$email     = trim($_POST['email']              ?? '');
$password  = $_POST['password']                ?? '';
$confPw    = $_POST['confirm_password']        ?? '';
$role      = $_POST['role']                    ?? 'Lecturer';
$facultyId = (int)($_POST['faculty_id']        ?? 0);
$position  = sanitize($_POST['position']       ?? 'Lecturer');
$spec      = sanitize($_POST['specialisation'] ?? '');
$rgCategory = sanitize($_POST['research_group_category'] ?? '');
$rgId       = (int)($_POST['research_group_id'] ?? 0);
$rgOther    = sanitize($_POST['research_centre_other'] ?? '');
$statusRes  = sanitize($_POST['status_researcher'] ?? '');

// ── Basic validation ──────────────────────────────────────
if (!$name || !$email || !$password) {
    jsonResponse(false, 'Name, email and password are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address format.');
}
if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.');
}
if ($password !== $confPw) {
    jsonResponse(false, 'Passwords do not match. Please try again.');
}
if (!in_array($role, ['Lecturer', 'Admin'])) {
    jsonResponse(false, 'Invalid role selected.');
}

// ── Faculty only required for Lecturer ───────────────────
if ($role === 'Lecturer') {
    if (!$facultyId) {
        jsonResponse(false, 'Please select a faculty for this lecturer.');
    }
    $db = getDB();
    $facCheck = $db->prepare("SELECT faculty_id FROM tbl_faculty WHERE faculty_id = ?");
    $facCheck->execute([$facultyId]);
    if (!$facCheck->fetch()) {
        jsonResponse(false, 'Selected faculty does not exist. Please try again.');
    }
} else {
    // Admin — no faculty needed
    $db = getDB();
}

// ── Check email already exists ────────────────────────────
$exists = $db->prepare("SELECT user_id FROM tbl_user WHERE email = ?");
$exists->execute([$email]);
if ($exists->fetch()) {
    jsonResponse(false, 'This email is already registered in the system.');
}

// ── Create account ────────────────────────────────────────
$db->beginTransaction();
try {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO tbl_user (email, password, role) VALUES (?,?,?)")
       ->execute([$email, $hashed, $role]);
    $userId = (int)$db->lastInsertId();

    if ($role === 'Lecturer') {
        $sno = $staffNo ?: 'UTH-' . str_pad($userId, 5, '0', STR_PAD_LEFT);

        // Resolve research group: FG -> validated FK; External/Others -> free text
        $rgIdFinal = null;
        $rgCentre  = null;
        if ($rgCategory === 'FG') {
            if ($rgId) {
                $gchk = $db->prepare("SELECT group_id FROM tbl_research_group WHERE group_id=? AND is_active=1");
                $gchk->execute([$rgId]);
                if ($gchk->fetch()) $rgIdFinal = $rgId;
            }
        } elseif ($rgCategory === 'External' || $rgCategory === 'Others') {
            $rgCentre = $rgOther ?: null;
        }

        $db->prepare(
            "INSERT INTO tbl_lecturer
             (staff_no, full_name, position, faculty_id, specialisation,
              research_group_category, research_group_id, research_centre, status_researcher, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$sno, $name, $position, $facultyId, $spec,
                    $rgCategory ?: null, $rgIdFinal, $rgCentre, $statusRes ?: null, $userId]);
    } else {
        // Admin account
        $db->prepare("INSERT INTO tbl_admin (name, user_id) VALUES (?,?)")
           ->execute([$name, $userId]);
    }

    $db->commit();
    jsonResponse(true, "Account for {$name} created successfully. They can now login with the password you set.");

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to create account: ' . $e->getMessage());
}
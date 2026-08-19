<?php
// ============================================================
//  ARAMS — Login API
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /arams/index.php'); exit;
}
if (!csrf_verify()) {
    header('Location: /arams/index.php?error=csrf'); exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$role     = trim($_POST['role']     ?? 'Lecturer');

if (!$email || !$password) {
    header('Location: /arams/index.php?error=invalid'); exit;
}

$db  = getDB();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Rate limit: block after 5 failed attempts from this IP within 15 minutes
$rl = $db->prepare("SELECT COUNT(*) FROM tbl_login_attempt WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
$rl->execute([$ip]);
if ((int)$rl->fetchColumn() >= 5) {
    header('Location: /arams/index.php?error=locked'); exit;
}

$sql = "SELECT u.user_id, u.email, u.password, u.role, u.is_active,
               l.lecturer_id,
               l.full_name      AS lec_name,
               l.profile_photo,
               a.admin_id,
               a.name           AS adm_name,
               f.faculty_name
        FROM tbl_user u
        LEFT JOIN tbl_lecturer l ON l.user_id = u.user_id
        LEFT JOIN tbl_admin    a ON a.user_id = u.user_id
        LEFT JOIN tbl_faculty  f ON f.faculty_id = l.faculty_id
        WHERE u.email = ?";
$st = $db->prepare($sql);
$st->execute([$email]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $db->prepare("INSERT INTO tbl_login_attempt (ip_address, email) VALUES (?, ?)")->execute([$ip, $email]);
    header('Location: /arams/index.php?error=invalid'); exit;
}
if (!$user['is_active']) {
    $db->prepare("INSERT INTO tbl_login_attempt (ip_address, email) VALUES (?, ?)")->execute([$ip, $email]);
    header('Location: /arams/index.php?error=inactive'); exit;
}
if ($user['role'] !== $role) {
    $db->prepare("INSERT INTO tbl_login_attempt (ip_address, email) VALUES (?, ?)")->execute([$ip, $email]);
    header('Location: /arams/index.php?error=invalid'); exit;
}

// Success — clear this IP's failed attempts
$db->prepare("DELETE FROM tbl_login_attempt WHERE ip_address = ?")->execute([$ip]);

// Update last login
$db->prepare("UPDATE tbl_user SET last_login = NOW() WHERE user_id = ?")
   ->execute([$user['user_id']]);

// Audit trail
try {
    $db->prepare("INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
                  VALUES (?, 'Logged In', ?, 'User', ?)")
       ->execute([$user['user_id'], $user['user_id'], 'Role: ' . $user['role']]);
} catch (Exception $e) { /* ignore audit errors */ }

// Set session — including profile_photo
$_SESSION['user_id']       = $user['user_id'];
$_SESSION['role']          = $user['role'];
$_SESSION['name']          = $user['lec_name'] ?? $user['adm_name'];
$_SESSION['email']         = $user['email'];
$_SESSION['lecturer_id']   = $user['lecturer_id'];
$_SESSION['admin_id']      = $user['admin_id'];
$_SESSION['faculty']       = $user['faculty_name'] ?? '';
$_SESSION['profile_photo'] = $user['profile_photo'] ?? '';

switch ($user['role']) {
    case 'Admin':
        $redirect = '/arams/pages/admin/dashboard.php';
        break;
    case 'TDPP':
        $redirect = '/arams/pages/tdpp/dashboard.php';
        break;
    default:
        $redirect = '/arams/pages/lecturer/dashboard.php';
}

header('Location: ' . $redirect);
exit;
<?php
// ============================================================
//  ARAMS — Forgot Password (Step 3 of 3): set a new password
// ============================================================
require_once __DIR__ . '/config/database.php';
session_start();
require_once __DIR__ . '/includes/csrf.php';

if (($_SESSION['pwr_stage'] ?? '') !== 'reset'
    || empty($_SESSION['pwr_email'])
    || empty($_SESSION['pwr_reset_id'])) {
    header('Location: /arams/forgot_password.php');
    exit;
}
// verified window: 15 minutes
if (time() - (int)($_SESSION['pwr_verified_at'] ?? 0) > 900) {
    unset($_SESSION['pwr_stage'], $_SESSION['pwr_reset_id'], $_SESSION['pwr_verified_at']);
    header('Location: /arams/forgot_password.php');
    exit;
}

$email   = $_SESSION['pwr_email'];
$resetId = (int)$_SESSION['pwr_reset_id'];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['confirm'] ?? '');

    if (!csrf_verify()) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (strlen($p1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $st = $db->prepare("SELECT reset_id FROM tbl_password_reset
                            WHERE reset_id = ? AND email = ? AND used = 0 AND expires_at > NOW()");
        $st->execute([$resetId, $email]);
        if (!$st->fetch()) {
            $error = 'Your reset session has expired. Please start again.';
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $db->prepare("UPDATE tbl_user SET password = ? WHERE email = ?")->execute([$hash, $email]);
            $db->prepare("UPDATE tbl_password_reset SET used = 1 WHERE reset_id = ?")->execute([$resetId]);

            // Audit trail (sensitive action)
            try {
                $uid = $db->prepare("SELECT user_id FROM tbl_user WHERE email = ?");
                $uid->execute([$email]);
                $userId = (int)$uid->fetchColumn();
                if ($userId) {
                    $db->prepare("INSERT INTO tbl_audit_log (user_id, action, target_id, target_type, details)
                                  VALUES (?, 'Password Reset', ?, 'User', ?)")
                       ->execute([$userId, $userId, 'Self-service password reset via email OTP']);
                }
            } catch (Exception $e) { /* ignore audit errors */ }

            unset($_SESSION['pwr_email'], $_SESSION['pwr_stage'], $_SESSION['pwr_reset_id'],
                  $_SESSION['pwr_verified_at'], $_SESSION['pwr_sent_at']);
            header('Location: /arams/index.php?reset=success');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — UTHM ARAMS</title>
    <link rel="stylesheet" href="/arams/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="login-page">
    <div>
        <div class="login-card">
            <div class="login-logo" style="background:transparent;border:none;width:74px;height:74px;margin:0 auto">
                <img src="/arams/assets/images/uthm_logo.png" alt="UTHM Logo" style="width:74px;height:74px;object-fit:contain">
            </div>
            <h2 class="login-title">Set New Password</h2>
            <p class="login-sub">Choose a strong password you haven't used before</p>

            <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/arams/reset_password.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div style="position:relative">
                        <input type="password" name="password" id="pw1" class="form-control"
                               placeholder="At least 8 characters" required autofocus minlength="8">
                        <button type="button" onclick="tog('pw1', this)"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8"
                                title="Show/hide password"><i class="fas fa-eye" style="font-size:14px"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm" id="pw2" class="form-control"
                           placeholder="Re-type your new password" required minlength="8">
                </div>
                <button type="submit" class="btn btn-teal btn-full">
                    <i class="fas fa-lock"></i> Update Password
                </button>
            </form>

            <div class="login-footer" style="margin-top:1rem">
                <a href="/arams/index.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
        <p class="login-copy">© <?= date('Y') ?> Universiti Tun Hussein Onn Malaysia</p>
    </div>
</div>
<script>
function tog(id, btn){
    var f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
    btn.querySelector('i').className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
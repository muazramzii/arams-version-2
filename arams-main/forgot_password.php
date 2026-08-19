<?php
// ============================================================
//  ARAMS — Forgot Password (Step 1 of 3): request a reset code
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
session_start();
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) { header('Location: /arams/index.php'); exit; }

// false = secure (same message whether or not the email exists).
// true  = reveal "no account found" when the email is not registered.
const RESET_REVEAL_EMAIL = false;

$error = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $emailVal = htmlspecialchars($email, ENT_QUOTES);

    if (!csrf_verify()) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = getDB();
        $st = $db->prepare("SELECT user_id, is_active FROM tbl_user WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
        $exists = $u && (int)$u['is_active'] === 1;

        if ($exists) {
            $code = (string) random_int(100000, 999999);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $db->prepare("DELETE FROM tbl_password_reset WHERE email = ? AND used = 0")->execute([$email]);
            $db->prepare("INSERT INTO tbl_password_reset (email, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
               ->execute([$email, $hash]);
            $sent = aramsSendOtp($email, $code);
            if (!$sent['success']) {
                $error = 'Could not send the email right now. Please try again in a moment.';
            }
        }

        if ($error === '') {
            if (!$exists && RESET_REVEAL_EMAIL) {
                $error = 'No account found with that email address.';
            } else {
                $_SESSION['pwr_email']   = $email;
                $_SESSION['pwr_stage']   = 'verify';
                $_SESSION['pwr_sent_at'] = time();
                header('Location: /arams/verify_code.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — UTHM ARAMS</title>
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
            <h2 class="login-title">Forgot Password</h2>
            <p class="login-sub">Enter your email and we'll send you a verification code</p>

            <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/arams/forgot_password.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="your.email@uthm.edu.my"
                           required autofocus value="<?= $emailVal ?>">
                </div>
                <button type="submit" class="btn btn-teal btn-full">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>

            <div class="login-footer" style="margin-top:1rem">
                <a href="/arams/index.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
        <p class="login-copy">© <?= date('Y') ?> Universiti Tun Hussein Onn Malaysia</p>
    </div>
</div>
</body>
</html>
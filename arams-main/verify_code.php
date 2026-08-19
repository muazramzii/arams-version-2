<?php
// ============================================================
//  ARAMS — Forgot Password (Step 2 of 3): verify the code
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
session_start();
require_once __DIR__ . '/includes/csrf.php';

if (($_SESSION['pwr_stage'] ?? '') !== 'verify' || empty($_SESSION['pwr_email'])) {
    header('Location: /arams/forgot_password.php');
    exit;
}
$email  = $_SESSION['pwr_email'];
$error  = '';
$notice = '';

// ---- Resend a new code ----
if (($_GET['resend'] ?? '') === '1') {
    if (time() - (int)($_SESSION['pwr_sent_at'] ?? 0) < 30) {
        $notice = 'Please wait a few seconds before requesting another code.';
    } else {
        $db = getDB();
        $st = $db->prepare("SELECT user_id, is_active FROM tbl_user WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && (int)$u['is_active'] === 1) {
            $code = (string) random_int(100000, 999999);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $db->prepare("DELETE FROM tbl_password_reset WHERE email = ? AND used = 0")->execute([$email]);
            $db->prepare("INSERT INTO tbl_password_reset (email, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
               ->execute([$email, $hash]);
            aramsSendOtp($email, $code);
        }
        $_SESSION['pwr_sent_at'] = time();
        $notice = 'A new code has been sent (if the email is registered).';
    }
}

// ---- Verify submitted code ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if (!csrf_verify()) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Enter the 6-digit code from your email.';
    } else {
        $db = getDB();
        $st = $db->prepare("SELECT reset_id, otp_hash, attempts FROM tbl_password_reset
                            WHERE email = ? AND used = 0 AND expires_at > NOW()
                            ORDER BY reset_id DESC LIMIT 1");
        $st->execute([$email]);
        $row = $st->fetch();

        if (!$row) {
            $error = 'Your code has expired or is invalid. Please request a new one.';
        } elseif ((int)$row['attempts'] >= 5) {
            $db->prepare("UPDATE tbl_password_reset SET used = 1 WHERE reset_id = ?")->execute([$row['reset_id']]);
            $error = 'Too many incorrect attempts. Please request a new code.';
        } elseif (password_verify($code, $row['otp_hash'])) {
            $_SESSION['pwr_stage']       = 'reset';
            $_SESSION['pwr_reset_id']    = (int)$row['reset_id'];
            $_SESSION['pwr_verified_at'] = time();
            header('Location: /arams/reset_password.php');
            exit;
        } else {
            $db->prepare("UPDATE tbl_password_reset SET attempts = attempts + 1 WHERE reset_id = ?")->execute([$row['reset_id']]);
            $left = 5 - ((int)$row['attempts'] + 1);
            $error = 'Incorrect code.' . ($left > 0 ? " {$left} attempt(s) left." : ' Please request a new code.');
        }
    }
}

// masked email for display: r••••@uthm.edu.my
$maskedEmail = preg_replace_callback('/^(.).*(@.*)$/u', fn($m) => $m[1] . str_repeat('•', 4) . $m[2], $email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code — UTHM ARAMS</title>
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
            <h2 class="login-title">Enter Verification Code</h2>
            <p class="login-sub">We sent a 6-digit code to <strong><?= htmlspecialchars($maskedEmail) ?></strong></p>

            <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php elseif ($notice !== ''): ?>
            <div class="alert alert-warning"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($notice) ?></div>
            <?php endif; ?>

            <form method="POST" action="/arams/verify_code.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">6-Digit Code</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric" pattern="\d{6}"
                           maxlength="6" required autofocus autocomplete="one-time-code"
                           placeholder="------"
                           style="text-align:center;font-size:26px;letter-spacing:14px;font-weight:700">
                </div>
                <button type="submit" class="btn btn-teal btn-full">
                    <i class="fas fa-check"></i> Verify Code
                </button>
            </form>

            <div class="login-footer" style="margin-top:1rem">
                Didn't get the code? <a href="/arams/verify_code.php?resend=1">Resend</a>
            </div>
            <div class="login-footer" style="margin-top:6px">
                <a href="/arams/forgot_password.php"><i class="fas fa-arrow-left"></i> Use a different email</a>
            </div>
            <div class="login-footer" style="margin-top:6px">
                <a href="/arams/index.php"><i class="fas fa-right-to-bracket"></i> Back to Login</a>
            </div>
        </div>
        <p class="login-copy">© <?= date('Y') ?> Universiti Tun Hussein Onn Malaysia</p>
    </div>
</div>
<script>
// keep only digits in the code box
document.querySelector('input[name="code"]').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});
</script>
</body>
</html>
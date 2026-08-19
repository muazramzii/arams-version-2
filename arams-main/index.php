<?php
// ============================================================
//  ARAMS — Login Page
// ============================================================
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'Lecturer';
    $folder = match($role) {
        'Admin' => 'admin',
        'TDPP'  => 'tdpp',
        default => 'lecturer',
    };
    header('Location: /arams/pages/' . $folder . '/dashboard.php');
    exit;
}
$error = htmlspecialchars($_GET['error'] ?? '');
require_once __DIR__ . '/includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — UTHM ARAMS</title>
    <link rel="stylesheet" href="/arams/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="login-page">
    <div>
        <div class="login-card">
           <div class="login-logo" style="background:transparent;border:none;width:90px;height:90px">
    <img src="/arams/assets/images/uthm_logo.png"
         alt="UTHM Logo"
         style="width:90px;height:90px;object-fit:contain">
</div>
            <h2 class="login-title">UTHM ARAMS</h2>
            <p class="login-sub">Academic Research Analytics &amp; Monitoring System</p>
            <p style="text-align:center;font-size:11px;color:var(--muted);margin-top:-10px;margin-bottom:8px">
                Universiti Tun Hussein Onn Malaysia
            </p>

            <?php if ($error === 'invalid'): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid email or password. Please try again.</div>
            <?php elseif ($error === 'inactive'): ?>
            <div class="alert alert-warning"><i class="fas fa-user-slash"></i> This account is inactive. Please contact the administrator.</div>
            <?php elseif ($error === 'locked'): ?>
            <div class="alert alert-danger"><i class="fas fa-ban"></i> Too many failed attempts. Please try again in about 15 minutes.</div>
            <?php elseif ($error === 'csrf'): ?>
            <div class="alert alert-warning"><i class="fas fa-shield-halved"></i> Security check failed. Please try again.</div>
            <?php elseif ($error === 'unauthorized'): ?>
            <div class="alert alert-warning"><i class="fas fa-lock"></i> You are not authorised to access that page.</div>
            <?php endif; ?>

            <?php if (($_GET['timeout'] ?? '') === '1'): ?>
            <div class="alert alert-warning"><i class="fas fa-clock"></i> Your session expired due to inactivity. Please log in again.</div>
            <?php endif; ?>

            <?php if (($_GET['reset'] ?? '') === 'success'): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Password updated. You can now log in with your new password.</div>
            <?php endif; ?>

            <form method="POST" action="/arams/api/login.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           placeholder="your.email@uthm.edu.my" required autofocus
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div style="position:relative">
                        <input type="password" name="password" id="passwordInput"
                               class="form-control" placeholder="••••••••" required
                               style="padding-right:42px">
                        <button type="button" id="togglePw"
                                onclick="togglePassword()"
                                style="position:absolute;right:0;top:0;height:100%;width:40px;
                                       background:none;border:none;cursor:pointer;
                                       color:#94a3b8;display:flex;align-items:center;
                                       justify-content:center;border-radius:0 8px 8px 0;
                                       transition:.15s"
                                onmouseenter="this.style.color='#0B3C5D'"
                                onmouseleave="this.style.color='#94a3b8'"
                                title="Show/hide password">
                            <i class="fas fa-eye" id="togglePwIcon" style="font-size:14px"></i>
                        </button>
                    </div>
                </div>

                <div class="form-label" style="margin-bottom:10px">Login as:</div>
                <div class="role-grid" style="grid-template-columns:1fr 1fr 1fr">
                    <button type="button" class="role-card active-lec" id="r-lec"
                            onclick="setRole('Lecturer')">
                        <i class="fas fa-graduation-cap"></i> Lecturer
                    </button>
                    <button type="button" class="role-card" id="r-adm"
                            onclick="setRole('Admin')">
                        <i class="fas fa-shield-alt"></i> Admin
                    </button>
                    <button type="button" class="role-card" id="r-tdpp"
                            onclick="setRole('TDPP')">
                        <i class="fas fa-user-tie"></i> TDPP
                    </button>
                </div>
                <input type="hidden" name="role" id="roleInput" value="Lecturer">
                <p class="form-hint" id="roleHint" style="margin-bottom:1rem;text-align:center">
                    Access your research profile and submit publications
                </p>

                <button type="submit" class="btn btn-teal btn-full">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="login-footer" style="margin-top:1rem">
                <a href="/arams/forgot_password.php">Forgot password?</a>
            </div>
            <div class="login-footer" style="margin-top:6px;font-size:11px;opacity:.5">
                test je dulu— Admin: admin.tncpi@uthm.edu.my / password<br>
                Lecturer: rozlini@uthm.edu.my / password<br>
                TDPP: tdpp@uthm.edu.my / password
            </div>
        </div>
        <p class="login-copy">© <?= date('Y') ?> Universiti Tun Hussein Onn Malaysia</p>
    </div>
</div>

<script>
function setRole(role) {
    document.getElementById('roleInput').value = role;
    const hints = {
        Lecturer: 'Access your research profile and submit publications',
        Admin:    'Manage system data and validate submissions',
        TDPP:     'Monitor faculty research performance and assign KPI tasks'
    };
    document.getElementById('roleHint').textContent = hints[role];
    document.getElementById('r-lec').className  = 'role-card' + (role === 'Lecturer' ? ' active-lec' : '');
    document.getElementById('r-adm').className  = 'role-card' + (role === 'Admin'    ? ' active-adm' : '');
    document.getElementById('r-tdpp').className = 'role-card' + (role === 'TDPP'     ? ' active-adm' : '');
}

function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('togglePwIcon');
    const btn   = document.getElementById('togglePw');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.style.color = '#0B3C5D';
        btn.title = 'Hide password';
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.style.color = '#94a3b8';
        btn.title = 'Show password';
    }
    input.focus();
}
</script>
</body>
</html>
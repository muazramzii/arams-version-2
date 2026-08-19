<?php
$pageTitle  = 'Faculty Members';
$activePage = 'users';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/avatar.php';
$db = getDB();

$tdpp = $db->prepare("SELECT t.*, f.faculty_code, f.faculty_name FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id WHERE t.user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

$users = $db->prepare(
    "SELECT u.user_id, u.email, u.role, u.is_active, u.last_login, l.full_name, l.staff_no, l.profile_photo
     FROM tbl_user u
     JOIN tbl_lecturer l ON l.user_id=u.user_id
     WHERE l.faculty_id=?
     ORDER BY l.full_name"
);
$users->execute([$facId]);
$users = $users->fetchAll();
?>
<div style="margin-bottom:1rem">
    <h2 style="margin:0;font-size:20px">Faculty Members — <?= htmlspecialchars($tdpp['faculty_code']) ?></h2>
    <p style="margin:4px 0 0;color:var(--muted);font-size:13px">Lecturer accounts in your faculty</p>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>Name</th><th>Email</th><th>Staff No</th><th>Role</th><th>Status</th><th>Last Login</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-weight:600">
                    <div style="display:flex;align-items:center;gap:10px">
                        <?= lecAvatar($u['profile_photo'] ?? '', $u['full_name']) ?>
                        <span><?= htmlspecialchars($u['full_name']) ?></span>
                    </div>
                </td>
                <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($u['staff_no']) ?></td>
                <td><span class="badge badge-blue"><?= htmlspecialchars($u['role']) ?></span></td>
                <td><span class="badge <?= $u['is_active']?'badge-green':'badge-grey' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                <td style="font-size:12px;color:var(--muted)"><?= $u['last_login'] ?: 'Never' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No users in your faculty.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
$pageTitle  = 'Validation';
$activePage = 'validation';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

$tdpp = $db->prepare("SELECT t.*, f.faculty_code FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id WHERE t.user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

// Use the same view as admin, filtered to this faculty.
// vw_pending_validation already exposes: data_id, record_type, record_title,
// lecturer_name, faculty_code, submission_date  (+ likely faculty_id)
try {
    $pending = $db->prepare(
        "SELECT * FROM vw_pending_validation WHERE faculty_code = ? ORDER BY submission_date DESC"
    );
    $pending->execute([$tdpp['faculty_code']]);
    $pending = $pending->fetchAll();
} catch (Exception $e) {
    $pending = [];
}
?>
<div style="margin-bottom:1rem">
    <h2 style="margin:0;font-size:20px">Validation Queue — <?= htmlspecialchars($tdpp['faculty_code']) ?></h2>
    <p style="margin:4px 0 0;color:var(--muted);font-size:13px">
        Approve or reject research submissions from your faculty. Approving may
        auto-complete the lecturer's matching KPI tasks.
    </p>
</div>
<div class="card">
    <div class="card-title"><i class="fas fa-clipboard-check" style="color:var(--amber)"></i> Pending Submissions (<?= count($pending) ?>)</div>
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>Lecturer</th><th>Type</th><th>Title / Details</th><th>Submitted</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
            <tr id="vrow-<?= $p['data_id'] ?>">
                <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['lecturer_name']) ?></td>
                <td><span class="badge badge-blue"><?= htmlspecialchars($p['record_type']) ?></span></td>
                <td style="font-size:13px;max-width:300px"><?= htmlspecialchars(substr($p['record_title'] ?? '—', 0, 80)) ?></td>
                <td style="font-size:12px;color:var(--muted)"><?= $p['submission_date'] ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-success btn-sm" onclick="approveRecord(<?= $p['data_id'] ?>, '/arams/api/validate.php', document.getElementById('vrow-<?= $p['data_id'] ?>'))">Approve</button>
                        <button class="btn btn-danger btn-sm" onclick="rejectRecord(<?= $p['data_id'] ?>, '/arams/api/validate.php', document.getElementById('vrow-<?= $p['data_id'] ?>'))">Reject</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pending)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">
                <i class="fas fa-check-circle" style="color:var(--green);font-size:24px;display:block;margin-bottom:8px"></i>
                No pending submissions in your faculty.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
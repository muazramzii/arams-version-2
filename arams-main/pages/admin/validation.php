<?php
$pageTitle  = 'Data Validation Queue';
$activePage = 'validation';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM vw_pending_validation";
$params = [];
if ($filter === 'pub')    { $sql .= " WHERE record_type = 'Publication'"; }
if ($filter === 'grant')  { $sql .= " WHERE record_type = 'Grant'"; }
if ($filter === 'hindex') { $sql .= " WHERE record_type = 'H-Index'"; }
$st = $db->prepare($sql); $st->execute($params);
$queue = $st->fetchAll();

// Recent audit log
$logs = $db->query(
    "SELECT al.*, u.email FROM tbl_audit_log al
     JOIN tbl_user u ON u.user_id = al.user_id
     ORDER BY al.logged_at DESC LIMIT 10"
)->fetchAll();
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>Validation Queue</h1>
        <p>Review and validate research data submitted by lecturers</p>
    </div>
    <span class="badge badge-yellow" style="font-size:14px;padding:8px 16px"><?= count($queue) ?> pending</span>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search submissions…" oninput="filterTable(this,'valTable')">
    <select class="filter-select" onchange="location.href='?filter='+this.value">
        <option value="all" <?= $filter==='all'?'selected':'' ?>>All Types</option>
        <option value="pub" <?= $filter==='pub'?'selected':'' ?>>Publications</option>
        <option value="grant" <?= $filter==='grant'?'selected':'' ?>>Grants</option>
        <option value="hindex" <?= $filter==='hindex'?'selected':'' ?>>H-Index</option>
    </select>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="table-wrap">
        <table class="arams-table" id="valTable">
            <thead><tr>
                <th>Lecturer</th><th>Faculty</th><th>Type</th>
                <th>Title / Details</th><th>Submitted</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($queue as $q): ?>
            <tr id="vrow-<?= $q['data_id'] ?>">
                <td style="font-weight:600"><?= htmlspecialchars($q['lecturer_name']) ?>
                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($q['staff_no']) ?></div>
                </td>
                <td><span class="badge badge-grey"><?= htmlspecialchars($q['faculty_code']) ?></span></td>
                <td>
                    <?php
                    $typeColor = ['Publication'=>'badge-blue','Grant'=>'badge-purple',
                                  'H-Index'=>'badge-teal','IP Record'=>'badge-orange','Research Income'=>'badge-green'];
                    ?>
                    <span class="badge <?= $typeColor[$q['record_type']] ?? 'badge-grey' ?>"><?= htmlspecialchars($q['record_type']) ?></span>
                </td>
                <td style="max-width:240px">
                    <div style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                         title="<?= htmlspecialchars($q['record_title']??'') ?>">
                        <?= htmlspecialchars(substr($q['record_title']??'',0,60)) ?><?= strlen($q['record_title']??'')>60?'…':'' ?>
                    </div>
                </td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= $q['submission_date'] ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-success btn-sm"
                                onclick="approveRecord(<?= $q['data_id'] ?>, '/arams/api/validate.php', document.getElementById('vrow-<?= $q['data_id'] ?>'))">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="rejectRecord(<?= $q['data_id'] ?>, '/arams/api/validate.php', document.getElementById('vrow-<?= $q['data_id'] ?>'))">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($queue)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:3rem">
                <i class="fas fa-check-circle" style="color:var(--green);font-size:36px;display:block;margin-bottom:10px"></i>
                All clear! No pending submissions to validate.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Audit Log -->
<div class="card">
    <div class="card-title"><i class="fas fa-history" style="color:var(--blue)"></i> Recent Audit Log</div>
    <div class="timeline">
        <?php foreach ($logs as $log):
            $col = str_contains($log['action'],'Approved') ? '#22c55e' : (str_contains($log['action'],'Rejected') ? '#ef4444' : '#3b82f6');
        ?>
        <div class="tl-item">
            <div class="tl-dot" style="background:<?= $col ?>"></div>
            <div class="tl-line"></div>
            <div class="tl-body">
                <div class="tl-title"><?= htmlspecialchars($log['action']) ?></div>
                <div class="tl-meta"><?= htmlspecialchars($log['details'] ?? '') ?> — <?= htmlspecialchars($log['email']) ?></div>
                <div class="tl-meta"><?= $log['logged_at'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <p style="color:var(--muted);font-size:13px">No audit log entries yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

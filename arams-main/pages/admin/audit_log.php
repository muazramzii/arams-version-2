<?php
// ============================================================
//  ARAMS — Audit Log Viewer (Admin only)
// ============================================================

// ── Guard FIRST, before any HTML output ──
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if (($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: /arams/index.php?error=unauthorized'); exit;
}

$pageTitle  = 'Audit Log';
$activePage = 'audit';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Pull logs + resolve the actor's name from whichever profile table matches
$logs = $db->query(
    "SELECT al.log_id, al.user_id, al.action, al.target_id, al.target_type,
            al.details, al.logged_at,
            COALESCE(l.full_name, a.name, t.full_name, u.email) AS actor_name,
            u.role AS actor_role
     FROM tbl_audit_log al
     LEFT JOIN tbl_user     u ON u.user_id = al.user_id
     LEFT JOIN tbl_lecturer l ON l.user_id = al.user_id
     LEFT JOIN tbl_admin    a ON a.user_id = al.user_id
     LEFT JOIN tbl_tdpp     t ON t.user_id = al.user_id
     ORDER BY al.logged_at DESC
     LIMIT 500"
)->fetchAll();

// Distinct target types for the filter dropdown
$types = array_values(array_unique(array_filter(array_column($logs, 'target_type'))));
sort($types);
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>Audit Log</h1>
        <p>System activity trail — who changed what, and when</p>
    </div>
    <span class="badge badge-grey" style="font-size:12px">
        <i class="fas fa-shield-alt" style="margin-right:4px"></i>
        <?= count($logs) ?> recent entries
    </span>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search action, name, or details…"
           oninput="filterTable(this,'auditTable')">
    <select class="filter-select" onchange="filterBySelect(this,'auditTable',3)">
        <option value="">All Types</option>
        <?php foreach ($types as $tp): ?>
        <option><?= htmlspecialchars($tp) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="arams-table" id="auditTable">
            <thead><tr>
                <th>When</th><th>Who</th><th>Action</th><th>Type</th>
                <th>Record</th><th>Details</th>
            </tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">
                No audit entries yet.
            </td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log):
                $roleBadge = $log['actor_role']==='Admin' ? 'badge-blue'
                           : ($log['actor_role']==='TDPP' ? 'badge-purple' : 'badge-teal');
            ?>
            <tr>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap">
                    <?= date('d M Y', strtotime($log['logged_at'])) ?><br>
                    <span style="font-size:11px"><?= date('g:i A', strtotime($log['logged_at'])) ?></span>
                </td>
                <td style="font-size:13px">
                    <div style="font-weight:600"><?= htmlspecialchars($log['actor_name'] ?? '—') ?></div>
                    <?php if ($log['actor_role']): ?>
                    <span class="badge <?= $roleBadge ?>" style="font-size:10px"><?= htmlspecialchars($log['actor_role']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;font-weight:500"><?= htmlspecialchars($log['action']) ?></td>
                <td>
                    <?php if ($log['target_type']): ?>
                    <span class="badge badge-grey" style="font-size:11px"><?= htmlspecialchars($log['target_type']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--muted)">
                    <?= $log['target_id'] ? '#'.htmlspecialchars($log['target_id']) : '—' ?>
                </td>
                <td style="font-size:12px;color:var(--muted);max-width:320px">
                    <?= $log['details'] ? htmlspecialchars($log['details']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p style="font-size:11px;color:var(--muted);margin-top:.75rem;text-align:center">
    Showing the most recent 500 entries. Older records remain stored in the database.
</p>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
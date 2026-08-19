<?php
// ============================================================
//  ARAMS — Lecturer "My KPI Tasks"
//  Shows tasks assigned by TDPP + auto-tracked progress
// ============================================================
$pageTitle  = 'My KPI Tasks';
$activePage = 'tasks';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Resolve lecturer_id
$lec = $db->prepare("SELECT lecturer_id, full_name FROM tbl_lecturer WHERE user_id=?");
$lec->execute([$_SESSION['user_id']]);
$lec = $lec->fetch();

// Guard: this page is for lecturers only
if (!$lec) {
    echo '<div class="card" style="text-align:center;padding:3rem;color:var(--muted)">';
    echo '<i class="fas fa-info-circle" style="font-size:40px;opacity:.3;display:block;margin-bottom:12px"></i>';
    echo '<p>This page is only available for lecturer accounts.</p>';
    echo '</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
$lecId = $lec['lecturer_id'];

// Refresh overdue
$db->prepare(
    "UPDATE tbl_kpi_task SET status='Overdue'
     WHERE lecturer_id=? AND status IN ('Pending','In Progress') AND deadline < CURDATE()"
)->execute([$lecId]);

// All tasks for this lecturer
$tasks = $db->prepare(
    "SELECT kt.*, tp.full_name AS assigned_by
     FROM tbl_kpi_task kt
     JOIN tbl_tdpp tp ON tp.tdpp_id = kt.tdpp_id
     WHERE kt.lecturer_id=?
     ORDER BY FIELD(kt.status,'Overdue','In Progress','Pending','Completed (Late)','Completed'), kt.deadline ASC"
);
$tasks->execute([$lecId]);
$tasks = $tasks->fetchAll();

$done = count(array_filter($tasks, fn($t) => str_starts_with($t['status'],'Completed')));
$total = count($tasks);
$rate  = $total > 0 ? round($done / $total * 100) : 0;
?>

<div style="margin-bottom:1rem">
    <h2 style="margin:0;font-size:20px">My KPI Tasks</h2>
    <p style="margin:4px 0 0;color:var(--muted);font-size:13px">
        Research targets assigned by your TDPP. Tasks complete <strong>automatically</strong>
        when your matching research is approved — no manual action needed.
    </p>
</div>

<!-- Summary cards -->
<div class="kpi-grid" style="margin-bottom:1rem">
    <div class="kpi-card bg-blue">
        <i class="fas fa-tasks"></i>
        <div class="kpi-val"><?= $total ?></div>
        <div class="kpi-label">Total KPI Tasks</div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-check-circle"></i>
        <div class="kpi-val"><?= $done ?></div>
        <div class="kpi-label">Completed</div>
        <div class="kpi-chg"><?= $rate ?>% completion rate</div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-spinner"></i>
        <div class="kpi-val"><?= count(array_filter($tasks, fn($t)=>in_array($t['status'],['Pending','In Progress']))) ?></div>
        <div class="kpi-label">In Progress</div>
    </div>
    <div class="kpi-card bg-green" style="background:linear-gradient(135deg,#ef4444,#dc2626)">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="kpi-val"><?= count(array_filter($tasks, fn($t)=>$t['status']==='Overdue')) ?></div>
        <div class="kpi-label">Overdue</div>
    </div>
</div>

<!-- Task cards -->
<?php foreach ($tasks as $t):
    $badge = match($t['status']) {
        'Completed'        => 'badge-green',
        'Completed (Late)' => 'badge-yellow',
        'Overdue'          => 'badge-red',
        'In Progress'      => 'badge-blue',
        default            => 'badge-grey'
    };
    $pct = $t['target_count'] > 0 ? min(100, round($t['progress_count'] / $t['target_count'] * 100)) : 0;
    $crit = [];
    if ($t['criteria_quartile']    !== 'Any') $crit[] = $t['criteria_quartile'];
    if ($t['criteria_indexing']    !== 'Any') $crit[] = $t['criteria_indexing'];
    if ($t['criteria_grant_level'] !== 'Any') $crit[] = $t['criteria_grant_level'];
    if ($t['criteria_min_amount'] > 0)        $crit[] = '≥RM' . number_format($t['criteria_min_amount']);
?>
<div class="card" style="margin-bottom:.75rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($t['task_type']) ?></span>
                <span class="badge <?= $badge ?>" style="font-size:10px"><?= htmlspecialchars($t['status']) ?></span>
            </div>
            <div style="font-weight:600;font-size:15px"><?= htmlspecialchars($t['task_title']) ?></div>
            <?php if ($t['task_desc']): ?>
            <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= htmlspecialchars($t['task_desc']) ?></div>
            <?php endif; ?>
            <div style="font-size:11px;color:var(--muted);margin-top:6px">
                Assigned by <?= htmlspecialchars($t['assigned_by']) ?>
                <?php if ($crit): ?> · Criteria: <?= htmlspecialchars(implode(', ', $crit)) ?><?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;min-width:120px">
            <div style="font-size:12px;color:var(--muted)">Deadline</div>
            <div style="font-weight:600;font-size:14px"><?= $t['deadline'] ?></div>
        </div>
    </div>
    <!-- Progress bar -->
    <div style="margin-top:.75rem">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:4px">
            <span>Progress (auto-tracked)</span>
            <span><?= (int)$t['progress_count'] ?> / <?= (int)$t['target_count'] ?></span>
        </div>
        <div style="background:var(--grey);height:8px;border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;
                background:<?= $pct>=100 ? '#22c55e' : 'linear-gradient(90deg,var(--blue),var(--teal))' ?>;
                transition:width .3s"></div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($tasks)): ?>
<div class="card" style="text-align:center;padding:3rem;color:var(--muted)">
    <i class="fas fa-clipboard-check" style="font-size:40px;opacity:.3;display:block;margin-bottom:12px"></i>
    <p>No KPI tasks assigned to you yet.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
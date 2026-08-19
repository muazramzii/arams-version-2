<?php
// ============================================================
//  ARAMS — TDPP Dashboard (Timbalan Dekan P&P)
//  Faculty-scoped research performance + KPI monitoring
// ============================================================
$pageTitle  = 'TDPP Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Get this TDPP's faculty
$tdpp = $db->prepare(
    "SELECT t.*, f.faculty_code, f.faculty_name
     FROM tbl_tdpp t
     JOIN tbl_faculty f ON f.faculty_id = t.faculty_id
     WHERE t.user_id = ?"
);
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

// Faculty-scoped KPIs
$totals = $db->prepare(
    "SELECT
        (SELECT COUNT(*) FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id=rd.data_id
         JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=?) AS total_pubs,
        (SELECT COUNT(*) FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id=rd.data_id
         JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=?) AS total_grants,
        (SELECT COUNT(*) FROM tbl_lecturer WHERE faculty_id=?) AS total_lecturers,
        (SELECT COUNT(*) FROM tbl_kpi_task kt
         JOIN tbl_tdpp t ON t.tdpp_id=kt.tdpp_id WHERE t.faculty_id=?) AS total_tasks"
);
$totals->execute([$facId, $facId, $facId, $facId]);
$totals = $totals->fetch();

// KPI Task status breakdown
$taskStats = $db->prepare(
    "SELECT kt.status, COUNT(*) AS cnt
     FROM tbl_kpi_task kt
     JOIN tbl_tdpp t ON t.tdpp_id = kt.tdpp_id
     WHERE t.faculty_id = ?
     GROUP BY kt.status"
);
$taskStats->execute([$facId]);
$taskMap = array_column($taskStats->fetchAll(), 'cnt', 'status');

$completed = (int)($taskMap['Completed'] ?? 0) + (int)($taskMap['Completed (Late)'] ?? 0);
$pendingT  = (int)($taskMap['Pending'] ?? 0) + (int)($taskMap['In Progress'] ?? 0);
$overdueT  = (int)($taskMap['Overdue'] ?? 0);
$totalT    = $completed + $pendingT + $overdueT;
$completionRate = $totalT > 0 ? round($completed / $totalT * 100) : 0;

// Recent tasks
$recentTasks = $db->prepare(
    "SELECT kt.*, l.full_name AS lecturer_name
     FROM tbl_kpi_task kt
     JOIN tbl_tdpp t ON t.tdpp_id = kt.tdpp_id
     JOIN tbl_lecturer l ON l.lecturer_id = kt.lecturer_id
     WHERE t.faculty_id = ?
     ORDER BY kt.created_at DESC LIMIT 8"
);
$recentTasks->execute([$facId]);
$recentTasks = $recentTasks->fetchAll();

// Top lecturers in faculty
$topLect = $db->prepare(
    "SELECT l.full_name, l.staff_no,
            (SELECT COUNT(*) FROM tbl_publication p
             JOIN tbl_research_data rd ON p.data_id=rd.data_id
             WHERE rd.lecturer_id=l.lecturer_id AND rd.status='Approved' AND rd.is_deleted=0) AS pubs,
            (SELECT COUNT(*) FROM tbl_kpi_task kt
             WHERE kt.lecturer_id=l.lecturer_id AND kt.status IN ('Completed','Completed (Late)')) AS done_tasks
     FROM tbl_lecturer l
     WHERE l.faculty_id = ?
     ORDER BY pubs DESC LIMIT 5"
);
$topLect->execute([$facId]);
$topLect = $topLect->fetchAll();
?>

<!-- Faculty banner -->
<div style="background:linear-gradient(135deg,var(--blue),var(--teal));
            color:#fff;padding:1.25rem 1.5rem;border-radius:12px;margin-bottom:1rem">
    <div style="font-size:13px;opacity:.85;text-transform:uppercase;letter-spacing:1px">
        Timbalan Dekan Penyelidikan & Penerbitan
    </div>
    <div style="font-size:22px;font-weight:700;margin-top:4px">
        <?= htmlspecialchars($tdpp['faculty_name']) ?>
    </div>
    <div style="font-size:13px;opacity:.9;margin-top:2px">
        Research Performance & KPI Monitoring — <?= htmlspecialchars($tdpp['faculty_code']) ?>
    </div>
</div>
<?php if ($overdueT > 0): ?>
<!-- Overdue Alert Banner -->
<div style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #dc2626;
            border-radius:10px;padding:.9rem 1.25rem;margin-bottom:1rem;
            display:flex;align-items:center;gap:12px">
    <div style="background:#dc2626;color:#fff;width:38px;height:38px;border-radius:50%;
                display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <div style="flex:1">
        <div style="font-weight:700;color:#991b1b;font-size:14px">
            <?= $overdueT ?> KPI task<?= $overdueT > 1 ? 's are' : ' is' ?> overdue
        </div>
        <div style="font-size:12px;color:#b91c1c">
            <?= $overdueT > 1 ? 'These tasks have' : 'This task has' ?> passed the deadline without completion. Review and follow up with the lecturer<?= $overdueT > 1 ? 's' : '' ?>.
        </div>
    </div>
    <a href="/arams/pages/tdpp/kpi.php" class="btn btn-sm"
       style="background:#dc2626;color:#fff;border:none;white-space:nowrap">
        <i class="fas fa-arrow-right"></i> Review Tasks
    </a>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= number_format((int)$totals['total_pubs']) ?></div>
        <div class="kpi-label">Faculty Publications</div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= number_format((int)$totals['total_grants']) ?></div>
        <div class="kpi-label">Faculty Grants</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-users"></i>
        <div class="kpi-val"><?= (int)$totals['total_lecturers'] ?></div>
        <div class="kpi-label">Lecturers Monitored</div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-tasks"></i>
        <div class="kpi-val"><?= (int)$totals['total_tasks'] ?></div>
        <div class="kpi-label">KPI Tasks Assigned</div>
        <div class="kpi-chg"><?= $completionRate ?>% completed</div>
    </div>
</div>

<div class="grid-2-1" style="margin-bottom:1rem">
    <!-- Recent KPI Tasks -->
    <div class="card">
        <div class="card-title" style="justify-content:space-between">
            <span><i class="fas fa-tasks" style="color:var(--teal)"></i> Recent KPI Tasks</span>
            <a href="/arams/pages/tdpp/kpi.php" class="btn btn-teal btn-sm">Manage KPIs</a>
        </div>
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>Lecturer</th><th>Task</th><th>Type</th><th>Deadline</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentTasks as $t):
                    $badge = match($t['status']) {
                        'Completed'        => 'badge-green',
                        'Completed (Late)' => 'badge-yellow',
                        'Overdue'          => 'badge-red',
                        'In Progress'      => 'badge-blue',
                        default            => 'badge-grey'
                    };
                ?>
                <tr>
                    <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['lecturer_name']) ?></td>
                    <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= htmlspecialchars($t['task_title']) ?>">
                        <?= htmlspecialchars($t['task_title']) ?>
                    </td>
                    <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($t['task_type']) ?></span></td>
                    <td style="font-size:12px;color:var(--muted)"><?= $t['deadline'] ?></td>
                    <td><span class="badge <?= $badge ?>" style="font-size:10px"><?= htmlspecialchars($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentTasks)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">
                    No KPI tasks yet. <a href="/arams/pages/tdpp/kpi.php" style="color:var(--teal)">Create one →</a>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- KPI Completion Status -->
    <div class="card" style="display:flex;flex-direction:column">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--teal)"></i> KPI Completion</div>
        <div id="kpiDonut" style="display:flex;justify-content:center;margin:.5rem 0"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding:0 .5rem;margin-top:.75rem">
            <div style="text-align:center;padding:.6rem;background:rgba(34,197,94,.08);border-radius:8px;border:1px solid rgba(34,197,94,.2)">
                <div style="font-size:18px;font-weight:700;color:#16a34a"><?= $completed ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase">Completed</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:rgba(251,146,60,.08);border-radius:8px;border:1px solid rgba(251,146,60,.2)">
                <div style="font-size:18px;font-weight:700;color:#ea580c"><?= $pendingT ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase">Pending</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:rgba(239,68,68,.08);border-radius:8px;border:1px solid rgba(239,68,68,.2)">
                <div style="font-size:18px;font-weight:700;color:#dc2626"><?= $overdueT ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase">Overdue</div>
            </div>
        </div>
        <div style="margin-top:.75rem;padding:.75rem 1rem;background:linear-gradient(90deg,rgba(27,153,139,.12),rgba(11,60,93,.06));border-radius:8px;display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;font-weight:600">Completion Rate</span>
            <span style="font-size:18px;font-weight:700;color:var(--teal)"><?= $completionRate ?>%</span>
        </div>
    </div>
</div>

<!-- Top Lecturers in Faculty -->
<div class="card">
    <div class="card-title" style="justify-content:space-between">
        <span><i class="fas fa-medal" style="color:#f59e0b"></i> Faculty Lecturer Performance</span>
        <a href="/arams/pages/tdpp/lecturers.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>#</th><th>Lecturer</th><th>Staff No</th><th>Publications</th><th>KPI Tasks Done</th></tr></thead>
            <tbody>
            <?php foreach ($topLect as $i => $l): ?>
            <tr>
                <td style="font-weight:700;color:var(--muted)"><?= $i+1 ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?></td>
                <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['staff_no']) ?></td>
                <td><?= (int)$l['pubs'] ?></td>
                <td><span class="badge badge-green"><?= (int)$l['done_tasks'] ?> done</span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topLect)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No lecturers in this faculty yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if (typeof renderDonut === 'function') {
        renderDonut('kpiDonut', [
            { label:'Completed', value:<?= $completed ?>, color:'#22c55e' },
            { label:'Pending',   value:<?= $pendingT ?>, color:'#f59e0b' },
            { label:'Overdue',   value:<?= $overdueT ?>, color:'#ef4444' }
        ]);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
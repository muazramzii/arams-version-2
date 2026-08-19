<?php
$pageTitle  = 'My Faculty Lecturers';
$activePage = 'lecturers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/avatar.php';
$db = getDB();

$tdpp = $db->prepare("SELECT t.*, f.faculty_code, f.faculty_name FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id WHERE t.user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

$lecturers = $db->prepare(
    "SELECT l.*, u.email,
        (SELECT COUNT(*) FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id=rd.data_id WHERE rd.lecturer_id=l.lecturer_id AND rd.status='Approved' AND rd.is_deleted=0) AS pubs,
        (SELECT COUNT(*) FROM tbl_grant g JOIN tbl_research_data rd ON g.data_id=rd.data_id WHERE rd.lecturer_id=l.lecturer_id AND rd.status='Approved' AND rd.is_deleted=0) AS grants,
        (SELECT COUNT(*) FROM tbl_kpi_task kt WHERE kt.lecturer_id=l.lecturer_id) AS tasks,
        (SELECT COUNT(*) FROM tbl_kpi_task kt WHERE kt.lecturer_id=l.lecturer_id AND kt.status IN ('Completed','Completed (Late)')) AS done
     FROM tbl_lecturer l
     JOIN tbl_user u ON u.user_id=l.user_id
     WHERE l.faculty_id=?
     ORDER BY l.full_name"
);
$lecturers->execute([$facId]);
$lecturers = $lecturers->fetchAll();
?>
<div style="margin-bottom:1rem">
    <h2 style="margin:0;font-size:20px"><?= htmlspecialchars($tdpp['faculty_name']) ?> — Lecturers</h2>
    <p style="margin:4px 0 0;color:var(--muted);font-size:13px"><?= count($lecturers) ?> lecturers under your monitoring · click a name to view their analytics</p>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>Name</th><th>Position</th><th>Email</th><th>Pubs</th><th>Grants</th><th>KPI Progress</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lecturers as $l):
                $rate = $l['tasks'] > 0 ? round($l['done']/$l['tasks']*100) : 0;
                $analyticsUrl = '/arams/pages/tdpp/lecturer_analytics.php?lecturer_id=' . (int)$l['lecturer_id'];
            ?>
            <tr class="lec-row" style="cursor:pointer"
                onclick="window.location='<?= $analyticsUrl ?>'"
                title="View <?= htmlspecialchars($l['full_name']) ?>'s analytics">
                <td style="font-weight:600">
                    <div style="display:flex;align-items:center;gap:10px">
                        <?= lecAvatar($l['profile_photo'] ?? '', $l['full_name']) ?>
                        <div>
                            <a href="<?= $analyticsUrl ?>" style="color:var(--blue);text-decoration:none" onclick="event.stopPropagation()">
                                <?= htmlspecialchars($l['full_name']) ?>
                            </a>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($l['staff_no']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="font-size:12px"><?= htmlspecialchars($l['position'] ?? '—') ?>
                    <?= $l['grade'] ? '('.htmlspecialchars($l['grade']).')' : '' ?></td>
                <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['email']) ?></td>
                <td><?= (int)$l['pubs'] ?></td>
                <td><?= (int)$l['grants'] ?></td>
                <td>
                    <span class="badge <?= $rate>=70?'badge-green':($rate>=40?'badge-yellow':'badge-grey') ?>">
                        <?= (int)$l['done'] ?>/<?= (int)$l['tasks'] ?> (<?= $rate ?>%)
                    </span>
                </td>
                <td style="text-align:right;color:var(--muted)"><i class="fas fa-chart-line"></i></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lecturers)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No lecturers in your faculty.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
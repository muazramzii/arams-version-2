<?php
// ============================================================
//  ARAMS — Lecturer Dashboard
// ============================================================
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)$user['lecturer_id'];

// KPI summary
$kpi = $db->prepare("SELECT * FROM vw_lecturer_kpi WHERE lecturer_id = ?");
$kpi->execute([$lecId]);
$k = $kpi->fetch() ?: [];

// Recent activity (last 5 submissions)
$act = $db->prepare(
    "SELECT rd.data_id, rd.submission_date, rd.status,
            COALESCE(p.title, g.grant_title, CONCAT('H-Index ',h.record_year), ip.ip_title, inc.source) AS title,
            CASE WHEN p.publication_id IS NOT NULL THEN 'Publication'
                 WHEN g.grant_id IS NOT NULL THEN 'Grant'
                 WHEN h.hindex_id IS NOT NULL THEN 'H-Index'
                 WHEN ip.ip_id IS NOT NULL THEN 'IP Record'
                 WHEN inc.income_id IS NOT NULL THEN 'Income'
                 ELSE 'Record' END AS record_type
     FROM tbl_research_data rd
     LEFT JOIN tbl_publication     p   ON p.data_id   = rd.data_id
     LEFT JOIN tbl_grant           g   ON g.data_id   = rd.data_id
     LEFT JOIN tbl_hindex          h   ON h.data_id   = rd.data_id
     LEFT JOIN tbl_ip_record       ip  ON ip.data_id  = rd.data_id
     LEFT JOIN tbl_research_income inc ON inc.data_id = rd.data_id
     WHERE rd.lecturer_id = ?
     ORDER BY rd.submission_date DESC LIMIT 5"
);
$act->execute([$lecId]);
$activities = $act->fetchAll();

// Publications by year (last 6 years)
$pubYear = $db->prepare(
    "SELECT p.pub_year, COUNT(*) AS cnt
     FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
       AND p.pub_year >= YEAR(NOW()) - 5
     GROUP BY p.pub_year ORDER BY p.pub_year"
);
$pubYear->execute([$lecId]);
$pubYearData = $pubYear->fetchAll();

$badgeMap = [
    'Approved' => 'badge-green',
    'Pending'  => 'badge-yellow',
    'Rejected' => 'badge-red',
];
$iconMap = [
    'Publication' => 'fas fa-file-alt',
    'Grant'       => 'fas fa-trophy',
    'H-Index'     => 'fas fa-chart-line',
    'IP Record'   => 'fas fa-lightbulb',
    'Income'      => 'fas fa-dollar-sign',
];
?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= (int)($k['total_publications'] ?? 0) ?></div>
        <div class="kpi-label">My Publications</div>
        <div class="kpi-chg">Q1: <?= (int)($k['q1_pubs'] ?? 0) ?> &nbsp;|&nbsp; Q2: <?= (int)($k['q2_pubs'] ?? 0) ?></div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= (int)($k['total_grants'] ?? 0) ?></div>
        <div class="kpi-label">My Grants</div>
        <div class="kpi-chg"><?= (int)($k['grants_as_pi'] ?? 0) ?> as PI</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= (int)($k['current_hindex'] ?? 0) ?></div>
        <div class="kpi-label">My H-Index</div>
        <div class="kpi-chg">Citations: <?= number_format((int)($k['total_citations'] ?? 0)) ?></div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-dollar-sign"></i>
        <div class="kpi-val">RM <?= number_format((float)($k['total_income_rm'] ?? 0) / 1000, 0) ?>K</div>
        <div class="kpi-label">Research Income</div>
        <div class="kpi-chg">IP Records: <?= (int)($k['total_ip'] ?? 0) ?></div>
    </div>
</div>

<div class="grid-2">
    <!-- Publications by Year Chart -->
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--blue)"></i> Publications by Year</div>
        <div class="bar-chart" id="pubYearChart">
            <?php
            $maxPub = max(array_column($pubYearData, 'cnt') ?: [1]);
            foreach ($pubYearData as $row):
                $pct = round(($row['cnt'] / $maxPub) * 100);
            ?>
            <div class="bar-col">
                <div class="bar-val"><?= $row['cnt'] ?></div>
                <div class="bar" style="height:<?= $pct ?>%;background:var(--blue)"></div>
                <div class="bar-label"><?= $row['pub_year'] ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pubYearData)): ?>
            <p style="color:var(--muted);font-size:13px;margin:auto">No approved publications yet.</p>
            <?php endif; ?>
        </div>
        <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:6px">Count per Year</div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
        <div class="card-title"><i class="fas fa-info-circle" style="color:var(--teal)"></i> Research Summary</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php
            $stats = [
                ['label'=>'Scopus Publications',   'val'=> $k['total_publications'] ?? 0, 'pct'=> min(100, ($k['total_publications']??0)*5)],
                ['label'=>'Active Grants',          'val'=> $k['total_grants']       ?? 0, 'pct'=> min(100, ($k['total_grants']??0)*10)],
                ['label'=>'H-Index (Scopus)',        'val'=> $k['current_hindex']    ?? 0, 'pct'=> min(100, ($k['current_hindex']??0)*5)],
                ['label'=>'IP Records',             'val'=> $k['total_ip']           ?? 0, 'pct'=> min(100, ($k['total_ip']??0)*20)],
            ];
            foreach ($stats as $s):
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= $s['label'] ?></span>
                    <strong><?= $s['val'] ?></strong>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $s['pct'] ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card" style="margin-top:1rem">
    <div class="card-title" style="justify-content:space-between">
        <span><i class="fas fa-clock" style="color:var(--teal)"></i> Recent Activity</span>
        <a href="/arams/pages/lecturer/research.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <?php if (empty($activities)): ?>
    <p style="color:var(--muted);font-size:13px;text-align:center;padding:1rem">No submissions yet. Start by adding a publication or grant.</p>
    <?php else: ?>
    <?php foreach ($activities as $a): ?>
    <div class="activity-item">
        <div class="activity-icon"><i class="<?= $iconMap[$a['record_type']] ?? 'fas fa-file' ?>"></i></div>
        <div class="activity-text">
            <div><?= htmlspecialchars($a['record_type']) ?> — <?= htmlspecialchars(substr($a['title'] ?? '', 0, 70)) ?><?= strlen($a['title'] ?? '') > 70 ? '…' : '' ?></div>
            <div class="activity-time"><?= htmlspecialchars($a['submission_date']) ?></div>
        </div>
        <span class="badge <?= $badgeMap[$a['status']] ?? 'badge-grey' ?>"><?= $a['status'] ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

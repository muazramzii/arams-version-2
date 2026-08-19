<?php
// ============================================================
//  ARAMS — Lecturer Research Timeline
// ============================================================
$pageTitle  = 'Research Timeline';
$activePage = 'timeline';
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)$user['lecturer_id'];

// Get all approved records grouped by year
$pubs = $db->prepare(
    "SELECT p.pub_year AS year, p.title, p.pub_type, p.quartile, p.indexing_type, 'publication' AS rtype
     FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY p.pub_year DESC"
);
$pubs->execute([$lecId]);

$grants = $db->prepare(
    "SELECT YEAR(g.start_date) AS year, g.grant_title AS title, g.role, g.amount,
            g.grant_category, g.status AS grant_status, 'grant' AS rtype
     FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY g.start_date DESC"
);
$grants->execute([$lecId]);

$hindexes = $db->prepare(
    "SELECT h.record_year AS year, h.hindex_value, h.citation_count, h.source, 'hindex' AS rtype
     FROM tbl_hindex h
     JOIN tbl_research_data rd ON h.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY h.record_year DESC"
);
$hindexes->execute([$lecId]);

// Merge and group by year
$events = [];
foreach ($pubs->fetchAll() as $p) {
    $events[$p['year']][] = array_merge($p, ['rtype' => 'publication']);
}
foreach ($grants->fetchAll() as $g) {
    $yr = $g['year'] ?? date('Y');
    $events[$yr][] = array_merge($g, ['rtype' => 'grant']);
}
foreach ($hindexes->fetchAll() as $h) {
    $events[$h['year']][] = array_merge($h, ['rtype' => 'hindex']);
}
krsort($events);

// Summary KPIs
$kpi = $db->prepare("SELECT * FROM vw_lecturer_kpi WHERE lecturer_id = ?");
$kpi->execute([$lecId]);
$k = $kpi->fetch() ?: [];
?>

<!-- Summary Cards -->
<div class="kpi-grid" style="margin-bottom:1.5rem">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= (int)($k['total_publications'] ?? 0) ?></div>
        <div class="kpi-label">Total Publications</div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= (int)($k['total_grants'] ?? 0) ?></div>
        <div class="kpi-label">Total Grants</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= (int)($k['current_hindex'] ?? 0) ?></div>
        <div class="kpi-label">Current H-Index</div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-dollar-sign"></i>
        <div class="kpi-val">RM <?= number_format((float)($k['total_income_rm']??0)/1000,0) ?>K</div>
        <div class="kpi-label">Total Income</div>
    </div>
</div>

<?php if (empty($events)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    No approved records yet. Submit publications or grants to see your research timeline.
</div>
<?php endif; ?>

<?php foreach ($events as $year => $yearEvents): ?>
<div class="card" style="margin-bottom:1rem">
    <!-- Year Header -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">
        <div style="width:50px;height:50px;border-radius:var(--radius-sm);background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;text-align:center;line-height:1.2">
            <?= $year ?>
        </div>
        <div>
            <h3 style="margin:0;font-size:18px"><?= $year ?></h3>
            <p style="margin:0;font-size:12px;color:var(--muted)"><?= count($yearEvents) ?> event<?= count($yearEvents)>1?'s':'' ?></p>
        </div>
    </div>

    <!-- Events -->
    <div class="timeline" style="padding-left:10px">
        <?php
        $typeColors = [
            'publication' => '#3b82f6',
            'grant'       => '#8b5cf6',
            'hindex'      => '#14b8a6',
        ];
        $typeIcons = [
            'publication' => 'fas fa-file-alt',
            'grant'       => 'fas fa-trophy',
            'hindex'      => 'fas fa-chart-line',
        ];
        foreach ($yearEvents as $ev):
            $col = $typeColors[$ev['rtype']] ?? '#64748b';
        ?>
        <div class="tl-item">
            <div class="tl-dot" style="background:<?= $col ?>"></div>
            <div class="tl-line"></div>
            <div class="tl-body">
                <?php if ($ev['rtype'] === 'publication'): ?>
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <div>
                            <div class="tl-title"><i class="fas fa-file-alt" style="color:<?= $col ?>;margin-right:5px"></i><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="tl-meta">
                                <?= htmlspecialchars($ev['pub_type']) ?>
                                <?= $ev['quartile'] !== 'N/A' ? ' • <strong>' . $ev['quartile'] . '</strong>' : '' ?>
                                <?= $ev['indexing_type'] ? ' • ' . htmlspecialchars($ev['indexing_type']) : '' ?>
                            </div>
                        </div>
                        <span class="badge badge-blue"><?= $ev['pub_type'] ?></span>
                    </div>
                <?php elseif ($ev['rtype'] === 'grant'): ?>
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <div>
                            <div class="tl-title"><i class="fas fa-trophy" style="color:<?= $col ?>;margin-right:5px"></i><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="tl-meta">
                                <?= htmlspecialchars($ev['grant_category'] ?? '') ?>
                                <?php if ($ev['role']): ?> • Role: <strong><?= htmlspecialchars($ev['role']) ?></strong><?php endif; ?>
                            </div>
                            <?php if ($ev['amount']): ?>
                            <div class="tl-amount">RM <?= number_format((float)$ev['amount'],2) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= $ev['grant_status']==='Active'?'badge-green':'badge-grey' ?>"><?= $ev['grant_status'] ?></span>
                    </div>
                <?php elseif ($ev['rtype'] === 'hindex'): ?>
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <div>
                            <div class="tl-title"><i class="fas fa-chart-line" style="color:<?= $col ?>;margin-right:5px"></i>H-Index Updated to <strong><?= $ev['hindex_value'] ?></strong></div>
                            <div class="tl-meta">
                                Source: <?= htmlspecialchars($ev['source']) ?>
                                <?= $ev['citation_count'] ? ' • Citations: ' . number_format($ev['citation_count']) : '' ?>
                            </div>
                        </div>
                        <span class="badge badge-teal">Verified</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

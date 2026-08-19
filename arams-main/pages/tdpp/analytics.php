<?php
// ============================================================
//  ARAMS — TDPP Faculty Analytics (mirrors Admin analytics)
//  Faculty-scoped: same charts as admin but for own faculty
// ============================================================
$pageTitle  = 'Faculty Analytics';
$activePage = 'analytics';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Resolve TDPP faculty
$tdpp = $db->prepare("SELECT t.*, f.faculty_code, f.faculty_name FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id WHERE t.user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp  = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

// ── KPI totals (faculty-scoped via vw_lecturer_kpi join) ──
$kpiRow = $db->prepare(
    "SELECT SUM(k.total_publications) AS pubs, SUM(k.total_grants) AS grants,
            AVG(k.current_hindex) AS hindex, SUM(k.total_citations) AS citations
     FROM vw_lecturer_kpi k
     JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
     WHERE l.faculty_id = ?"
);
$kpiRow->execute([$facId]); $kpiRow = $kpiRow->fetch();

$pubTrend = $db->prepare(
    "SELECT p.pub_year AS yr, COUNT(*) AS cnt
     FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=? AND p.pub_year >= YEAR(NOW())-5
     GROUP BY p.pub_year ORDER BY p.pub_year"
);
$pubTrend->execute([$facId]); $pubTrend = $pubTrend->fetchAll();

$quartileDist = $db->prepare(
    "SELECT quartile, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=? GROUP BY quartile"
);
$quartileDist->execute([$facId]); $quartileDist = $quartileDist->fetchAll();

$pubTypes = $db->prepare(
    "SELECT pub_type, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=? GROUP BY pub_type ORDER BY cnt DESC"
);
$pubTypes->execute([$facId]); $pubTypes = $pubTypes->fetchAll();

$grantCats = $db->prepare(
    "SELECT grant_category, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=? GROUP BY grant_category ORDER BY cnt DESC"
);
$grantCats->execute([$facId]); $grantCats = $grantCats->fetchAll();

$grantRoles = $db->prepare(
    "SELECT role, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=? GROUP BY role ORDER BY cnt DESC"
);
$grantRoles->execute([$facId]); $grantRoles = $grantRoles->fetchAll();

$grantStatus = $db->prepare(
    "SELECT
        SUM(CASE WHEN g.end_date IS NULL OR g.end_date >= CURDATE() THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN g.end_date IS NOT NULL AND g.end_date < CURDATE() THEN 1 ELSE 0 END) AS nonactive
     FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id=rd.data_id
     JOIN tbl_lecturer l ON l.lecturer_id=rd.lecturer_id
     WHERE rd.status='Approved' AND rd.is_deleted=0 AND l.faculty_id=?"
);
$grantStatus->execute([$facId]); $grantStatus = $grantStatus->fetch();

// ── KPI task completion by lecturer (TDPP-specific) ──────
$kpiByLec = $db->prepare(
    "SELECT l.full_name,
        COUNT(kt.task_id) AS total,
        SUM(CASE WHEN kt.status IN ('Completed','Completed (Late)') THEN 1 ELSE 0 END) AS done
     FROM tbl_lecturer l
     LEFT JOIN tbl_kpi_task kt ON kt.lecturer_id=l.lecturer_id
     WHERE l.faculty_id=?
     GROUP BY l.lecturer_id HAVING total > 0
     ORDER BY done DESC"
);
$kpiByLec->execute([$facId]); $kpiByLec = $kpiByLec->fetchAll();

// ── Pre-calculate percentages ────────────────────────────
$qMap    = array_column($quartileDist, 'cnt', 'quartile');
$pubMax  = max(array_column($pubTrend, 'cnt') ?: [1]);
$typeMax = max(array_column($pubTypes, 'cnt') ?: [1]);

$barPcts = [];
foreach ($pubTrend as $r) $barPcts[] = round(($r['cnt'] / $pubMax) * 100);
$typePcts = [];
foreach ($pubTypes as $r) $typePcts[] = round(($r['cnt'] / $typeMax) * 100);

$pubTypeColors  = ['#2563eb','#8b5cf6','#14b8a6','#60a5fa','#a78bfa','#5eead4','#94a3b8'];
$grantCatColors = ['#2563eb','#8b5cf6','#14b8a6','#60a5fa','#a78bfa','#5eead4','#94a3b8'];
$grantRoleColors= ['#2563eb','#8b5cf6','#14b8a6'];

// ── H-Index (faculty-scoped) ──
$hTop = $db->prepare(
    "SELECT l.full_name, k.current_hindex AS h
     FROM vw_lecturer_kpi k JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
     WHERE l.faculty_id = ? AND k.current_hindex > 0 ORDER BY k.current_hindex DESC LIMIT 6"
);
$hTop->execute([$facId]); $hTop = $hTop->fetchAll();

$hScatter = $db->prepare(
    "SELECT k.current_hindex AS h, k.total_citations AS c
     FROM vw_lecturer_kpi k JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
     WHERE l.faculty_id = ? AND (k.current_hindex > 0 OR k.total_citations > 0)"
);
$hScatter->execute([$facId]); $hScatter = $hScatter->fetchAll();

// ── Research groups (faculty-scoped) ──
$rgCat = $db->prepare("SELECT research_group_category AS cat, COUNT(*) AS cnt FROM tbl_lecturer WHERE faculty_id = ? GROUP BY research_group_category");
$rgCat->execute([$facId]); $rgCat = $rgCat->fetchAll();

$rgSizes = $db->prepare(
    "SELECT g.group_name AS name, COUNT(l.lecturer_id) AS cnt
     FROM tbl_research_group g JOIN tbl_lecturer l ON l.research_group_id = g.group_id
     WHERE l.faculty_id = ? GROUP BY g.group_id HAVING cnt > 0 ORDER BY cnt DESC LIMIT 10"
);
$rgSizes->execute([$facId]); $rgSizes = $rgSizes->fetchAll();

$rgMembers = $db->prepare(
    "SELECT l.full_name AS name, l.staff_no, l.grade, l.research_group_category AS cat,
            COALESCE(g.group_name, NULLIF(l.research_centre,'')) AS grp
     FROM tbl_lecturer l LEFT JOIN tbl_research_group g ON g.group_id = l.research_group_id
     WHERE l.faculty_id = ? ORDER BY l.full_name"
);
$rgMembers->execute([$facId]); $rgMembers = $rgMembers->fetchAll();

// ── Derived: category map + sizes + member name maps ──
$rgCatMap = ['FG' => 0, 'CoR' => 0, 'External' => 0, 'Not set' => 0];
foreach ($rgCat as $r) { $k = trim((string)($r['cat'] ?? '')); if ($k === '') $k = 'Not set'; if (!isset($rgCatMap[$k])) $rgCatMap[$k] = 0; $rgCatMap[$k] += (int)$r['cnt']; }
$rgSizeMax = $rgSizes ? max(array_column($rgSizes, 'cnt')) : 1;
$gaugeMax  = max(20, (int)ceil(($hTop[0]['h'] ?? 0) / 5) * 5);
$grpMembers = []; $catMembers = [];
foreach ($rgMembers as $m) {
    $rec = ['name' => $m['name'], 'staff_no' => $m['staff_no'], 'grade' => $m['grade'], 'cat' => $m['cat']];
    if (!empty($m['grp'])) $grpMembers[$m['grp']][] = $rec;
    $c = trim((string)($m['cat'] ?? '')); if ($c === '') $c = 'Not set';
    $catMembers[$c][] = $rec;
}

// ── SVG helpers (server-rendered, print-safe) ──
function svgScatter(array $pts, float $avgH): string {
    $W=440; $H=280; $pl=50; $pr=14; $ptp=14; $pb=42;
    $plotW=$W-$pl-$pr; $plotH=$H-$ptp-$pb; $maxH=1; $maxC=1;
    foreach ($pts as $p){ $maxH=max($maxH,(float)$p['h']); $maxC=max($maxC,(float)$p['c']); }
    $maxH=ceil(max($maxH,$avgH)/5)*5; if($maxH<5)$maxH=5; $maxC=ceil($maxC/200)*200; if($maxC<1)$maxC=1;
    $X=function($h)use($pl,$plotW,$maxH){ return $pl + ($h/$maxH)*$plotW; };
    $Y=function($c)use($ptp,$plotH,$maxC){ return $ptp + $plotH - ($c/$maxC)*$plotH; };
    $s='<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" style="max-height:300px" xmlns="http://www.w3.org/2000/svg" font-family="inherit">';
    for($i=0;$i<=4;$i++){ $cy=$ptp+$plotH-($i/4)*$plotH; $val=round($maxC*$i/4);
        $s.='<line x1="'.$pl.'" y1="'.$cy.'" x2="'.($W-$pr).'" y2="'.$cy.'" stroke="#eef2f7" stroke-width="1"/>';
        $s.='<text x="'.($pl-6).'" y="'.($cy+3).'" font-size="9" fill="#94a3b8" text-anchor="end">'.number_format($val).'</text>'; }
    for($i=0;$i<=5;$i++){ $cx=$pl+($i/5)*$plotW; $val=round($maxH*$i/5);
        $s.='<text x="'.$cx.'" y="'.($H-$pb+16).'" font-size="9" fill="#94a3b8" text-anchor="middle">'.$val.'</text>'; }
    $s.='<text x="'.($pl+$plotW/2).'" y="'.($H-5).'" font-size="10" fill="#64748b" text-anchor="middle">H-Index</text>';
    $s.='<text x="13" y="'.($ptp+$plotH/2).'" font-size="10" fill="#64748b" text-anchor="middle" transform="rotate(-90 13 '.($ptp+$plotH/2).')">Citations</text>';
    $ax=$X($avgH);
    $s.='<line x1="'.round($ax,1).'" y1="'.$ptp.'" x2="'.round($ax,1).'" y2="'.($ptp+$plotH).'" stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="4 3"/>';
    $s.='<text x="'.round($ax+3,1).'" y="'.($ptp+10).'" font-size="8" fill="#d97706">avg '.number_format($avgH,1).'</text>';
    foreach ($pts as $p){ $cx=$X((float)$p['h']); $cy=$Y((float)$p['c']);
        $s.='<circle cx="'.round($cx,1).'" cy="'.round($cy,1).'" r="4.5" fill="#0d9488" fill-opacity="0.5" stroke="#0d9488" stroke-width="1"/>'; }
    $s.='</svg>'; return $s;
}
function svgGauge(float $val, float $max): string {
    $max=max($max,1); $v=max(0.0,min($val,$max)); $frac=$v/$max;
    $W=240;$H=158;$cx=120;$cy=138;$r=92;$sw=18;
    $theta=(1-$frac)*M_PI; $vx=$cx+$r*cos($theta); $vy=$cy-$r*sin($theta); $lx=$cx-$r; $rx=$cx+$r;
    $s='<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" style="max-height:175px" xmlns="http://www.w3.org/2000/svg" font-family="inherit">';
    $s.='<defs><linearGradient id="ggt" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2dd4bf"/><stop offset="1" stop-color="#0d9488"/></linearGradient></defs>';
    $s.='<path d="M '.$lx.' '.$cy.' A '.$r.' '.$r.' 0 0 1 '.$rx.' '.$cy.'" fill="none" stroke="#e2e8f0" stroke-width="'.$sw.'" stroke-linecap="round"/>';
    $s.='<path d="M '.$lx.' '.$cy.' A '.$r.' '.$r.' 0 0 1 '.round($vx,2).' '.round($vy,2).'" fill="none" stroke="url(#ggt)" stroke-width="'.$sw.'" stroke-linecap="round"/>';
    $s.='<text x="'.$cx.'" y="'.($cy-16).'" font-size="36" font-weight="800" fill="#0f172a" text-anchor="middle">'.number_format($val,1).'</text>';
    $s.='<text x="'.$cx.'" y="'.($cy+4).'" font-size="11" fill="#64748b" text-anchor="middle">Average H-Index</text>';
    $s.='<text x="'.$lx.'" y="'.($cy+20).'" font-size="9" fill="#94a3b8" text-anchor="middle">0</text>';
    $s.='<text x="'.$rx.'" y="'.($cy+20).'" font-size="9" fill="#94a3b8" text-anchor="middle">'.round($max).'</text>';
    $s.='</svg>'; return $s;
}
function rgDonut(array $segs): string {
    $total = 0; foreach ($segs as $s) $total += $s['value'];
    if ($total <= 0) return '<div style="color:var(--muted);font-size:13px;padding:20px">No data.</div>';
    $r = 54; $circ = 2 * M_PI * $r; $off = 0;
    $svg = '<svg viewBox="0 0 140 140" width="140" height="140" style="flex-shrink:0" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<circle cx="70" cy="70" r="54" fill="none" stroke="#eef2f7" stroke-width="20"/>';
    foreach ($segs as $seg) {
        if ($seg['value'] <= 0) continue;
        $len = $circ * $seg['value'] / $total;
        $key = htmlspecialchars($seg['key'] ?? $seg['label'], ENT_QUOTES);
        $svg .= '<circle class="rg-seg" data-cat="' . $key . '" style="cursor:pointer" cx="70" cy="70" r="54" fill="none" stroke="' . $seg['color'] . '" stroke-width="20" '
              . 'stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '" '
              . 'stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 70 70)" stroke-linecap="butt"><title>' . $key . '</title></circle>';
        $off += $len;
    }
    $svg .= '<text x="70" y="66" font-size="24" font-weight="800" fill="#0f172a" text-anchor="middle">' . $total . '</text>';
    $svg .= '<text x="70" y="84" font-size="10" fill="#64748b" text-anchor="middle">staff</text>';
    $svg .= '</svg>';
    return $svg;
}
?>

<!-- Page Header -->
<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1><?= htmlspecialchars($tdpp['faculty_name']) ?> — Analytics</h1>
        <p>Faculty-wide research performance metrics (<?= htmlspecialchars($tdpp['faculty_code']) ?>)</p>
    </div>
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-download"></i> Export Report
    </button>
</div>

<!-- KPI Cards -->
<div class="akpi-grid">
    <div class="akpi" style="--g1:#3b82f6;--g2:#2563eb">
        <div class="akpi-ic"><i class="fas fa-file-alt"></i></div>
        <div class="akpi-body">
            <div class="akpi-num" data-target="<?= (int)($kpiRow['pubs'] ?? 0) ?>" data-dec="0">0</div>
            <div class="akpi-label">Total Publications</div>
        </div>
    </div>
    <div class="akpi" style="--g1:#8b5cf6;--g2:#6d28d9">
        <div class="akpi-ic"><i class="fas fa-trophy"></i></div>
        <div class="akpi-body">
            <div class="akpi-num" data-target="<?= (int)($kpiRow['grants'] ?? 0) ?>" data-dec="0">0</div>
            <div class="akpi-label">Total Grants</div>
        </div>
    </div>
    <div class="akpi" style="--g1:#14b8a6;--g2:#0d9488">
        <div class="akpi-ic"><i class="fas fa-chart-line"></i></div>
        <div class="akpi-body">
            <div class="akpi-num" data-target="<?= (float)($kpiRow['hindex'] ?? 0) ?>" data-dec="1">0</div>
            <div class="akpi-label">Average H-Index</div>
        </div>
    </div>
    <div class="akpi" style="--g1:#f43f5e;--g2:#be123c">
        <div class="akpi-ic"><i class="fas fa-quote-left"></i></div>
        <div class="akpi-body">
            <div class="akpi-num" data-target="<?= (int)($kpiRow['citations'] ?? 0) ?>" data-dec="0">0</div>
            <div class="akpi-label">Total Citations</div>
        </div>
    </div>
</div>

<!-- Row 1: Trend + Quartile -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--blue)"></i> Publications by Year</div>
        <?php if (empty($pubTrend)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No approved publications yet.</p>
        <?php else: ?>
        <div class="bar-chart" style="height:160px;flex-direction:row !important;align-items:flex-end !important">
            <?php foreach ($pubTrend as $i => $row):
                $barStyle = 'height:' . $barPcts[$i] . '%;background:linear-gradient(0deg,var(--blue),var(--blue-light))';
            ?>
            <div class="bar-col" style="cursor:pointer" onclick="drillDown('year', '<?= $row['yr'] ?>')" title="Click to view <?= $row['yr'] ?> publications">
                <div class="bar-val"><?= $row['cnt'] ?></div>
                <div class="bar" style="<?= $barStyle ?>"></div>
                <div class="bar-label"><?= $row['yr'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:6px">Approved publications per year</div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--teal)"></i> Quartile Distribution</div>
        <div id="quartileDonut"></div>
    </div>
</div>

<!-- Row 2: Publication Type + Grant Category -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#3b82f6"></i> Publication Types
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(<?= array_sum(array_column($pubTypes,'cnt')) ?> total)</span>
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?><div id="pubTypeDonut"></div><?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#8b5cf6"></i> Grant Categories
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(<?= array_sum(array_column($grantCats,'cnt')) ?> total)</span>
        </div>
        <?php if (empty($grantCats)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?><div id="grantCatDonut"></div><?php endif; ?>
    </div>
</div>

<!-- Row 3: Publication Breakdown + Grant by Role -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-layer-group" style="color:var(--blue)"></i> Publication Breakdown</div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: foreach ($pubTypes as $i => $pt):
            $widthStyle = 'width:' . $typePcts[$i] . '%';
            $col = $pubTypeColors[$i % count($pubTypeColors)];
        ?>
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('pubtype', '<?= addslashes($pt['pub_type']) ?>')" title="Click to view records">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($pt['pub_type']) ?></span>
                </div>
                <strong><?= $pt['cnt'] ?></strong>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="<?= $widthStyle ?>;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user-tag" style="color:#8b5cf6"></i> Grant by Role (PI / Co-I / Member)</div>
        <?php if (empty($grantRoles)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <div id="grantRoleDonut" style="margin-bottom:1rem"></div>
        <?php
        $totalGrants = array_sum(array_column($grantRoles,'cnt')) ?: 1;
        foreach ($grantRoles as $i => $gr):
            $col = $grantRoleColors[$i % count($grantRoleColors)];
            $pct = round($gr['cnt'] / $totalGrants * 100);
        ?>
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('grantrole', '<?= addslashes($gr['role']) ?>')" title="Click to view grants">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($gr['role']) ?></span>
                </div>
                <span><strong><?= $gr['cnt'] ?></strong> <span style="color:var(--muted);font-size:11px">(<?= $pct ?>%)</span></span>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Grant Status: Active vs Non-Active (clickable) -->
<?php $gsActive=(int)($grantStatus['active']??0); $gsNon=(int)($grantStatus['nonactive']??0); $gsTot=$gsActive+$gsNon; ?>
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-circle-play" style="color:#22c55e"></i> Grant Status — Active vs Non-Active
        <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(click for details)</span>
    </div>
    <?php if ($gsTot === 0): ?>
    <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No grants yet.</p>
    <?php else: ?>
    <div id="grantStatusDonut" style="margin-bottom:1rem"></div>
    <div style="margin-bottom:.6rem;cursor:pointer" onclick="drillDown('grantactive','Active')" title="Click to view active grants">
        <div style="display:flex;justify-content:space-between;font-size:13px">
            <div style="display:flex;align-items:center;gap:7px"><div style="width:10px;height:10px;border-radius:50%;background:#22c55e"></div><span>Active</span></div>
            <strong><?= $gsActive ?></strong>
        </div>
    </div>
    <div style="cursor:pointer" onclick="drillDown('grantactive','Non-Active')" title="Click to view non-active grants">
        <div style="display:flex;justify-content:space-between;font-size:13px">
            <div style="display:flex;align-items:center;gap:7px"><div style="width:10px;height:10px;border-radius:50%;background:#ef4444"></div><span>Non-Active</span></div>
            <strong><?= $gsNon ?></strong>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- H-Index visuals (faculty) -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-braille" style="color:#0d9488"></i> H-Index vs Citations
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(each dot = a lecturer)</span>
        </div>
        <?php if (empty($hScatter)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <?= svgScatter($hScatter, (float)($kpiRow['hindex'] ?? 0)) ?>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-gauge-high" style="color:#8b5cf6"></i> Average H-Index &amp; Top Researchers</div>
        <?= svgGauge((float)($kpiRow['hindex'] ?? 0), $gaugeMax) ?>
        <?php if (!empty($hTop)): $hMax = max(1, (int)round((float)$hTop[0]['h'])); ?>
        <div style="margin-top:10px;border-top:1px solid var(--border);padding-top:12px">
            <?php foreach ($hTop as $t): $hv = (int)round((float)$t['h']); $w = round($hv / $hMax * 100); ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px">
                <span style="flex:0 0 150px;font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($t['full_name']) ?></span>
                <div style="flex:1;position:relative;height:14px">
                    <div style="position:absolute;top:6px;left:0;width:<?= $w ?>%;height:2px;background:#cbd5e1"></div>
                    <div style="position:absolute;top:2px;left:calc(<?= $w ?>% - 5px);width:11px;height:11px;border-radius:50%;background:#0d9488;box-shadow:0 0 0 2px #fff"></div>
                </div>
                <span style="flex:0 0 26px;text-align:right;font-size:13px;font-weight:700;color:#0d9488"><?= $hv ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Research Group overview (faculty) -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:#8b5cf6"></i> Staff by Research Category
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(click for names)</span>
        </div>
        <?php
            $rgSegs = [
                ['key' => 'FG',       'label' => 'FG (Focus Group)',         'value' => $rgCatMap['FG'],       'color' => '#0d9488'],
                ['key' => 'CoR',      'label' => 'CoR (Centre of Research)', 'value' => $rgCatMap['CoR'],      'color' => '#8b5cf6'],
                ['key' => 'External', 'label' => 'External',                 'value' => $rgCatMap['External'], 'color' => '#2563eb'],
                ['key' => 'Not set',  'label' => 'Not set',                  'value' => $rgCatMap['Not set'],  'color' => '#94a3b8'],
            ];
            $rgTotal = array_sum(array_column($rgSegs, 'value'));
        ?>
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <?= rgDonut($rgSegs) ?>
            <div style="flex:1;min-width:170px">
                <?php foreach ($rgSegs as $cs): if ($cs['value'] <= 0) continue; $pc = $rgTotal ? round($cs['value'] / $rgTotal * 100) : 0; ?>
                <div class="rg-cat-row" data-cat="<?= htmlspecialchars($cs['key'], ENT_QUOTES) ?>" style="display:flex;align-items:center;gap:8px;margin-bottom:9px;font-size:13px;cursor:pointer;padding:3px 4px;border-radius:5px">
                    <span style="width:12px;height:12px;border-radius:3px;background:<?= $cs['color'] ?>;flex-shrink:0"></span>
                    <span style="flex:1"><?= htmlspecialchars($cs['label']) ?></span>
                    <strong><?= $cs['value'] ?></strong>
                    <span style="color:var(--muted);font-size:11px;width:38px;text-align:right"><?= $pc ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:#2563eb"></i> Members per Research Group
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(click a bar for names)</span>
        </div>
        <?php if (empty($rgSizes)): ?>
        <p style="color:var(--muted);font-size:13px">No research groups in this faculty.</p>
        <?php else: foreach ($rgSizes as $row): $w = round($row['cnt'] / $rgSizeMax * 100); ?>
        <div class="rg-bar-row" data-grp="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>" style="margin-bottom:9px;cursor:pointer;padding:2px 4px;border-radius:5px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
                <span style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80%"><?= htmlspecialchars($row['name']) ?></span>
                <strong><?= (int)$row['cnt'] ?></strong>
            </div>
            <div style="height:9px;background:#e9eef5;border-radius:5px;overflow:hidden">
                <div style="height:100%;width:<?= $w ?>%;border-radius:5px;background:linear-gradient(90deg,#60a5fa,#2563eb)"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Research-group drill-down panel -->
<div class="card" id="rgDetail" style="display:none;margin-bottom:1rem;border-left:4px solid #0d9488">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span id="rgDetailTitle"></span>
        <span onclick="closeRgDetail()" style="cursor:pointer;font-size:13px;font-weight:500;color:var(--muted)"><i class="fas fa-xmark"></i> Close</span>
    </div>
    <div id="rgDetailBody"></div>
</div>

<style>.rg-cat-row:hover,.rg-bar-row:hover{background:#f1f5f9}.rg-seg:hover{opacity:.82}</style>
<script>
(function(){
    var rgGroupMembers = <?= json_encode($grpMembers ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
    var rgCatMembers   = <?= json_encode($catMembers ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
    function e(s){ if(s===null||s===undefined||s==='') return '—'; return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    window.showRg = function(type, key){
        var list = (type==='group' ? rgGroupMembers[key] : rgCatMembers[key]) || [];
        var label = (type==='group' ? 'Research Group: ' : 'Category: ') + key;
        document.getElementById('rgDetailTitle').innerHTML = '<i class="fas fa-users" style="color:#0d9488"></i> ' + e(label) + ' <span style="color:var(--muted);font-weight:400;font-size:13px">(' + list.length + ' staff)</span>';
        var rows = list.map(function(m){ return '<tr><td style="font-weight:600">'+e(m.name)+'</td><td>'+e(m.staff_no)+'</td><td>'+e(m.grade)+'</td><td>'+e(m.cat)+'</td></tr>'; }).join('');
        if(!rows) rows = '<tr><td colspan="4" style="color:var(--muted)">No staff.</td></tr>';
        document.getElementById('rgDetailBody').innerHTML = '<div class="table-wrap"><table class="arams-table"><thead><tr><th>Name</th><th>Staff No</th><th>Grade</th><th>Category</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
        var p = document.getElementById('rgDetail'); p.style.display='block'; p.scrollIntoView({behavior:'smooth', block:'nearest'});
    };
    window.closeRgDetail = function(){ document.getElementById('rgDetail').style.display='none'; };
    function bind(){
        document.querySelectorAll('.rg-cat-row, .rg-seg').forEach(function(el){ el.addEventListener('click', function(){ showRg('cat', el.getAttribute('data-cat')); }); });
        document.querySelectorAll('.rg-bar-row').forEach(function(el){ el.addEventListener('click', function(){ showRg('group', el.getAttribute('data-grp')); }); });
    }
    if (document.readyState !== 'loading') bind(); else document.addEventListener('DOMContentLoaded', bind);
})();
</script>

<!-- Row 4: KPI Completion by Lecturer (TDPP-specific) -->
<div class="card">
    <div class="card-title"><i class="fas fa-ranking-star" style="color:#f59e0b"></i> KPI Completion by Lecturer</div>
    <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:420px">
            <thead><tr><th>Lecturer</th><th>Done / Total</th><th style="min-width:180px">Completion Rate</th></tr></thead>
            <tbody>
            <?php foreach ($kpiByLec as $k): $r = $k['total']>0 ? round($k['done']/$k['total']*100) : 0; ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($k['full_name']) ?></td>
                <td style="font-weight:700"><?= (int)$k['done'] ?> / <?= (int)$k['total'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                            <div style="height:100%;width:<?= $r ?>%;border-radius:4px;background:linear-gradient(90deg,var(--blue),var(--teal))"></div>
                        </div>
                        <span style="font-size:12px;font-weight:600;min-width:32px"><?= $r ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($kpiByLec)): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1.5rem">No KPI tasks assigned yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof renderDonut !== 'function') return;

    renderDonut('quartileDonut', [
        { label:'Q1',  value:<?= (int)($qMap['Q1']  ?? 0) ?>, color:'#1d4ed8' },
        { label:'Q2',  value:<?= (int)($qMap['Q2']  ?? 0) ?>, color:'#3b82f6' },
        { label:'Q3',  value:<?= (int)($qMap['Q3']  ?? 0) ?>, color:'#60a5fa' },
        { label:'Q4',  value:<?= (int)($qMap['Q4']  ?? 0) ?>, color:'#93c5fd' },
        { label:'N/A', value:<?= (int)($qMap['N/A'] ?? 0) ?>, color:'#cbd5e1' }
    ]);

    <?php if (!empty($pubTypes)): ?>
    renderDonut('pubTypeDonut', [
        <?php foreach ($pubTypes as $i => $pt): $col = $pubTypeColors[$i % count($pubTypeColors)]; ?>
        { label: '<?= addslashes($pt['pub_type']) ?>', value: <?= (int)$pt['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantCats)): ?>
    renderDonut('grantCatDonut', [
        <?php foreach ($grantCats as $i => $gc): $col = $grantCatColors[$i % count($grantCatColors)]; ?>
        { label: '<?= addslashes($gc['grant_category']) ?>', value: <?= (int)$gc['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantRoles)): ?>
    renderDonut('grantRoleDonut', [
        <?php foreach ($grantRoles as $i => $gr): $col = $grantRoleColors[$i % count($grantRoleColors)]; ?>
        { label: '<?= addslashes($gr['role']) ?>', value: <?= (int)$gr['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if ($gsTot > 0): ?>
    renderDonut('grantStatusDonut', [
        { label:'Active',     value:<?= $gsActive ?>, color:'#14b8a6' },
        { label:'Non-Active', value:<?= $gsNon ?>,    color:'#94a3b8' }
    ]);
    <?php endif; ?>
});
</script>

<!-- ── Drill-Down Detail Panel (auto-appears at bottom) ── -->
<div class="card" id="detailPanel" style="display:none;margin-top:1rem;scroll-margin-top:20px">
    <div class="card-title" style="justify-content:space-between">
        <span><i class="fas fa-list" style="color:var(--teal)"></i> <span id="detailTitle">Records</span>
            <span id="detailCount" style="font-size:12px;color:var(--muted);font-weight:400;margin-left:6px"></span>
        </span>
        <button class="btn btn-outline btn-sm" onclick="closeDrill()"><i class="fas fa-times"></i> Close</button>
    </div>
    <div class="table-wrap">
        <table class="arams-table" id="detailTable">
            <thead id="detailHead"></thead>
            <tbody id="detailBody"></tbody>
        </table>
    </div>
</div>

<script>
function drillDown(type, value) {
    var panel = document.getElementById('detailPanel');
    var body  = document.getElementById('detailBody');
    var head  = document.getElementById('detailHead');
    document.getElementById('detailTitle').textContent = 'Loading...';
    document.getElementById('detailCount').textContent = '';
    body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">Loading records...</td></tr>';
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior:'smooth', block:'start' });

    fetch('/arams/api/analytics_detail.php?type=' + encodeURIComponent(type) + '&value=' + encodeURIComponent(value))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { body.innerHTML = '<tr><td style="padding:1rem;color:var(--muted)">' + (res.message||'No data') + '</td></tr>'; return; }
            document.getElementById('detailTitle').textContent = res.title;
            document.getElementById('detailCount').textContent = '(' + res.count + ' record' + (res.count!=1?'s':'') + ')';

            if (res.count === 0) {
                head.innerHTML = '';
                body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">No records found for this selection.</td></tr>';
                return;
            }

            var hasLecturer = res.rows.length && (res.rows[0].lecturer_name !== undefined);
            var lecHead = hasLecturer ? '<th>Lecturer</th>' : '';

            if (res.kind === 'publication') {
                head.innerHTML = '<tr>' + lecHead + '<th>Title</th><th>Authors</th><th>Journal</th><th>Year</th><th>Quartile</th><th>Indexing</th><th>Status</th></tr>';
                body.innerHTML = res.rows.map(function(r){
                    var lecCell = hasLecturer ? '<td style="font-size:11px;font-weight:600;white-space:nowrap">' + esc((r.lecturer_title? r.lecturer_title+' ':'') + r.lecturer_name) + '</td>' : '';
                    return '<tr>' + lecCell +
                        '<td style="font-weight:600;font-size:12px;max-width:240px">' + esc(r.title) + '</td>' +
                        '<td style="font-size:11px;color:var(--muted);max-width:160px">' + esc(r.authors) + '</td>' +
                        '<td style="font-size:11px">' + esc(r.journal_name) + '</td>' +
                        '<td>' + esc(r.pub_year) + '</td>' +
                        '<td><span class="badge badge-blue" style="font-size:10px">' + esc(r.quartile) + '</span></td>' +
                        '<td style="font-size:11px">' + esc(r.indexing_type) + '</td>' +
                        '<td><span class="badge badge-green" style="font-size:10px">' + esc(r.status) + '</span></td>' +
                        '</tr>';
                }).join('');
            } else {
                head.innerHTML = '<tr>' + lecHead + '<th>Grant Title</th><th>Code</th><th>Funder</th><th>Category</th><th>Level</th><th>Role</th><th>Amount</th><th>Status</th></tr>';
                body.innerHTML = res.rows.map(function(r){
                    var lecCell = hasLecturer ? '<td style="font-size:11px;font-weight:600;white-space:nowrap">' + esc((r.lecturer_title? r.lecturer_title+' ':'') + r.lecturer_name) + '</td>' : '';
                    return '<tr>' + lecCell +
                        '<td style="font-weight:600;font-size:12px;max-width:220px">' + esc(r.grant_title) + '</td>' +
                        '<td style="font-size:11px">' + esc(r.grant_code) + '</td>' +
                        '<td style="font-size:11px">' + esc(r.funder) + '</td>' +
                        '<td style="font-size:11px">' + esc(r.grant_category) + '</td>' +
                        '<td><span class="badge badge-grey" style="font-size:10px">' + esc(r.grant_level) + '</span></td>' +
                        '<td><span class="badge badge-blue" style="font-size:10px">' + esc(r.role) + '</span></td>' +
                        '<td style="font-size:11px">RM ' + Number(r.amount||0).toLocaleString() + '</td>' +
                        '<td><span class="badge badge-green" style="font-size:10px">' + esc(r.status) + '</span></td>' +
                        '</tr>';
                }).join('');
            }
        })
        .catch(function(){ body.innerHTML = '<tr><td style="padding:1rem;color:#dc2626">Error loading records.</td></tr>'; });
}
function closeDrill() { document.getElementById('detailPanel').style.display = 'none'; }
function esc(s) { if (s===null||s===undefined) return '—'; return String(s).replace(/[&<>"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

// Attach donut hovers + legend clicks after donuts render
setTimeout(function() {
    ['quartileDonut','pubTypeDonut','grantCatDonut','grantRoleDonut'].forEach(function(id){
        attachDonutHovers(id);
    });
    attachDonutClicks('quartileDonut', 'quartile');
    attachDonutClicks('pubTypeDonut',  'pubtype');
    attachDonutClicks('grantCatDonut', 'grantcat');
    attachDonutClicks('grantRoleDonut','grantrole');
}, 300);

// ✅ CORRECT version — targets .legend-item directly (same as admin)
function attachDonutClicks(donutId, filterType) {
    var container = document.getElementById(donutId);
    if (!container) return;
    var items = container.querySelectorAll('.legend-item');
    items.forEach(function(item){
        item.style.cursor = 'pointer';
        item.title = 'Click to view records';
        item.addEventListener('mouseenter', function(){ item.style.opacity = '0.7'; });
        item.addEventListener('mouseleave', function(){ item.style.opacity = '1'; });
        item.addEventListener('click', function(){
            var label = '';
            var spans = item.querySelectorAll('span');
            for (var i=0; i<spans.length; i++) {
                if (!spans[i].classList.contains('legend-val')) { label = spans[i].textContent.trim(); break; }
            }
            if (label) drillDown(filterType, label);
        });
    });
}

// Donut hover tooltips
function attachDonutHovers(donutId) {
    var d = document.getElementById(donutId);
    if (!d) return;
    var svg = d.querySelector('svg'); if (!svg) return;
    var tip = document.getElementById('donutTip');
    if (!tip) {
        tip = document.createElement('div');
        tip.id = 'donutTip';
        tip.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;background:#0f172a;color:#fff;padding:6px 10px;border-radius:6px;font-size:12px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.25);display:none;white-space:nowrap';
        document.body.appendChild(tip);
    }
    var map = {};
    d.querySelectorAll('.legend-item').forEach(function(li){
        var sw = li.querySelector('[style*="background"]');
        if (!sw) return;
        var m = (sw.getAttribute('style') || '').match(/background:\s*([^;]+)/);
        if (m) map[m[1].trim().toLowerCase()] = li.textContent.replace(/\s+/g,' ').trim();
    });
    svg.querySelectorAll('circle').forEach(function(c){
        var stroke = (c.getAttribute('stroke') || '').toLowerCase();
        var label = map[stroke]; if (!label) return;
        var baseW = c.getAttribute('stroke-width');
        c.style.cursor = 'pointer';
        c.style.transition = 'stroke-width .15s';
        c.addEventListener('mouseenter', function(){ tip.textContent = label; tip.style.display='block'; c.setAttribute('stroke-width',(parseFloat(baseW)+4)); });
        c.addEventListener('mousemove', function(e){ tip.style.left=(e.clientX+14)+'px'; tip.style.top=(e.clientY-10)+'px'; });
        c.addEventListener('mouseleave', function(){ tip.style.display='none'; c.setAttribute('stroke-width',baseW); });
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
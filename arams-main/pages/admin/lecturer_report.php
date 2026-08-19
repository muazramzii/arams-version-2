<?php
// ============================================================
//  ARAMS — Individual Lecturer Performance Report
// ============================================================
$pageTitle  = 'Lecturer Report';
$activePage = 'reports';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Fetch ALL faculties for filter dropdown
$faculties = $db->query(
    "SELECT faculty_id, faculty_code, faculty_name
     FROM tbl_faculty ORDER BY faculty_code"
)->fetchAll();

// Fetch ALL lecturers with faculty_id for JS filtering
$lecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, l.position, l.grade,
            l.faculty_id,
            f.faculty_name, f.faculty_code, u.email
     FROM tbl_lecturer l
     JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     JOIN tbl_user   u ON u.user_id    = l.user_id
     ORDER BY l.full_name"
)->fetchAll();

// Selected lecturer
$selectedId = (int)($_GET['lecturer_id'] ?? 0);
$lec = $kpi = null;
$pubs = $grants = $hindexes = $incomes = $awards = [];

if ($selectedId) {
    $st = $db->prepare(
        "SELECT l.*, f.faculty_name, f.faculty_code, u.email
         FROM tbl_lecturer l
         JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
         JOIN tbl_user   u ON u.user_id    = l.user_id
         WHERE l.lecturer_id = ?"
    );
    $st->execute([$selectedId]); $lec = $st->fetch();

    $st = $db->prepare("SELECT * FROM vw_lecturer_kpi WHERE lecturer_id = ?");
    $st->execute([$selectedId]); $kpi = $st->fetch() ?: [];

    $st = $db->prepare(
        "SELECT p.*, rd.submission_date FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
         ORDER BY p.pub_year DESC"
    );
    $st->execute([$selectedId]); $pubs = $st->fetchAll();

    $st = $db->prepare(
        "SELECT g.* FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
         ORDER BY g.start_date DESC"
    );
    $st->execute([$selectedId]); $grants = $st->fetchAll();

    $st = $db->prepare(
        "SELECT h.* FROM tbl_hindex h
         JOIN tbl_research_data rd ON h.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
         ORDER BY h.record_year DESC"
    );
    $st->execute([$selectedId]); $hindexes = $st->fetchAll();

    $st = $db->prepare(
        "SELECT i.* FROM tbl_research_income i
         JOIN tbl_research_data rd ON i.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
         ORDER BY i.year_received DESC"
    );
    $st->execute([$selectedId]); $incomes = $st->fetchAll();

    $st = $db->prepare(
        "SELECT * FROM tbl_award WHERE lecturer_id = ? ORDER BY award_year DESC"
    );
    $st->execute([$selectedId]); $awards = $st->fetchAll();
}

// ── Print-friendly inline SVG chart helpers ───────────────────
// Vertical bar chart from ['2021'=>3, '2022'=>5, ...] (insertion order = x order)
function svgBar(array $data, string $color = '#2563eb', string $prefix = ''): string {
    if (empty($data)) return '<p style="color:var(--muted);font-size:13px;padding:1rem 0">No data to chart.</p>';
    $max = max($data); $max = $max > 0 ? $max : 1;
    $n = count($data);
    $bw = 44; $gap = 20; $chartH = 156; $padTop = 18; $padBot = 26; $padLeft = 30;
    $w = max(280, $padLeft + $n * ($bw + $gap) + $gap);
    $h = $chartH + $padTop + $padBot;
    $baseY = $padTop + $chartH;
    $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" style="max-width:'.$w.'px;height:auto" xmlns="http://www.w3.org/2000/svg" font-family="system-ui,sans-serif">';
    // horizontal gridlines + y scale (4 steps)
    for ($i = 0; $i <= 4; $i++) {
        $gy = $padTop + $chartH - ($i / 4) * $chartH;
        $gv = round(($i / 4) * $max);
        $svg .= '<line x1="'.$padLeft.'" y1="'.$gy.'" x2="'.$w.'" y2="'.$gy.'" stroke="'.($i === 0 ? '#cbd5e1' : '#eef2f6').'"/>';
        $svg .= '<text x="'.($padLeft-6).'" y="'.($gy+4).'" text-anchor="end" font-size="10" fill="#94a3b8">'.$gv.'</text>';
    }
    $x = $padLeft + $gap;
    foreach ($data as $label => $val) {
        $bh = (int)round(($val / $max) * $chartH);
        $y = $baseY - $bh;
        $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$bw.'" height="'.max($bh,1).'" rx="4" fill="'.$color.'"/>';
        $svg .= '<text x="'.($x+$bw/2).'" y="'.($y-5).'" text-anchor="middle" font-size="13" font-weight="700" fill="#0f172a">'.$prefix.htmlspecialchars((string)$val).'</text>';
        $svg .= '<text x="'.($x+$bw/2).'" y="'.($baseY+18).'" text-anchor="middle" font-size="12" fill="#475569" font-weight="600">'.htmlspecialchars((string)$label).'</text>';
        $x += $bw + $gap;
    }
    return $svg.'</svg>';
}

// Donut from [['label'=>,'value'=>,'color'=>], ...] with legend
function svgDonut(array $segs): string {
    $segs = array_values(array_filter($segs, fn($s) => ($s['value'] ?? 0) > 0));
    if (empty($segs)) return '<p style="color:var(--muted);font-size:13px;padding:1rem 0">No data to chart.</p>';
    $total = array_sum(array_column($segs, 'value'));
    $r = 54; $cx = 70; $cy = 70; $circ = 2 * M_PI * $r;
    $off = 0;
    $ring = '';
    foreach ($segs as $s) {
        $frac = $s['value'] / $total;
        $len = $frac * $circ;
        $ring .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$s['color'].'" stroke-width="22" '
              .  'stroke-dasharray="'.round($len,2).' '.round($circ-$len,2).'" stroke-dashoffset="'.round(-$off,2).'" transform="rotate(-90 '.$cx.' '.$cy.')"/>';
        $off += $len;
    }
    $svg = '<div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">';
    $svg .= '<svg viewBox="0 0 140 140" width="140" height="140" xmlns="http://www.w3.org/2000/svg">'.$ring
          . '<text x="70" y="66" text-anchor="middle" font-size="22" font-weight="700" fill="#0f172a" font-family="system-ui">'.$total.'</text>'
          . '<text x="70" y="84" text-anchor="middle" font-size="11" fill="#64748b" font-family="system-ui">total</text></svg>';
    $svg .= '<div style="display:flex;flex-direction:column;gap:6px;font-size:13px">';
    foreach ($segs as $s) {
        $pct = round($s['value'] / $total * 100);
        $svg .= '<div style="display:flex;align-items:center;gap:8px">'
              . '<span style="width:11px;height:11px;border-radius:3px;background:'.$s['color'].';flex-shrink:0"></span>'
              . '<span style="color:#334155">'.htmlspecialchars($s['label']).'</span>'
              . '<strong style="margin-left:auto;padding-left:10px">'.$s['value'].'</strong>'
              . '<span style="color:#94a3b8;font-size:11px">'.$pct.'%</span></div>';
    }
    return $svg.'</div></div>';
}

// ── Per-type aggregation (Publications) ───────────────────────
$pubByYear = [];
foreach (array_reverse($pubs) as $p) { $y = $p['pub_year'] ?: '—'; $pubByYear[$y] = ($pubByYear[$y] ?? 0) + 1; }
$pubQuartile = [];
foreach ($pubs as $p) { $q = $p['quartile'] ?: 'N/A'; $pubQuartile[$q] = ($pubQuartile[$q] ?? 0) + 1; }
$qColors = ['Q1'=>'#16a34a','Q2'=>'#3b82f6','Q3'=>'#f59e0b','Q4'=>'#ef4444','N/A'=>'#94a3b8'];
$pubDonut = [];
foreach ($pubQuartile as $q => $c) $pubDonut[] = ['label'=>$q,'value'=>$c,'color'=>$qColors[$q] ?? '#8b5cf6'];

// ── Grants: by year + Active/Non-Active ───────────────────────
$today = date('Y-m-d');
$grantByYear = [];
foreach (array_reverse($grants) as $g) {
    $yr = !empty($g['start_date']) ? date('Y', strtotime($g['start_date'])) : '—';
    $grantByYear[$yr] = ($grantByYear[$yr] ?? 0) + 1;
}
$gActive = $gNon = 0;
foreach ($grants as $g) {
    $end = $g['end_date'] ?? null;
    if (empty($end) || $end >= $today) $gActive++; else $gNon++;
}
$grantDonut = [];
if ($gActive) $grantDonut[] = ['label'=>'Active','value'=>$gActive,'color'=>'#16a34a'];
if ($gNon)    $grantDonut[] = ['label'=>'Non-Active','value'=>$gNon,'color'=>'#ef4444'];

// ── H-Index: value by year + source breakdown ─────────────────
$hindexByYear = [];
foreach (array_reverse($hindexes) as $h) $hindexByYear[$h['record_year']] = (int)$h['hindex_value'];
$hSource = [];
foreach ($hindexes as $h) { $src = $h['source'] ?: 'Other'; $hSource[$src] = ($hSource[$src] ?? 0) + 1; }
$srcColors = ['Scopus'=>'#e8590c','Web of Science'=>'#3b82f6','WoS'=>'#3b82f6','Google Scholar'=>'#16a34a','Other'=>'#94a3b8'];
$hindexDonut = [];
foreach ($hSource as $src => $c) $hindexDonut[] = ['label'=>$src,'value'=>$c,'color'=>$srcColors[$src] ?? '#8b5cf6'];

// ── Income: RM'000 by year + category breakdown ───────────────
$incByYearK = [];
foreach (array_reverse($incomes) as $inc) {
    $yr = $inc['year_received'] ?: '—';
    $incByYearK[$yr] = ($incByYearK[$yr] ?? 0) + (float)($inc['amount'] ?? 0);
}
foreach ($incByYearK as $yr => $amt) $incByYearK[$yr] = (int)round($amt / 1000);
$incCat = [];
foreach ($incomes as $inc) { $c = $inc['income_category'] ?? $inc['source'] ?? 'Other'; $incCat[$c] = ($incCat[$c] ?? 0) + (float)($inc['amount'] ?? 0); }
$pool = ['#2563eb','#16a34a','#f59e0b','#8b5cf6','#ef4444','#0ea5e9','#ec4899'];
$incomeDonut = []; $ci = 0;
foreach ($incCat as $c => $amt) $incomeDonut[] = ['label'=>$c,'value'=>(int)round($amt),'color'=>$pool[$ci++ % count($pool)]];

// ── Awards: by year + level breakdown ─────────────────────────
$awardByYear = [];
foreach (array_reverse($awards) as $aw) { $yr = $aw['award_year'] ?: '—'; $awardByYear[$yr] = ($awardByYear[$yr] ?? 0) + 1; }
$awLevel = [];
foreach ($awards as $aw) { $lv = $aw['level'] ?: 'Other'; $awLevel[$lv] = ($awLevel[$lv] ?? 0) + 1; }
$lvColors = ['International'=>'#2563eb','National'=>'#16a34a','University'=>'#f59e0b','State'=>'#8b5cf6','Other'=>'#94a3b8'];
$awardDonut = [];
foreach ($awLevel as $lv => $c) $awardDonut[] = ['label'=>$lv,'value'=>$c,'color'=>$lvColors[$lv] ?? '#94a3b8'];
?>

<!-- Buttons row -->
<div style="margin-bottom:1rem;display:flex;align-items:center;gap:1rem" class="no-print">
    <a href="/arams/pages/admin/reports.php" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Reports
    </a>
    <?php if ($lec): ?>
    <button class="btn btn-teal btn-sm" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
    <?php endif; ?>
</div>

<!-- ── SELECTOR CARD — Badge faculty filter ──────────────── -->
<div class="card no-print" style="margin-bottom:1rem">
    <div class="card-title">
        <i class="fas fa-user-graduate" style="color:var(--blue)"></i>
        Select Lecturer
    </div>

    <!-- Step 1: Faculty badge filter — click to filter instantly -->
    <div style="margin-bottom:1rem">
        <label class="form-label" style="margin-bottom:.5rem;display:block">
            Step 1 — Filter by Faculty
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            <button type="button" class="fac-badge active"
                    onclick="selectFaculty(this, '')">All</button>
            <?php foreach ($faculties as $fac): ?>
            <button type="button" class="fac-badge"
                    onclick="selectFaculty(this, '<?= $fac['faculty_id'] ?>')">
                <?= htmlspecialchars($fac['faculty_code']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 2: Lecturer select -->
    <form method="GET" action="">
        <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label">Step 2 — Choose Lecturer</label>
            <select class="form-control" name="lecturer_id" id="lecturerSelect">
                <option value="">— Select a Lecturer —</option>
                <?php foreach ($lecturers as $l): ?>
                <option value="<?= $l['lecturer_id'] ?>"
                        data-fac="<?= (int)$l['faculty_id'] ?>"
                        <?= $selectedId === (int)$l['lecturer_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['full_name']) ?>
                    (<?= htmlspecialchars($l['faculty_code']) ?>)
                    <?= $l['grade'] ? '— ' . htmlspecialchars($l['grade']) : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-teal">
            <i class="fas fa-search"></i> Load Report
        </button>
    </form>
</div>

<style>
.fac-badge {
    padding:5px 14px; border-radius:20px;
    border:1px solid var(--border);
    background:var(--grey); color:var(--text);
    font-size:12px; font-weight:500;
    cursor:pointer; transition:.15s;
}
.fac-badge:hover { border-color:var(--teal); color:var(--teal); }
.fac-badge.active { background:var(--teal); color:#fff; border-color:var(--teal); }
.rtype-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1rem; }
.rtab { cursor:pointer; border:1px solid var(--border); background:#fff; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:600; color:var(--text); display:inline-flex; align-items:center; gap:6px; }
.rtab:hover { border-color:var(--teal); color:var(--teal); }
.rtab.active { background:var(--teal); color:#fff; border-color:var(--teal); }
.report-letterhead { display:flex; align-items:center; gap:18px; padding:4px 4px 14px; border-bottom:3px solid #0B3C5D; margin-bottom:1.25rem; }
.report-letterhead .lh-logo { height:72px; width:auto; flex-shrink:0; }
.report-letterhead .lh-titles { flex:1; text-align:center; }
.report-letterhead .lh-uni { font-size:16px; font-weight:800; letter-spacing:.4px; color:#0B3C5D; line-height:1.2; }
.report-letterhead .lh-sub { font-size:11px; color:#64748b; margin:3px 0 7px; }
.report-letterhead .lh-report { font-size:13px; font-weight:700; letter-spacing:1.2px; color:#0d9488; }
.report-letterhead .lh-meta { text-align:right; font-size:10.5px; color:#64748b; flex-shrink:0; line-height:1.7; min-width:120px; }
.report-letterhead .lh-meta div:last-child { color:#b91c1c; font-weight:700; letter-spacing:.5px; }
.rkpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:1.25rem; }
.rkpi { background:#fff; border:1px solid #e2e8f0; border-top:4px solid var(--accent); border-radius:10px; padding:14px 16px; }
.rkpi-top { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
.rkpi-top i { font-size:18px; color:var(--accent); }
.rkpi-num { font-size:28px; font-weight:800; color:#0f172a; line-height:1; }
.rkpi-label { font-size:11.5px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.4px; }
.rkpi-sub { font-size:11px; color:#64748b; margin-top:3px; }
</style>

<?php if (!$selectedId): ?>
<!-- Empty state -->
<div style="text-align:center;padding:4rem;color:var(--muted)">
    <i class="fas fa-user-graduate"
       style="font-size:48px;opacity:.3;margin-bottom:1rem;display:block"></i>
    <p style="font-size:15px">Select a lecturer above to generate their performance report.</p>
</div>

<?php elseif (!$lec): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> Lecturer not found.
</div>

<?php else:
$photo    = $lec['profile_photo'] ?? '';
$photoUrl = ($photo && file_exists(__DIR__ . '/../../assets/images/profiles/' . $photo))
            ? '/arams/assets/images/profiles/' . htmlspecialchars($photo) : '';
$initials = strtoupper(substr($lec['full_name'], 0, 2));
?>

<!-- ══════════════════════════════════════
     PRINTABLE REPORT
════════════════════════════════════════ -->
<div id="reportContent">

    <!-- ══ Official letterhead (prints) ══ -->
    <div class="report-letterhead">
        <img src="/arams/assets/images/uthm_logo.png" alt="UTHM" class="lh-logo">
        <div class="lh-titles">
            <div class="lh-uni">UNIVERSITI TUN HUSSEIN ONN MALAYSIA</div>
            <div class="lh-sub">Academic Research Analytics and Monitoring System (ARAMS)</div>
            <div class="lh-report">ACADEMIC RESEARCH PERFORMANCE REPORT</div>
        </div>
        <div class="lh-meta">
            <div>Ref: ARAMS/<?= htmlspecialchars($lec['staff_no'] ?: '—') ?>/<?= date('Y') ?></div>
            <div><?= date('d M Y') ?></div>
            <div>CONFIDENTIAL</div>
        </div>
    </div>

    <!-- Profile Header -->
    <div class="card" style="margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
            <!-- Photo -->
            <?php if ($photoUrl): ?>
            <img src="<?= $photoUrl ?>"
                 style="width:80px;height:80px;border-radius:50%;object-fit:cover;
                        border:3px solid var(--teal);flex-shrink:0">
            <?php else: ?>
            <div style="width:80px;height:80px;border-radius:50%;flex-shrink:0;
                        background:linear-gradient(135deg,var(--blue),var(--teal));
                        display:flex;align-items:center;justify-content:center;
                        font-size:28px;font-weight:700;color:#fff;
                        border:3px solid var(--teal)">
                <?= $initials ?>
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div style="flex:1">
                <h2 style="margin:0 0 4px;font-size:20px">
                    <?= htmlspecialchars($lec['full_name']) ?>
                </h2>
                <div style="font-size:13px;color:var(--muted);margin-bottom:8px">
                    <?= htmlspecialchars($lec['position'] ?? 'Lecturer') ?>
                    <?= $lec['grade'] ? '(' . htmlspecialchars($lec['grade']) . ')' : '' ?>
                    — <?= htmlspecialchars($lec['faculty_name']) ?>
                </div>
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:13px">
                    <span>
                        <i class="fas fa-envelope" style="color:var(--teal);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['email']) ?>
                    </span>
                    <span>
                        <i class="fas fa-id-badge" style="color:var(--blue);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['staff_no']) ?>
                    </span>
                    <?php if ($lec['department']): ?>
                    <span>
                        <i class="fas fa-building" style="color:var(--muted);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['department']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($lec['research_centre']): ?>
                    <span>
                        <i class="fas fa-flask" style="color:var(--muted);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['research_centre']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($lec['scopus_id'] || $lec['orcid_id'] || $lec['lens_id']): ?>
                <div style="margin-top:8px;display:flex;gap:1rem;flex-wrap:wrap;
                            font-size:12px;color:var(--muted)">
                    <?php if ($lec['scopus_id']): ?>
                    <span>Scopus: <strong><?= htmlspecialchars($lec['scopus_id']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($lec['orcid_id']): ?>
                    <span>ORCID: <strong><?= htmlspecialchars($lec['orcid_id']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($lec['lens_id']): ?>
                    <span>Lens: <strong><?= htmlspecialchars($lec['lens_id']) ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Generated date -->
            <div style="text-align:right;font-size:11px;color:var(--muted)">
                <div>Report Generated</div>
                <div style="font-weight:600;color:var(--text)"><?= date('d M Y, H:i') ?></div>
                <div style="margin-top:4px">UTHM ARAMS</div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rkpi-grid">
        <div class="rkpi" style="--accent:#2563eb">
            <div class="rkpi-top"><i class="fas fa-file-alt"></i><span class="rkpi-num"><?= (int)($kpi['total_publications'] ?? 0) ?></span></div>
            <div class="rkpi-label">Total Publications</div>
            <div class="rkpi-sub">Q1: <?= (int)($kpi['q1_pubs'] ?? 0) ?> &middot; Q2: <?= (int)($kpi['q2_pubs'] ?? 0) ?></div>
        </div>
        <div class="rkpi" style="--accent:#8b5cf6">
            <div class="rkpi-top"><i class="fas fa-trophy"></i><span class="rkpi-num"><?= (int)($kpi['total_grants'] ?? 0) ?></span></div>
            <div class="rkpi-label">Total Grants</div>
            <div class="rkpi-sub"><?= (int)($kpi['grants_as_pi'] ?? 0) ?> as Principal Investigator</div>
        </div>
        <div class="rkpi" style="--accent:#0d9488">
            <div class="rkpi-top"><i class="fas fa-chart-line"></i><span class="rkpi-num"><?= (int)($kpi['current_hindex'] ?? 0) ?></span></div>
            <div class="rkpi-label">H-Index (Scopus)</div>
            <div class="rkpi-sub"><?= number_format((int)($kpi['total_citations'] ?? 0)) ?> citations</div>
        </div>
        <div class="rkpi" style="--accent:#16a34a">
            <div class="rkpi-top"><i class="fas fa-dollar-sign"></i><span class="rkpi-num">RM <?= number_format((float)($kpi['total_income_rm'] ?? 0) / 1000, 0) ?>K</span></div>
            <div class="rkpi-label">Research Income</div>
            <div class="rkpi-sub">Total approved funding</div>
        </div>
    </div>

    <!-- ══ TYPE TABS (screen only) — pick a type, then Print ══ -->
    <div class="rtype-tabs no-print">
        <button class="rtab active" data-rt="publications" onclick="showRType('publications',this)"><i class="fas fa-file-alt"></i> Publications</button>
        <button class="rtab" data-rt="grants" onclick="showRType('grants',this)"><i class="fas fa-trophy"></i> Grants</button>
        <button class="rtab" data-rt="hindex" onclick="showRType('hindex',this)"><i class="fas fa-chart-line"></i> H-Index</button>
        <button class="rtab" data-rt="income" onclick="showRType('income',this)"><i class="fas fa-dollar-sign"></i> Income</button>
        <button class="rtab" data-rt="awards" onclick="showRType('awards',this)"><i class="fas fa-medal"></i> Awards</button>
    </div>

    <!-- ════════ PUBLICATIONS SECTION ════════ -->
    <div class="rtype-section" data-rt="publications">
        <div class="card" style="margin-bottom:1rem">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--blue)"></i> Publications by Year &amp; Quartile</div>
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;align-items:center;padding-top:8px">
                <div style="flex:1;min-width:260px"><?= svgBar($pubByYear, '#2563eb') ?></div>
                <div><?= svgDonut($pubDonut) ?></div>
            </div>
        </div>

    <!-- Publications -->
    <?php if (!empty($pubs)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-file-alt" style="color:var(--blue)"></i>
            Publications (<?= count($pubs) ?>)
        </div>
        <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:600px">
            <thead>
                <tr><th>#</th><th>Title</th><th>Type</th><th>Indexing</th>
                    <th>Quartile</th><th>Year</th><th>Journal</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pubs as $i => $p): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;max-width:280px">
                    <?= htmlspecialchars(substr($p['title'], 0, 90)) ?><?= strlen($p['title']) > 90 ? '…' : '' ?>
                    <?php if ($p['doi']): ?>
                    <div style="font-size:10px;color:var(--teal)">DOI: <?= htmlspecialchars($p['doi']) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($p['pub_type']) ?></span></td>
                <td><span class="badge badge-grey" style="font-size:10px"><?= htmlspecialchars($p['indexing_type']) ?></span></td>
                <td>
                    <span class="badge <?= $p['quartile']==='Q1'?'badge-blue':($p['quartile']==='Q2'?'badge-teal':'badge-grey') ?>"
                          style="font-size:10px"><?= $p['quartile'] ?></span>
                </td>
                <td style="font-weight:600"><?= $p['pub_year'] ?></td>
                <td style="font-size:11px;color:var(--muted)">
                    <?= htmlspecialchars(substr($p['journal_name'] ?? '', 0, 40)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /publications -->

    <!-- ════════ GRANTS SECTION ════════ -->
    <div class="rtype-section" data-rt="grants" style="display:none">
        <div class="card" style="margin-bottom:1rem">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:#8b5cf6"></i> Grants by Year &amp; Status (Active vs Non-Active)</div>
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;align-items:center;padding-top:8px">
                <div style="flex:1;min-width:260px"><?= svgBar($grantByYear, '#8b5cf6') ?></div>
                <div><?= svgDonut($grantDonut) ?></div>
            </div>
        </div>


    <!-- Grants -->
    <?php if (!empty($grants)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-trophy" style="color:#8b5cf6"></i>
            Grants (<?= count($grants) ?>)
        </div>
        <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:560px">
            <thead>
                <tr><th>#</th><th>Grant Title</th><th>Code</th>
                    <th>Category</th><th>Role</th><th>Amount (RM)</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($grants as $i => $g): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;max-width:220px">
                    <?= htmlspecialchars(substr($g['grant_title'], 0, 70)) ?><?= strlen($g['grant_title']) > 70 ? '…' : '' ?>
                </td>
                <td style="font-size:11px;font-weight:600"><?= htmlspecialchars($g['grant_code'] ?? '—') ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($g['grant_category'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $g['role']==='PI'?'badge-blue':'badge-grey' ?>"
                          style="font-size:10px"><?= htmlspecialchars($g['role']) ?></span>
                </td>
                <td style="font-weight:600;color:var(--green)">
                    <?= $g['amount'] ? 'RM ' . number_format((float)$g['amount']) : '—' ?>
                </td>
                <td>
                    <span class="badge <?= $g['status']==='Active'?'badge-green':'badge-grey' ?>"
                          style="font-size:10px"><?= $g['status'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /grants -->

    <!-- ════════ H-INDEX SECTION ════════ -->
    <div class="rtype-section" data-rt="hindex" style="display:none">
        <div class="card" style="margin-bottom:1rem">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--teal)"></i> H-Index Value by Year</div>
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;align-items:center;padding-top:8px">
                <div style="flex:1;min-width:260px"><?= svgBar($hindexByYear, '#0d9488') ?></div>
                <div><?= svgDonut($hindexDonut) ?></div>
            </div>
        </div>
    <div style="margin-bottom:1rem">
        <div class="card">
            <div class="card-title">
                <i class="fas fa-chart-line" style="color:var(--teal)"></i>
                H-Index History
            </div>
            <?php if (empty($hindexes)): ?>
            <p style="color:var(--muted);font-size:13px">No records yet.</p>
            <?php else: ?>
            <table class="arams-table">
                <thead><tr><th>Year</th><th>H-Index</th><th>Citations</th><th>Source</th></tr></thead>
                <tbody>
                <?php foreach ($hindexes as $h): ?>
                <tr>
                    <td><?= $h['record_year'] ?></td>
                    <td style="font-weight:700;font-size:16px;color:var(--blue)"><?= $h['hindex_value'] ?></td>
                    <td><?= $h['citation_count'] !== null ? number_format($h['citation_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($h['source']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- /hindex margin -->
    </div><!-- /hindex section -->

    <!-- ════════ INCOME SECTION ════════ -->
    <div class="rtype-section" data-rt="income" style="display:none">
        <div class="card" style="margin-bottom:1rem">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--green)"></i> Research Income (RM'000) by Year</div>
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;align-items:center;padding-top:8px">
                <div style="flex:1;min-width:260px"><?= svgBar($incByYearK, '#16a34a') ?></div>
                <div><?= svgDonut($incomeDonut) ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-title">
                <i class="fas fa-dollar-sign" style="color:var(--green)"></i>
                Research Income
            </div>
            <?php if (empty($incomes)): ?>
            <p style="color:var(--muted);font-size:13px">No records yet.</p>
            <?php else: ?>
            <table class="arams-table">
                <thead><tr><th>Year</th><th>Category</th><th>Amount (RM)</th></tr></thead>
                <tbody>
                <?php foreach ($incomes as $inc): ?>
                <tr>
                    <td><?= $inc['year_received'] ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($inc['income_category'] ?? $inc['source'] ?? '—') ?></td>
                    <td style="font-weight:600;color:var(--green)">
                        RM <?= number_format((float)($inc['amount'] ?? 0)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════════ AWARDS SECTION ════════ -->
    <div class="rtype-section" data-rt="awards" style="display:none">
        <div class="card" style="margin-bottom:1rem">
            <div class="card-title"><i class="fas fa-chart-bar" style="color:#f59e0b"></i> Awards by Year &amp; Level</div>
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;align-items:center;padding-top:8px">
                <div style="flex:1;min-width:260px"><?= svgBar($awardByYear, '#f59e0b') ?></div>
                <div><?= svgDonut($awardDonut) ?></div>
            </div>
        </div>


    <!-- Awards -->
    <?php if (!empty($awards)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-medal" style="color:#f59e0b"></i>
            Awards & Recognition (<?= count($awards) ?>)
        </div>
        <table class="arams-table">
            <thead><tr><th>#</th><th>Award Name</th><th>Type</th><th>Level</th><th>Year</th><th>Organiser</th></tr></thead>
            <tbody>
            <?php foreach ($awards as $i => $aw): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;font-weight:600"><?= htmlspecialchars($aw['award_name']) ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($aw['award_type'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $aw['level']==='International'?'badge-blue':($aw['level']==='National'?'badge-teal':'badge-grey') ?>"
                          style="font-size:10px"><?= $aw['level'] ?></span>
                </td>
                <td><?= $aw['award_year'] ?></td>
                <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($aw['organiser'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php if (empty($awards)): ?>
    <div class="card" style="margin-bottom:1rem"><p style="color:var(--muted);font-size:13px">No awards recorded.</p></div>
    <?php endif; ?>
    </div><!-- /awards section -->

    <!-- Footer -->
    <div style="text-align:center;font-size:11px;color:var(--muted);
                padding:1rem;border-top:1px solid var(--border);margin-top:1rem">
        Academic Research Analytics and Monitoring System (ARAMS) —
        Universiti Tun Hussein Onn Malaysia &nbsp;|&nbsp;
        Generated on <?= date('d M Y \a\t H:i') ?>
    </div>

</div><!-- #reportContent -->
<?php endif; ?>

<!-- ── PRINT STYLES ───────────────────────────────────────── -->
<style>
@page { size: A4; margin: 12mm; }
@media print {
    * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    .no-print, .sidebar, .topbar, .sidebar-toggle, .btn { display:none !important; }
    html, body { width:auto !important; margin:0 !important; padding:0 !important; overflow:visible !important; }
    .main-wrap, .page-content, #reportContent, .report-letterhead, .rkpi-grid {
        width:100% !important; max-width:100% !important; margin:0 !important; padding:0 !important;
        box-sizing:border-box !important; overflow:visible !important; }
    /* undo mobile table rule so wide tables don't overflow the page */
    .arams-table { display:table !important; overflow:visible !important; width:100% !important; }
    [style*="overflow-x"] { overflow:visible !important; }
    body { font-size:11px; color:#000; }
    .card { box-shadow:none !important; border:1px solid #ddd !important; break-inside:avoid; }
    .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; }
    table { width:100%; border-collapse:collapse; font-size:10px; }
    th { background:#0B3C5D !important; color:#fff !important;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    td, th { padding:4px 6px; border:1px solid #ddd; }
    .arams-table thead th { background:#0B3C5D !important; color:white !important; }
    a  { color:inherit; text-decoration:none; }
    .badge { border:1px solid #ccc; padding:1px 5px; border-radius:10px; font-size:9px; }
    .report-letterhead { border-bottom:3px solid #0B3C5D !important;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .report-letterhead .lh-logo { height:58px; }
    .report-letterhead { gap:12px; }
    .report-letterhead .lh-uni { font-size:14px; }
    .report-letterhead .lh-meta { min-width:auto; font-size:9px; }
    .rkpi-grid { gap:8px; break-inside:avoid; }
    .rkpi { border:1px solid #cbd5e1 !important; padding:10px 12px; }
    .rkpi-num { font-size:22px; }
    .rtype-section > .card:first-child { break-inside:avoid; }
    svg { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>

<!-- ── FACULTY FILTER JS ──────────────────────────────────── -->
<script>
var allLecturerOptions = [];

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('lecturerSelect');
    allLecturerOptions = Array.from(sel.options).map(function(o) {
        return { value: o.value, text: o.text, fac: o.getAttribute('data-fac') };
    });
});

function selectFaculty(btn, facultyId) {
    document.querySelectorAll('.fac-badge').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    var sel = document.getElementById('lecturerSelect');
    var cur = sel.value;
    sel.innerHTML = '';

    allLecturerOptions.forEach(function(opt) {
        if (opt.value === '' || !facultyId || opt.fac === String(facultyId)) {
            var o = new Option(opt.text, opt.value);
            if (opt.fac) o.setAttribute('data-fac', opt.fac);
            sel.appendChild(o);
        }
    });

    sel.value = Array.from(sel.options).some(function(o) {
        return o.value === cur;
    }) ? cur : '';
}

// Switch which type section is shown (and printed)
function showRType(type, btn){
    document.querySelectorAll('.rtype-section').forEach(function(s){
        s.style.display = (s.getAttribute('data-rt') === type) ? '' : 'none';
    });
    document.querySelectorAll('.rtab').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
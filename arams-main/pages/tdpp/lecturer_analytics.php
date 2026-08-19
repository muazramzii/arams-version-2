<?php
// ============================================================
//  ARAMS — TDPP: Personal Analytics for ONE lecturer
//  Security: TDPP may ONLY view lecturers in their own faculty.
//  Drill-down: clicking a chart shows that lecturer's records.
// ============================================================
$pageTitle  = 'Lecturer Analytics';
$activePage = 'lecturers';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// ── Resolve the TDPP's faculty ──────────────────────────────
$tdpp = $db->prepare(
    "SELECT t.faculty_id, f.faculty_code, f.faculty_name
     FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id
     WHERE t.user_id=?"
);
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();

if (!$tdpp) {
    echo '<div class="card" style="padding:2rem;text-align:center;color:#dc2626">
            This page is for TDPP accounts only.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
$facId = (int)$tdpp['faculty_id'];

// ── Resolve requested lecturer + SECURITY CHECK ─────────────
$reqLec = isset($_GET['lecturer_id']) ? (int)$_GET['lecturer_id'] : 0;

$lec = $db->prepare(
    "SELECT l.lecturer_id, l.full_name, l.title, l.position, l.grade,
            l.faculty_id, f.faculty_name
     FROM tbl_lecturer l JOIN tbl_faculty f ON f.faculty_id=l.faculty_id
     WHERE l.lecturer_id=?"
);
$lec->execute([$reqLec]);
$lec = $lec->fetch();

if (!$lec || (int)$lec['faculty_id'] !== $facId) {
    echo '<div class="card" style="padding:2rem;text-align:center">
            <i class="fas fa-lock" style="font-size:32px;color:#dc2626;margin-bottom:10px"></i>
            <h2 style="margin:0 0 6px;font-size:18px">Access Denied</h2>
            <p style="color:var(--muted);font-size:13px;margin:0">
              You can only view analytics for lecturers in your own faculty
              (' . htmlspecialchars($tdpp['faculty_name']) . ').</p>
            <a href="/arams/pages/tdpp/lecturers.php" class="btn btn-primary btn-sm" style="margin-top:12px">
              <i class="fas fa-arrow-left"></i> Back to My Lecturers</a>
          </div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$lecId = (int)$lec['lecturer_id'];

// ── Queries (scoped to this lecturer) ───────────────────────
$kpiRow = $db->prepare(
    "SELECT total_publications AS pubs, total_grants AS grants,
            current_hindex AS hindex, total_citations AS citations
     FROM vw_lecturer_kpi WHERE lecturer_id = ?"
);
$kpiRow->execute([$lecId]); $kpiRow = $kpiRow->fetch();

$pubTrend = $db->prepare(
    "SELECT p.pub_year AS yr, COUNT(*) AS cnt
     FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id=rd.data_id
     WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0 AND p.pub_year >= YEAR(NOW())-5
     GROUP BY p.pub_year ORDER BY p.pub_year"
);
$pubTrend->execute([$lecId]); $pubTrend = $pubTrend->fetchAll();

$quartileDist = $db->prepare(
    "SELECT quartile, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id=rd.data_id
     WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0 GROUP BY quartile"
);
$quartileDist->execute([$lecId]); $quartileDist = $quartileDist->fetchAll();

$pubTypes = $db->prepare(
    "SELECT pub_type, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id=rd.data_id
     WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
     GROUP BY pub_type ORDER BY cnt DESC"
);
$pubTypes->execute([$lecId]); $pubTypes = $pubTypes->fetchAll();

$grantCats = $db->prepare(
    "SELECT grant_category, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id=rd.data_id
     WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
     GROUP BY grant_category ORDER BY cnt DESC"
);
$grantCats->execute([$lecId]); $grantCats = $grantCats->fetchAll();

$grantRoles = $db->prepare(
    "SELECT role, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id=rd.data_id
     WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
     GROUP BY role ORDER BY cnt DESC"
);
$grantRoles->execute([$lecId]); $grantRoles = $grantRoles->fetchAll();

// ── Pre-calculate percentages ───────────────────────────────
$qMap    = array_column($quartileDist, 'cnt', 'quartile');
$pubMax  = max(array_column($pubTrend, 'cnt') ?: [1]);
$typeMax = max(array_column($pubTypes, 'cnt') ?: [1]);

$barPcts = [];  foreach ($pubTrend as $r) $barPcts[]  = round(($r['cnt'] / $pubMax)  * 100);
$typePcts = []; foreach ($pubTypes as $r) $typePcts[] = round(($r['cnt'] / $typeMax) * 100);

$pubTypeColors  = ['#2563eb','#8b5cf6','#14b8a6','#60a5fa','#a78bfa','#5eead4','#94a3b8'];
$grantCatColors = ['#2563eb','#8b5cf6','#14b8a6','#60a5fa','#a78bfa','#5eead4','#94a3b8'];
$grantRoleColors= ['#2563eb','#8b5cf6','#14b8a6'];
?>

<!-- Header -->
<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1><?= htmlspecialchars(($lec['title']?$lec['title'].' ':'').$lec['full_name']) ?></h1>
        <p><?= htmlspecialchars($lec['position'] ?? '') ?>
           <?= $lec['grade'] ? '· '.htmlspecialchars($lec['grade']) : '' ?>
           · <?= htmlspecialchars($lec['faculty_name']) ?></p>
    </div>
    <a href="/arams/pages/tdpp/lecturers.php" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Back to My Lecturers
    </a>
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
            <div class="akpi-num" data-target="<?= (float)($kpiRow['hindex'] ?? 0) ?>" data-dec="0">0</div>
            <div class="akpi-label">H-Index</div>
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
            <?php foreach ($pubTrend as $i => $row): ?>
            <div class="bar-col" style="cursor:pointer" onclick="drillDown('year','<?= $row['yr'] ?>')" title="Click to view <?= $row['yr'] ?> publications">
                <div class="bar-val"><?= $row['cnt'] ?></div>
                <div class="bar" style="height:<?= $barPcts[$i] ?>%;background:linear-gradient(0deg,var(--blue),var(--blue-light))"></div>
                <div class="bar-label"><?= $row['yr'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--teal)"></i> Quartile Distribution</div>
        <div id="quartileDonut"></div>
    </div>
</div>

<!-- Row 2: Pub Type + Grant Category -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:#3b82f6"></i> Publication Types
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(<?= array_sum(array_column($pubTypes,'cnt')) ?> total)</span>
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?><div id="pubTypeDonut"></div><?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:#8b5cf6"></i> Grant Categories
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(<?= array_sum(array_column($grantCats,'cnt')) ?> total)</span>
        </div>
        <?php if (empty($grantCats)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?><div id="grantCatDonut"></div><?php endif; ?>
    </div>
</div>

<!-- Row 3: Pub Breakdown + Grant Role -->
<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-title"><i class="fas fa-layer-group" style="color:var(--blue)"></i> Publication Breakdown</div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: foreach ($pubTypes as $i => $pt): $col = $pubTypeColors[$i % count($pubTypeColors)]; ?>
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('pubtype','<?= addslashes($pt['pub_type']) ?>')" title="Click to view records">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($pt['pub_type']) ?></span>
                </div><strong><?= $pt['cnt'] ?></strong>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="width:<?= $typePcts[$i] ?>%;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card">
        <div class="card-title"><i class="fas fa-user-tag" style="color:#8b5cf6"></i> Grant by Role</div>
        <?php if (empty($grantRoles)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?><div id="grantRoleDonut" style="margin-bottom:1rem"></div>
        <?php $tg = array_sum(array_column($grantRoles,'cnt')) ?: 1;
        foreach ($grantRoles as $i => $gr): $col = $grantRoleColors[$i % count($grantRoleColors)]; $pct = round($gr['cnt']/$tg*100); ?>
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('grantrole','<?= addslashes($gr['role']) ?>')" title="Click to view grants">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($gr['role']) ?></span>
                </div><span><strong><?= $gr['cnt'] ?></strong> <span style="color:var(--muted);font-size:11px">(<?= $pct ?>%)</span></span>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ── Drill-Down Detail Panel ── -->
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
// This lecturer's ID — used to filter API results down to just their records.
var ARAMS_LEC_ID = <?= $lecId ?>;
var ARAMS_LEC_NAME = '<?= addslashes(($lec['title']?$lec['title'].' ':'').$lec['full_name']) ?>';

document.addEventListener('DOMContentLoaded', function () {
    renderDonut('quartileDonut', [
        { label:'Q1', value:<?= (int)($qMap['Q1'] ?? 0) ?>, color:'#1d4ed8' },
        { label:'Q2', value:<?= (int)($qMap['Q2'] ?? 0) ?>, color:'#3b82f6' },
        { label:'Q3', value:<?= (int)($qMap['Q3'] ?? 0) ?>, color:'#60a5fa' },
        { label:'Q4', value:<?= (int)($qMap['Q4'] ?? 0) ?>, color:'#93c5fd' },
        { label:'N/A', value:<?= (int)($qMap['N/A'] ?? 0) ?>, color:'#cbd5e1' },
    ]);
    <?php if (!empty($pubTypes)): ?>
    renderDonut('pubTypeDonut', [
        <?php foreach ($pubTypes as $i => $pt): $col = $pubTypeColors[$i % count($pubTypeColors)]; ?>
        { label:'<?= addslashes($pt['pub_type']) ?>', value:<?= (int)$pt['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>
    <?php if (!empty($grantCats)): ?>
    renderDonut('grantCatDonut', [
        <?php foreach ($grantCats as $i => $gc): $col = $grantCatColors[$i % count($grantCatColors)]; ?>
        { label:'<?= addslashes($gc['grant_category']) ?>', value:<?= (int)$gc['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>
    <?php if (!empty($grantRoles)): ?>
    renderDonut('grantRoleDonut', [
        <?php foreach ($grantRoles as $i => $gr): $col = $grantRoleColors[$i % count($grantRoleColors)]; ?>
        { label:'<?= addslashes($gr['role']) ?>', value:<?= (int)$gr['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    setTimeout(function() {
        ['quartileDonut','pubTypeDonut','grantCatDonut','grantRoleDonut'].forEach(function(id){
            attachDonutHovers(id);
            attachDonutClicks(id);
        });
    }, 300);
});

// ── Donut hover tooltip ────────────────────────────────────────────────
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

// ── Donut legend click → drillDown ─────────────────────────────────────
function attachDonutClicks(donutId) {
    var container = document.getElementById(donutId);
    if (!container) return;
    var map = { quartileDonut:'quartile', pubTypeDonut:'pubtype', grantCatDonut:'grantcat', grantRoleDonut:'grantrole' };
    var filterType = map[donutId]; if (!filterType) return;
    container.querySelectorAll('.legend-item').forEach(function(item){
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

// ── Drill-down: fetch records, filter to this lecturer, show in panel ──
function drillDown(type, value) {
    var panel = document.getElementById('detailPanel');
    var body  = document.getElementById('detailBody');
    document.getElementById('detailTitle').textContent = 'Loading...';
    document.getElementById('detailCount').textContent = '';
    body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">Loading...</td></tr>';
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior:'smooth', block:'start' });

    fetch('/arams/api/analytics_detail.php?type=' + encodeURIComponent(type) + '&value=' + encodeURIComponent(value))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showDrillError(res.message); return; }
            // API returns faculty-wide records for TDPP — filter client-side to just this lecturer.
            var rows = (res.rows || []).filter(function(r){
                return (r.lecturer_name || '').toLowerCase().indexOf(ARAMS_LEC_NAME.replace(/^[A-Za-z\.]+\s+/,'').toLowerCase()) !== -1
                    || (ARAMS_LEC_NAME.toLowerCase().indexOf((r.lecturer_name||'').toLowerCase()) !== -1 && r.lecturer_name);
            });
            renderRecords({
                kind: res.kind,
                title: res.title + ' — ' + ARAMS_LEC_NAME,
                count: rows.length,
                rows: rows
            });
        })
        .catch(function(){ showDrillError('Error loading records.'); });
}

function renderRecords(res) {
    var head = document.getElementById('detailHead');
    var body = document.getElementById('detailBody');
    document.getElementById('detailTitle').innerHTML = '<i class="fas fa-list" style="color:var(--teal)"></i> ' + esc(res.title);
    document.getElementById('detailCount').textContent = '(' + res.count + ' record' + (res.count!=1?'s':'') + ')';

    if (res.count === 0) {
        head.innerHTML = '';
        body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">No records found for this selection.</td></tr>';
        return;
    }

    if (res.kind === 'publication') {
        head.innerHTML = '<tr><th>Title</th><th>Authors</th><th>Journal</th><th>Year</th><th>Quartile</th><th>Indexing</th><th>Status</th></tr>';
        body.innerHTML = res.rows.map(function(r){
            return '<tr>' +
                '<td style="font-weight:600;font-size:12px;max-width:260px">' + esc(r.title) + '</td>' +
                '<td style="font-size:11px;color:var(--muted);max-width:180px">' + esc(r.authors) + '</td>' +
                '<td style="font-size:11px">' + esc(r.journal_name) + '</td>' +
                '<td>' + esc(r.pub_year) + '</td>' +
                '<td><span class="badge badge-blue" style="font-size:10px">' + esc(r.quartile) + '</span></td>' +
                '<td style="font-size:11px">' + esc(r.indexing_type) + '</td>' +
                '<td><span class="badge badge-green" style="font-size:10px">' + esc(r.status) + '</span></td>' +
                '</tr>';
        }).join('');
    } else {
        head.innerHTML = '<tr><th>Grant Title</th><th>Code</th><th>Funder</th><th>Category</th><th>Level</th><th>Role</th><th>Amount</th><th>Status</th></tr>';
        body.innerHTML = res.rows.map(function(r){
            return '<tr>' +
                '<td style="font-weight:600;font-size:12px;max-width:240px">' + esc(r.grant_title) + '</td>' +
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
}

function showDrillError(msg) {
    document.getElementById('detailHead').innerHTML = '';
    document.getElementById('detailBody').innerHTML = '<tr><td style="padding:1rem;color:#dc2626">' + esc(msg || 'No data') + '</td></tr>';
}
function closeDrill() { document.getElementById('detailPanel').style.display = 'none'; }
function esc(s) { if (s===null||s===undefined) return '—'; return String(s).replace(/[&<>"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
// ============================================================
//  ARAMS — Admin Report Generation (Option A: guided layout)
// ============================================================
$pageTitle  = 'Report Generation';
$activePage = 'reports';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Recent reports
$recentReports = $db->query(
    "SELECT r.*, a.name AS admin_name
     FROM tbl_report r JOIN tbl_admin a ON a.admin_id = r.admin_id
     ORDER BY r.date_generated DESC LIMIT 10"
)->fetchAll();

$stats = $db->query("SELECT COUNT(*) AS total FROM tbl_report")->fetchColumn();
$monthCount = $db->query("SELECT COUNT(*) FROM tbl_report WHERE date_generated >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
$lecCount   = $db->query("SELECT COUNT(*) FROM tbl_lecturer")->fetchColumn();

$faculties = $db->query("SELECT faculty_id, faculty_code, faculty_name FROM tbl_faculty ORDER BY faculty_code")->fetchAll();
$allLecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, f.faculty_code
     FROM tbl_lecturer l JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     ORDER BY l.full_name"
)->fetchAll();

// Report types grouped into meaningful sections
$groups = [
    'Overview' => [
        ['id'=>'comprehensive','name'=>'Comprehensive Research Report','desc'=>'Complete overview of all research activities','icon'=>'fa-layer-group','color'=>'#0f6e56','bg'=>'#e1f5ee'],
    ],
    'By research category' => [
        ['id'=>'publications','name'=>'Publications Report','desc'=>'WoS, Scopus, MyCite & journal output','icon'=>'fa-book','color'=>'#185fa5','bg'=>'#e6f1fb'],
        ['id'=>'grants','name'=>'Grants & Funding Report','desc'=>'Grant awards, funding & active grants','icon'=>'fa-coins','color'=>'#854f0b','bg'=>'#faeeda'],
        ['id'=>'awards','name'=>'Awards & IP Report','desc'=>'Intellectual property & recognition','icon'=>'fa-award','color'=>'#993556','bg'=>'#fbeaf0'],
        ['id'=>'hindex','name'=>'H-Index & Citations Report','desc'=>'Citation impact & h-index trends','icon'=>'fa-arrow-trend-up','color'=>'#534ab7','bg'=>'#eeedfe'],
    ],
    'By people & unit' => [
        ['id'=>'faculty','name'=>'Faculty Performance Report','desc'=>'Compare performance across faculties','icon'=>'fa-building-columns','color'=>'#0f6e56','bg'=>'#e1f5ee'],
        ['id'=>'researchgroup','name'=>'Research Group Report','desc'=>'Output & funding by research group','icon'=>'fa-users-rectangle','color'=>'#854f0b','bg'=>'#faeeda'],
        ['id'=>'individual','name'=>'Individual Lecturer Report','desc'=>'Tabular record list for one lecturer','icon'=>'fa-user','color'=>'#185fa5','bg'=>'#e6f1fb'],
        ['id'=>'lecturer','name'=>'Lecturer Performance Report','desc'=>'Visual analytics for one lecturer','icon'=>'fa-chart-pie','color'=>'#993c1d','bg'=>'#faece7'],
    ],
];
?>

<style>
.rg-stepper{display:flex;align-items:center;gap:8px;margin:0 0 1.25rem;flex-wrap:wrap}
.rg-step{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--muted);background:var(--grey);padding:7px 14px;border-radius:8px;transition:.2s}
.rg-step.active{background:#e1f5ee;color:#0f6e56}
.rg-step-num{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#fff;font-size:11px;font-weight:700}
.rg-step.active .rg-step-num{background:#0f6e56;color:#fff}
.rg-step-line{flex:0 0 22px;height:2px;background:var(--border)}
.rg-group-label{font-size:12px;font-weight:700;color:var(--muted);margin:1rem 0 .5rem}
.rg-group-label:first-of-type{margin-top:0}
.report-card .rg-ic{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:8px}
</style>

<div class="page-header">
    <h1>Report Generation</h1>
    <p>Pick a report, set filters, then generate</p>
</div>

<div class="rg-stepper">
    <div class="rg-step active" id="rgStep1"><span class="rg-step-num">1</span> Choose report</div>
    <div class="rg-step-line"></div>
    <div class="rg-step active" id="rgStep2"><span class="rg-step-num">2</span> Set filters</div>
    <div class="rg-step-line"></div>
    <div class="rg-step active" id="rgStep3"><span class="rg-step-num">3</span> Generate</div>
</div>

<div class="grid-2-1">
    <!-- Left: Config -->
    <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Report Type Selection -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-alt" style="color:var(--blue)"></i> Select Report Type</div>
            <?php foreach ($groups as $label => $items): ?>
            <div class="rg-group-label"><?= $label ?></div>
            <div class="report-grid">
                <?php foreach ($items as $t): ?>
                <div class="report-card<?= $t['id']==='comprehensive'?' selected':'' ?>"
                     onclick="selectReport(this, '<?= $t['id'] ?>')">
                    <div class="rg-ic" style="background:<?= $t['bg'] ?>;color:<?= $t['color'] ?>">
                        <i class="fas <?= $t['icon'] ?>"></i>
                    </div>
                    <div class="report-card-name"><?= $t['name'] ?></div>
                    <div class="report-card-desc"><?= $t['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <input type="hidden" id="selectedReportType" value="comprehensive">
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-title"><i class="fas fa-filter" style="color:var(--teal)"></i> Report Filters</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select class="form-control" id="filterYear">
                        <option value="all">All Years</option>
                        <?php for ($y = date('Y'); $y >= 2015; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Faculty</label>
                    <select class="form-control" id="filterFaculty">
                        <option value="all">All Faculties</option>
                        <?php foreach ($faculties as $f): ?>
                        <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_code']) ?> — <?= htmlspecialchars($f['faculty_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" id="lecturerSelectGroup" style="display:none">
                <label class="form-label">Select Lecturer *</label>
                <select class="form-control" id="filterLecturer">
                    <option value="">— Choose a Lecturer —</option>
                    <?php foreach ($allLecturers as $l): ?>
                    <option value="<?= $l['lecturer_id'] ?>">
                        <?= htmlspecialchars($l['full_name']) ?> (<?= htmlspecialchars($l['faculty_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Export Format</label>
                <select class="form-control" id="exportFormat">
                    <option value="Excel">Excel (.xlsx)</option>
                    <option value="PDF">PDF Document</option>
                    <option value="CSV">CSV File</option>
                </select>
            </div>
            <button class="btn btn-teal btn-full" onclick="generateReport()">
                <i class="fas fa-download"></i> Generate &amp; Download Report
            </button>
        </div>
    </div>

    <!-- Right: Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1rem">
        <div class="card" style="background:linear-gradient(135deg,var(--blue),var(--teal));color:#fff;border:none">
            <div style="font-size:28px;margin-bottom:.5rem"><i class="fas fa-calendar-alt"></i></div>
            <h3 style="font-size:15px;margin:0 0 1rem;font-family:inherit">Report Statistics</h3>
            <div style="font-size:13px;display:flex;justify-content:space-between;margin-bottom:6px;opacity:.9">
                <span>Generated this month</span><strong><?= $monthCount ?></strong>
            </div>
            <div style="font-size:13px;display:flex;justify-content:space-between;margin-bottom:6px;opacity:.9">
                <span>Total reports</span><strong><?= $stats ?></strong>
            </div>
            <div style="font-size:13px;display:flex;justify-content:space-between;opacity:.9">
                <span>Total lecturers</span><strong><?= $lecCount ?></strong>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-history" style="color:var(--blue)"></i> Recent Reports</div>
            <?php if (empty($recentReports)): ?>
            <p style="color:var(--muted);font-size:13px">No reports generated yet.</p>
            <?php endif; ?>
            <?php foreach ($recentReports as $r): ?>
            <div style="padding:.75rem;background:var(--grey);border-radius:var(--radius-sm);margin-bottom:8px">
                <div style="display:flex;align-items:flex-start;justify-content:space-between">
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($r['report_type']) ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px">
                            <?= $r['report_year'] ?? 'All years' ?> • <?= date('d M Y', strtotime($r['date_generated'])) ?>
                        </div>
                    </div>
                    <span class="badge <?= $r['format']==='PDF' ? 'badge-red' : ($r['format']==='CSV' ? 'badge-teal' : 'badge-green') ?>"
                          style="font-size:10px;margin-left:6px"><?= $r['format'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius-sm);padding:1rem">
            <h4 style="font-size:13px;color:#1e40af;margin:0 0 5px">Need Help?</h4>
            <p style="font-size:12px;color:#1e40af;margin:0;opacity:.8">Reports use approved data only. Pending and deleted submissions are excluded.</p>
        </div>
    </div>
</div>

<!-- Hidden form for file download -->
<form id="reportForm" method="POST" action="/arams/api/generate_report.php" target="_blank" style="display:none">
    <input type="hidden" name="type"       id="f_type">
    <input type="hidden" name="year"       id="f_year">
    <input type="hidden" name="faculty_id" id="f_fac">
    <input type="hidden" name="format"     id="f_format">
</form>

<script>
function selectReport(el, type) {
    document.querySelectorAll('.report-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedReportType').value = type;
    // Only the visual "Lecturer Performance" report targets one lecturer
    const lecGroup = document.getElementById('lecturerSelectGroup');
    if (lecGroup) lecGroup.style.display = (type === 'lecturer') ? 'block' : 'none';
}

function generateReport() {
    const type   = document.getElementById('selectedReportType').value;
    const year   = document.getElementById('filterYear').value;
    const fac    = document.getElementById('filterFaculty').value;
    const format = document.getElementById('exportFormat').value;
    const btn    = event.target;

    if (type === 'lecturer') {
        const lecId = document.getElementById('filterLecturer').value;
        if (!lecId) { showToast('Please select a lecturer first.', 'error'); return; }
        window.open('/arams/pages/admin/lecturer_report.php?lecturer_id=' + lecId, '_blank');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    document.getElementById('f_type').value   = type;
    document.getElementById('f_year').value   = year;
    document.getElementById('f_fac').value    = fac;
    document.getElementById('f_format').value = format;
    document.getElementById('reportForm').submit();

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> Generate &amp; Download Report';
        location.reload();
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
// ============================================================
//  ARAMS — Lecturer Research Management
// ============================================================
$pageTitle  = 'Research Management';
$activePage = 'research';
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)$user['lecturer_id'];
$tab   = $_GET['tab'] ?? 'publications';

// Fetch all records per type
$pubs = $db->prepare(
    "SELECT p.*, rd.status, rd.submission_date, rd.remarks
     FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY p.pub_year DESC, rd.submission_date DESC"
);
$pubs->execute([$lecId]); $publications = $pubs->fetchAll();

$grts = $db->prepare(
    "SELECT g.*, rd.status, rd.submission_date, rd.remarks
     FROM tbl_grant g JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY g.start_date DESC"
);
$grts->execute([$lecId]); $grants = $grts->fetchAll();

$hidx = $db->prepare(
    "SELECT h.*, rd.status, rd.submission_date
     FROM tbl_hindex h JOIN tbl_research_data rd ON h.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY h.record_year DESC"
);
$hidx->execute([$lecId]); $hindexes = $hidx->fetchAll();

$ips = $db->prepare(
    "SELECT ip.*, rd.status, rd.submission_date
     FROM tbl_ip_record ip JOIN tbl_research_data rd ON ip.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY ip.filing_date DESC"
);
$ips->execute([$lecId]); $iprecs = $ips->fetchAll();

$incs = $db->prepare(
    "SELECT inc.*, rd.status, rd.submission_date
     FROM tbl_research_income inc JOIN tbl_research_data rd ON inc.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY inc.year_received DESC"
);
$incs->execute([$lecId]); $incomes = $incs->fetchAll();

$counts = [
    'publications' => count($publications),
    'grants'       => count($grants),
    'hindex'       => count($hindexes),
    'ip'           => count($iprecs),
    'income'       => count($incomes),
];

$badgeMap = ['Approved'=>'badge-green','Pending'=>'badge-yellow','Rejected'=>'badge-red'];
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>Research Management</h1>
        <p>Manage all your research submissions</p>
    </div>
    <button class="btn btn-teal" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Record
    </button>
</div>

<!-- Tabs -->
<div class="tabs" id="researchTabs">
    <?php
    $tabs = [
        'publications' => ['icon'=>'fas fa-file-alt',   'label'=>'Publications'],
        'grants'       => ['icon'=>'fas fa-trophy',      'label'=>'Grants'],
        'hindex'       => ['icon'=>'fas fa-chart-line',  'label'=>'H-Index'],
        'ip'           => ['icon'=>'fas fa-lightbulb',   'label'=>'IP Records'],
        'income'       => ['icon'=>'fas fa-dollar-sign', 'label'=>'Income'],
    ];
    foreach ($tabs as $tid => $tinfo): ?>
    <button class="tab-btn <?= $tab === $tid ? 'active' : '' ?>"
            onclick="switchResTab('<?= $tid ?>', this)">
        <i class="<?= $tinfo['icon'] ?>"></i>
        <?= $tinfo['label'] ?>
        <span class="tab-count"><?= $counts[$tid] ?></span>
    </button>
    <?php endforeach; ?>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search records…" oninput="filterActiveTab(this)">
    <select class="filter-select" onchange="filterActiveStatus(this)">
        <option value="">All Status</option>
        <option>Approved</option><option>Pending</option><option>Rejected</option>
    </select>
</div>

<!-- PUBLICATIONS TAB -->
<div class="tab-panel" id="panel-publications" style="<?= $tab==='publications' ? '' : 'display:none' ?>">
    <?php if (empty($publications)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No publications yet. Click "Add Record" to submit your first publication.</div>
    <?php else: ?>
    <?php foreach ($publications as $p): ?>
    <div class="pub-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;flex-wrap:wrap">
                    <span class="badge badge-blue"><?= htmlspecialchars($p['pub_type']) ?></span>
                    <span class="badge badge-teal"><?= htmlspecialchars($p['indexing_type']) ?></span>
                    <?php if ($p['quartile'] !== 'N/A'): ?>
                    <span class="badge badge-purple"><?= $p['quartile'] ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $badgeMap[$p['status']] ?>"><?= $p['status'] ?></span>
                </div>
                <div class="pub-title"><?= htmlspecialchars($p['title']) ?></div>
                <div class="pub-meta">
                    <?= htmlspecialchars($p['journal_name'] ?? '') ?>
                    <?= $p['pub_year'] ? ' • ' . $p['pub_year'] : '' ?>
                    <?= $p['doi'] ? ' • <a href="https://doi.org/' . htmlspecialchars($p['doi']) . '" target="_blank" style="color:var(--teal)">DOI</a>' : '' ?>
                </div>
                <?php if ($p['status'] === 'Rejected' && $p['remarks']): ?>
                <div class="alert alert-danger" style="margin-top:6px;padding:5px 10px;font-size:12px">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($p['remarks']) ?>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- GRANTS TAB -->
<div class="tab-panel" id="panel-grants" style="<?= $tab==='grants' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table" id="resTable">
                <thead><tr>
                    <th>Grant Title</th><th>Funder</th><th>Amount (RM)</th>
                    <th>Role</th><th>Period</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($grants as $g): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($g['grant_title']) ?></div>
                        <?php if ($g['grant_code']): ?>
                        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($g['grant_code']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($g['funder'] ?? '') ?></td>
                    <td><?= $g['amount'] ? number_format((float)$g['amount'],2) : '—' ?></td>
                    <td><span class="badge <?= $g['role']==='PI' ? 'badge-blue' : 'badge-grey' ?>"><?= $g['role'] ?></span></td>
                    <td style="font-size:12px"><?= $g['start_date'] ?? '—' ?><br><?= $g['end_date'] ?? '' ?></td>
                    <td><span class="badge <?= $badgeMap[$g['status']] ?? 'badge-grey' ?>"><?= $g['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($grants)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No grants yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- H-INDEX TAB -->
<div class="tab-panel" id="panel-hindex" style="<?= $tab==='hindex' ? '' : 'display:none' ?>">
    <?php if (!empty($hindexes)): ?>
    <div class="kpi-card bg-uthm" style="margin-bottom:1rem;display:inline-block;min-width:180px">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= $hindexes[0]['hindex_value'] ?></div>
        <div class="kpi-label">Current H-Index (<?= $hindexes[0]['source'] ?>)</div>
        <div class="kpi-chg">As of <?= $hindexes[0]['record_year'] ?></div>
    </div>
    <?php endif; ?>
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>Year</th><th>H-Index</th><th>Citations</th><th>Source</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($hindexes as $h): ?>
                <tr>
                    <td><?= $h['record_year'] ?></td>
                    <td style="font-weight:700;font-size:16px;color:var(--blue)"><?= $h['hindex_value'] ?></td>
                    <td><?= $h['citation_count'] !== null ? number_format($h['citation_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($h['source']) ?></td>
                    <td><span class="badge <?= $badgeMap[$h['status']] ?>"><?= $h['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($hindexes)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No H-Index records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- IP TAB -->
<div class="tab-panel" id="panel-ip" style="<?= $tab==='ip' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>IP Title</th><th>Type</th><th>IP Number</th><th>Country</th><th>Registration</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($iprecs as $ip): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($ip['ip_title']) ?></td>
                    <td><span class="badge badge-orange"><?= htmlspecialchars($ip['ip_type']) ?></span></td>
                    <td><?= htmlspecialchars($ip['ip_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ip['country'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ip['registration_status']) ?></td>
                    <td><span class="badge <?= $badgeMap[$ip['status']] ?>"><?= $ip['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($iprecs)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No IP records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- INCOME TAB -->
<div class="tab-panel" id="panel-income" style="<?= $tab==='income' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>Source</th><th>Category</th><th>Amount (RM)</th><th>Year</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($incomes as $inc): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($inc['source']) ?></td>
                    <td><?= htmlspecialchars($inc['income_category']) ?></td>
                    <td style="font-weight:600;color:var(--green)"><?= number_format((float)$inc['amount'],2) ?></td>
                    <td><?= $inc['year_received'] ?></td>
                    <td><span class="badge <?= $badgeMap[$inc['status']] ?>"><?= $inc['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($incomes)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No income records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($incomes)): ?>
        <div class="stat-row" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
            <div class="stat-item">
                <span>Total Income</span>
                <strong>RM <?= number_format(array_sum(array_column($incomes,'amount')),2) ?></strong>
            </div>
            <div class="stat-item">
                <span>Records</span>
                <strong><?= count($incomes) ?></strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="/arams/assets/js/research_forms.js"></script>
<script>
// Show correct tab panel based on server-rendered initial tab
const panels = document.querySelectorAll('.tab-panel');

let activeResTab = '<?= $tab ?>';

function switchResTab(tabId, btn) {
    activeResTab = tabId;
    document.querySelectorAll('#researchTabs .tab-btn').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    panels.forEach(p => p.style.display = 'none');
    const target = document.getElementById('panel-' + tabId);
    if (target) {
        target.style.display = 'block';
        // replay entrance animation on the newly-shown panel's cards
        target.querySelectorAll('.card, .pub-card, .kpi-card').forEach(function(el){
            el.style.animation = 'none'; void el.offsetWidth; el.style.animation = '';
        });
    }
    // reset search when switching tabs
    const s = document.querySelector('.search-input'); if (s) s.value = '';
    const sel = document.querySelector('.filter-select'); if (sel) sel.value = '';
}

function _activePanel() { return document.getElementById('panel-' + activeResTab); }

function filterActiveTab(input) {
    const q = input.value.toLowerCase();
    const panel = _activePanel(); if (!panel) return;
    const cards = panel.querySelectorAll('.pub-card');
    if (cards.length) {
        cards.forEach(c => { c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none'; });
    } else {
        panel.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
}

function filterActiveStatus(sel) {
    const q = sel.value.toLowerCase();
    const panel = _activePanel(); if (!panel) return;
    const match = el => !q || el.textContent.toLowerCase().includes(q);
    const cards = panel.querySelectorAll('.pub-card');
    if (cards.length) {
        cards.forEach(c => { c.style.display = match(c) ? '' : 'none'; });
    } else {
        panel.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            tr.style.display = match(tr) ? '' : 'none';
        });
    }
}

function openAddModal() {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Add Research Record</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="tabs" style="margin-bottom:1rem" id="addTabs">
            <button class="tab-btn active" onclick="switchAddForm('pub',this)"><i class="fas fa-file-alt"></i> Publication</button>
            <button class="tab-btn" onclick="switchAddForm('grant',this)"><i class="fas fa-trophy"></i> Grant</button>
            <button class="tab-btn" onclick="switchAddForm('hindex',this)"><i class="fas fa-chart-line"></i> H-Index</button>
            <button class="tab-btn" onclick="switchAddForm('ip',this)"><i class="fas fa-lightbulb"></i> IP</button>
            <button class="tab-btn" onclick="switchAddForm('income',this)"><i class="fas fa-dollar-sign"></i> Income</button>
        </div>
        <div id="addFormArea">${pubForm()}</div>
        <button class="btn btn-teal btn-full" style="margin-top:1rem" onclick="submitAddForm()">
            <i class="fas fa-paper-plane"></i> Submit for Validation
        </button>`);
}

let currentFormType = 'pub';
function switchAddForm(type, btn) {
    currentFormType = type;
    document.querySelectorAll('#addTabs .tab-btn').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const forms = { pub:pubForm, grant:grantForm, hindex:hindexForm, ip:ipForm, income:incomeForm };
    document.getElementById('addFormArea').innerHTML = (forms[type] || pubForm)();
}

function submitAddForm() {
    const typeMap = { pub:'publication', grant:'grant', hindex:'hindex', ip:'ip', income:'income' };
    const form = document.getElementById('addForm');
    if (!form || !form.checkValidity()) { form && form.reportValidity(); return; }
    const data = new FormData(form);
    data.append('type', typeMap[currentFormType]);
    fetch('/arams/api/submit_research.php', { method:'POST', body:data })
        .then(r=>r.json())
        .then(res => {
            if (res.success) { showToast(res.message,'success'); closeModal(); setTimeout(()=>location.reload(),1200); }
            else showToast(res.message,'error');
        });
}

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
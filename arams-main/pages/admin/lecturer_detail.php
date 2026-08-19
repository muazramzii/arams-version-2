<?php
// ============================================================
//  ARAMS — Admin: Lecturer Detail View (with charts + inline edit)
// ============================================================
$pageTitle  = 'Lecturer Detail';
$activePage = 'lecturers';
$editMode   = (($_GET['edit'] ?? '') === '1');   // edit page = ?edit=1
$bodyClass  = $editMode ? 'edit-mode' : '';       // applied on <body> by header
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)($_GET['id'] ?? 0);
if (!$lecId) { header('Location: /arams/pages/admin/lecturers.php'); exit; }

// ── Lecturer profile ──────────────────────────────────────
$lec = $db->prepare(
    "SELECT l.*, f.faculty_name, f.faculty_code, u.email
     FROM tbl_lecturer l
     JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     JOIN tbl_user u ON u.user_id = l.user_id
     WHERE l.lecturer_id = ?"
);
$lec->execute([$lecId]); $lec = $lec->fetch();
if (!$lec) { header('Location: /arams/pages/admin/lecturers.php'); exit; }

// ── KPI ───────────────────────────────────────────────────
$kpi = $db->prepare("SELECT * FROM vw_lecturer_kpi WHERE lecturer_id = ?");
$kpi->execute([$lecId]); $k = $kpi->fetch() ?: [];

// ── Publications ──────────────────────────────────────────
$pubs = $db->prepare(
    "SELECT p.*, rd.submission_date FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY p.pub_year DESC"
);
$pubs->execute([$lecId]); $publications = $pubs->fetchAll();

// ── Publication type counts ───────────────────────────────
$pubTypes = $db->prepare(
    "SELECT pub_type, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     GROUP BY pub_type ORDER BY cnt DESC"
);
$pubTypes->execute([$lecId]); $pubTypes = $pubTypes->fetchAll();

// ── Quartile counts ───────────────────────────────────────
$quartiles = $db->prepare(
    "SELECT quartile, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     GROUP BY quartile"
);
$quartiles->execute([$lecId]); $quartiles = $quartiles->fetchAll();
$qMap = array_column($quartiles, 'cnt', 'quartile');

// ── Publication trend by year ─────────────────────────────
$pubTrend = $db->prepare(
    "SELECT p.pub_year AS yr, COUNT(*) AS cnt FROM tbl_publication p
     JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
       AND p.pub_year >= YEAR(NOW()) - 5
     GROUP BY p.pub_year ORDER BY p.pub_year"
);
$pubTrend->execute([$lecId]); $pubTrend = $pubTrend->fetchAll();

// ── Grants ────────────────────────────────────────────────
$grants = $db->prepare(
    "SELECT g.*, rd.status FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY g.start_date DESC"
);
$grants->execute([$lecId]); $grants = $grants->fetchAll();

// ── Grant category counts ─────────────────────────────────
$grantCats = $db->prepare(
    "SELECT grant_category, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     GROUP BY grant_category ORDER BY cnt DESC"
);
$grantCats->execute([$lecId]); $grantCats = $grantCats->fetchAll();

// ── Grant role counts ─────────────────────────────────────
$grantRoles = $db->prepare(
    "SELECT role, COUNT(*) AS cnt FROM tbl_grant g
     JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     GROUP BY role ORDER BY cnt DESC"
);
$grantRoles->execute([$lecId]); $grantRoles = $grantRoles->fetchAll();

// ── Awards ────────────────────────────────────────────────
$awards = $db->prepare(
    "SELECT * FROM tbl_award WHERE lecturer_id = ? ORDER BY award_year DESC"
);
$awards->execute([$lecId]); $awards = $awards->fetchAll();

// ── IP Records ────────────────────────────────────────────
$ips = $db->prepare(
    "SELECT i.*, rd.status FROM tbl_ip_record i
     JOIN tbl_research_data rd ON i.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY i.filing_date DESC"
);
$ips->execute([$lecId]); $ips = $ips->fetchAll();

// ── H-Index history ───────────────────────────────────────
$hindexes = $db->prepare(
    "SELECT h.* FROM tbl_hindex h
     JOIN tbl_research_data rd ON h.data_id = rd.data_id
     WHERE rd.lecturer_id = ? AND rd.status = 'Approved' AND rd.is_deleted=0
     ORDER BY h.record_year DESC"
);
$hindexes->execute([$lecId]); $hindexes = $hindexes->fetchAll();

// ── Pre-calculate percentages ─────────────────────────────
$pubMax  = max(array_column($pubTrend, 'cnt') ?: [1]);
$typeMax = max(array_column($pubTypes, 'cnt') ?: [1]);
$barPcts  = [];
foreach ($pubTrend as $r) $barPcts[]  = round(($r['cnt'] / $pubMax)  * 100);
$typePcts = [];
foreach ($pubTypes as $r) $typePcts[] = round(($r['cnt'] / $typeMax) * 100);

// ── Colour palettes ───────────────────────────────────────
$pubTypeColors   = ['#0B3C5D','#1B998B','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#22c55e','#ec4899'];
$grantCatColors  = ['#0B3C5D','#1B998B','#3b82f6','#f59e0b','#8b5cf6','#ef4444','#22c55e'];
$grantRoleColors = ['#0B3C5D','#1B998B','#f59e0b'];

// ── Allowed values for the profile-fields editor ─────────
$RGC_OPTS    = ['','CoE','CoR','Focus Group'];

// ── Photo ─────────────────────────────────────────────────
$photo    = $lec['profile_photo'] ?? '';
$photoUrl = ($photo && file_exists(__DIR__ . '/../../assets/images/profiles/' . $photo))
            ? '/arams/assets/images/profiles/' . htmlspecialchars($photo)
            : '';
$initials = strtoupper(substr($lec['full_name'], 0, 2));
?>

<!-- ── ADMIN INLINE EDIT STYLES ──────────────────────── -->
<style>
.edit-only,.edit-only-block{display:none}
.edit-row.edit-only{display:none}
body.edit-mode .edit-only{display:inline-block}
body.edit-mode .edit-row.edit-only{display:flex}
body.edit-mode .edit-only-block{display:block}
body.edit-mode .view-only{display:none}
/* edit-mode redesign */
body.edit-mode .editable-section{border-left:3px solid #ef9f27}
.edit-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#faeeda;border:1px solid #f0c77b;border-radius:12px;padding:12px 16px;margin-bottom:1rem}
.edit-bar .eb-title{font-weight:700;font-size:15px;color:#7a4f0a;display:flex;align-items:center;gap:8px}
.edit-bar .eb-sub{font-size:12px;color:#946312;margin-top:2px}
.add-chip{cursor:pointer;border:1px solid var(--border);background:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;color:var(--text);display:inline-flex;align-items:center;gap:5px}
.add-chip:hover{background:var(--grey);border-color:var(--teal);color:var(--teal-dark,#0f6e56)}
.inline-select{padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:#fff;max-width:100%}
.inline-input{padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:140px}
.save-pill{cursor:pointer;background:var(--blue);color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600}
.save-pill:hover{opacity:.9}
.save-pill:disabled{opacity:.6;cursor:default}
.saved-tick{color:var(--green);font-weight:700;font-size:12px;margin-left:6px;display:none}
.edit-row{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:6px}
</style>

<!-- Back button + Edit toggle -->
<div style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
    <a href="/arams/pages/admin/lecturers.php" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to All Lecturers
    </a>
    <a href="/arams/pages/admin/lecturer_report.php?lecturer_id=<?= $lecId ?>"
       class="btn btn-primary btn-sm">
        <i class="fas fa-file-alt"></i> Generate Performance Report
    </a>
    <?php if (!$editMode): ?>
    <a href="/arams/pages/admin/lecturer_detail.php?id=<?= $lecId ?>&edit=1" class="btn btn-sm" style="background:var(--teal);color:#fff">
        <i class="fas fa-pen"></i> Edit Mode
    </a>
    <?php endif; ?>
</div>

<?php if ($editMode): ?>
<!-- ── Edit Mode command bar ─────────────────────────── -->
<div class="edit-bar">
    <div>
        <div class="eb-title"><i class="fas fa-pen"></i> Editing <?= htmlspecialchars($lec['full_name']) ?></div>
        <div class="eb-sub">You're in edit mode — add, edit or remove records. Changes save as you make them.</div>
    </div>
    <a href="/arams/pages/admin/lecturer_detail.php?id=<?= $lecId ?>" class="btn btn-sm" style="background:#64748b;color:#fff">
        <i class="fas fa-check"></i> Done Editing
    </a>
</div>
<?php endif; ?>

<!-- ── PROFILE HEADER ─────────────────────────────────── -->
<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">

        <!-- Avatar / Photo -->
        <?php if ($photoUrl): ?>
        <img src="<?= $photoUrl ?>"
             style="width:76px;height:76px;border-radius:50%;object-fit:cover;
                    border:3px solid var(--teal);flex-shrink:0">
        <?php else: ?>
        <div style="width:76px;height:76px;border-radius:50%;
                    background:linear-gradient(135deg,var(--blue),var(--teal));
                    display:flex;align-items:center;justify-content:center;
                    font-size:26px;font-weight:700;color:#fff;flex-shrink:0">
            <?= $initials ?>
        </div>
        <?php endif; ?>

        <div style="flex:1">
            <h2 style="font-size:20px;margin:0 0 4px"><?= htmlspecialchars($lec['full_name']) ?></h2>
            <p style="font-size:13px;color:var(--muted);margin:0 0 6px">
                <?= htmlspecialchars($lec['position'] ?? 'Lecturer') ?> •
                <?= htmlspecialchars($lec['department'] ?? $lec['faculty_name']) ?>
            </p>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
                <span class="badge badge-green">Active</span>
                <span class="badge badge-grey"><?= htmlspecialchars($lec['faculty_code']) ?></span>
                <?php if ($lec['grade']): ?>
                <span class="badge badge-grey"><?= htmlspecialchars($lec['grade']) ?></span>
                <?php endif; ?>
                <?php if ($lec['research_centre']): ?>
                <span class="badge badge-teal"><?= htmlspecialchars($lec['research_centre']) ?></span>
                <?php endif; ?>
                <?php if (!empty($lec['managerial_position'])): ?>
                <span class="badge badge-purple">Managerial</span>
                <?php endif; ?>
            </div>
            <!-- Research IDs -->
            <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:12px">
                <?php if ($lec['scopus_id']): ?>
                <a href="https://www.scopus.com/authid/detail.uri?authorId=<?= htmlspecialchars($lec['scopus_id']) ?>"
                   target="_blank" style="color:var(--teal)">
                    <i class="fas fa-external-link-alt" style="font-size:10px"></i>
                    Scopus: <?= htmlspecialchars($lec['scopus_id']) ?>
                </a>
                <?php endif; ?>
                <?php if ($lec['orcid_id']): ?>
                <a href="https://orcid.org/<?= htmlspecialchars($lec['orcid_id']) ?>"
                   target="_blank" style="color:var(--teal)">
                    <i class="fas fa-external-link-alt" style="font-size:10px"></i>
                    ORCID: <?= htmlspecialchars($lec['orcid_id']) ?>
                </a>
                <?php endif; ?>
                <?php if ($lec['lens_id']): ?>
                <a href="https://www.lens.org/lens/profile/<?= htmlspecialchars($lec['lens_id']) ?>"
                   target="_blank" style="color:var(--teal)">
                    <i class="fas fa-external-link-alt" style="font-size:10px"></i>
                    Lens: <?= htmlspecialchars($lec['lens_id']) ?>
                </a>
                <?php endif; ?>
                <?php if ($lec['researcher_id']): ?>
                <a href="https://www.webofscience.com/wos/author/record/<?= htmlspecialchars($lec['researcher_id']) ?>"
                   target="_blank" style="color:var(--teal)">
                    <i class="fas fa-external-link-alt" style="font-size:10px"></i>
                    ResearcherID: <?= htmlspecialchars($lec['researcher_id']) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align:right;font-size:13px">
            <div style="color:var(--muted);font-size:11px">Email</div>
            <div style="font-weight:500;margin-bottom:6px">
                <a href="mailto:<?= htmlspecialchars($lec['email'] ?? '') ?>"
                   style="color:var(--teal)">
                    <?= htmlspecialchars($lec['email'] ?? '—') ?>
                </a>
            </div>
            <div style="color:var(--muted);font-size:11px">Staff No</div>
            <div style="font-weight:600"><?= htmlspecialchars($lec['staff_no']) ?></div>
        </div>
    </div>
</div>

<!-- ── EDIT PANEL: Profile Fields (admin only) ────────── -->
<div class="card edit-only-block" id="profileEditPanel"
     style="margin-bottom:1rem;border:2px dashed var(--teal)">
    <div class="card-title"><i class="fas fa-pen" style="color:var(--teal)"></i> Edit Profile Fields</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <label style="font-size:12px">Research Centre
            <input id="ef_rc" class="inline-input" style="width:100%"
                   value="<?= htmlspecialchars($lec['research_centre'] ?? '') ?>">
        </label>
        <label style="font-size:12px">Research Group Category
            <select id="ef_rgc" class="inline-select" style="width:100%">
                <?php $cur = $lec['research_group_category'] ?? '';
                $opts = $RGC_OPTS;
                if ($cur && !in_array($cur, $opts, true)) $opts[] = $cur;
                foreach ($opts as $o): ?>
                <option value="<?= htmlspecialchars($o) ?>" <?= $o === $cur ? 'selected' : '' ?>>
                    <?= $o === '' ? '— none —' : htmlspecialchars($o) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="font-size:12px">Status Researcher
            <input id="ef_sr" class="inline-input" style="width:100%"
                   value="<?= htmlspecialchars($lec['status_researcher'] ?? '') ?>">
        </label>
        <label style="font-size:12px;display:flex;align-items:center;gap:8px;margin-top:18px">
            <input type="checkbox" id="ef_mp" <?= !empty($lec['managerial_position']) ? 'checked' : '' ?>>
            Holds Managerial Position
        </label>
    </div>
    <div style="margin-top:1rem">
        <button class="save-pill" onclick="saveProfile()">Save Profile</button>
        <span class="saved-tick" id="tick_profile">✓ Saved</span>
    </div>
</div>

<!-- ── KPI CARDS ─────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1rem">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= (int)($k['total_publications'] ?? 0) ?></div>
        <div class="kpi-label">Publications</div>
        <div class="kpi-chg">Q1: <?= (int)($k['q1_pubs'] ?? 0) ?> &nbsp;Q2: <?= (int)($k['q2_pubs'] ?? 0) ?></div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= (int)($k['total_grants'] ?? 0) ?></div>
        <div class="kpi-label">Grants</div>
        <div class="kpi-chg"><?= (int)($k['grants_as_pi'] ?? 0) ?> as PI</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= (int)($k['current_hindex'] ?? 0) ?></div>
        <div class="kpi-label">H-Index (Scopus)</div>
        <div class="kpi-chg">Citations: <?= number_format((int)($k['total_citations'] ?? 0)) ?></div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-dollar-sign"></i>
        <div class="kpi-val">RM <?= number_format((float)($k['total_income_rm'] ?? 0) / 1000, 0) ?>K</div>
        <div class="kpi-label">Research Income</div>
        <div class="kpi-chg">IP: <?= (int)($k['total_ip'] ?? 0) ?> records</div>
    </div>
</div>

<!-- ── ROW 1: Publication Trend + Quartile Donut ────── -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Trend Chart -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-bar" style="color:var(--blue)"></i>
            Publications by Year
        </div>
        <?php if (empty($pubTrend)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">
            No approved publications yet.
        </p>
        <?php else: ?>
        <div class="bar-chart" style="height:140px">
            <?php foreach ($pubTrend as $i => $row):
                $bs = 'height:' . $barPcts[$i] . '%;background:linear-gradient(0deg,var(--blue),var(--blue-light))';
            ?>
            <div class="bar-col">
                <div class="bar-val"><?= $row['cnt'] ?></div>
                <div class="bar" style="<?= $bs ?>"></div>
                <div class="bar-label"><?= $row['yr'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:6px">
            Approved publications per year
        </div>
        <?php endif; ?>
    </div>

    <!-- Quartile Donut -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:var(--teal)"></i>
            Quartile Distribution
        </div>
        <div id="quartileDonut"></div>
    </div>
</div>

<!-- ── ROW 2: Publication Type Donut + Grant Category Donut ── -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publication Type Donut -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#3b82f6"></i>
            Publication Types
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">
                (<?= array_sum(array_column($pubTypes,'cnt')) ?> total)
            </span>
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:1.5rem 0">No data yet.</p>
        <?php else: ?>
        <div id="pubTypeDonut"></div>
        <?php endif; ?>
    </div>

    <!-- Grant Category Donut -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#8b5cf6"></i>
            Grant Categories
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">
                (<?= array_sum(array_column($grantCats,'cnt')) ?> total)
            </span>
        </div>
        <?php if (empty($grantCats)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:1.5rem 0">No data yet.</p>
        <?php else: ?>
        <div id="grantCatDonut"></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── ROW 3: Publication Breakdown + Grant Role ─────── -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publication Breakdown Bars -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-layer-group" style="color:var(--blue)"></i>
            Publication Breakdown
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <?php foreach ($pubTypes as $i => $pt):
            $col = $pubTypeColors[$i % count($pubTypeColors)];
            $ws  = 'width:' . $typePcts[$i] . '%';
        ?>
        <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($pt['pub_type']) ?></span>
                </div>
                <strong><?= $pt['cnt'] ?></strong>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="<?= $ws ?>;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Grant Role Donut + Bars -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-user-tag" style="color:#8b5cf6"></i>
            Grant by Role (PI / Co-I / Member)
        </div>
        <?php if (empty($grantRoles)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <div id="grantRoleDonut" style="margin-bottom:1rem"></div>
        <?php
        $totalGR = array_sum(array_column($grantRoles,'cnt')) ?: 1;
        foreach ($grantRoles as $i => $gr):
            $col = $grantRoleColors[$i % count($grantRoleColors)];
            $pct = round($gr['cnt'] / $totalGR * 100);
            $ws  = 'width:' . $pct . '%';
        ?>
        <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($gr['role']) ?></span>
                </div>
                <span>
                    <strong><?= $gr['cnt'] ?></strong>
                    <span style="color:var(--muted);font-size:11px">(<?= $pct ?>%)</span>
                </span>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="<?= $ws ?>;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Admin: Add income record (income has no list section) ────── -->
<div class="card edit-only-block editable-section" style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
            <div style="font-weight:600;font-size:14px"><i class="fas fa-dollar-sign" style="color:var(--green,#16a34a)"></i> Research income</div>
            <div style="font-size:12px;color:var(--muted)">Income records don't have a list here yet. Add one — it's validated and recorded in the audit trail.</div>
        </div>
        <button class="add-chip" onclick="openAddRecordModal('income')"><i class="fas fa-plus"></i> Add income</button>
    </div>
</div>

<!-- ── ROW 4: Publications List + Grants + Awards ────── -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publications List -->
    <div class="card editable-section">
        <div class="card-title" style="justify-content:space-between">
            <span><i class="fas fa-file-alt" style="color:var(--blue)"></i> Publications (<?= count($publications) ?>)</span>
            <button class="add-chip edit-only" onclick="openAddRecordModal('publication')"><i class="fas fa-plus"></i> Add</button>
        </div>
        <?php if (empty($publications)): ?>
        <p style="color:var(--muted);font-size:13px">No approved publications.</p>
        <?php else: ?>
        <?php foreach (array_slice($publications, 0, 8) as $p): ?>
        <div class="pub-card">
            <div style="display:flex;gap:6px;margin-bottom:4px;flex-wrap:wrap">
                <span class="view-only badge badge-blue"><?= htmlspecialchars($p['pub_type']) ?></span>
                <span class="badge badge-teal"><?= htmlspecialchars($p['indexing_type']) ?></span>
                <?php if ($p['quartile'] !== 'N/A'): ?>
                <span class="badge badge-purple"><?= $p['quartile'] ?></span>
                <?php endif; ?>
            </div>
            <div class="pub-title" style="font-size:13px">
                <?= htmlspecialchars(substr($p['title'], 0, 85)) ?><?= strlen($p['title']) > 85 ? '…' : '' ?>
            </div>
            <div class="pub-meta">
                <?= htmlspecialchars($p['journal_name'] ?? '') ?>
                <?= $p['pub_year'] ? ' • ' . $p['pub_year'] : '' ?>
                <?php if ($p['doi']): ?>
                • <a href="https://doi.org/<?= htmlspecialchars($p['doi']) ?>"
                     target="_blank" style="color:var(--teal)">DOI ↗</a>
                <?php endif; ?>
            </div>
            <div class="edit-row edit-only">
                <button class="save-pill" onclick="editRecord('publication',<?= $p['data_id'] ?>)"><i class="fas fa-pen"></i> Edit</button>
                <button class="save-pill" style="background:#dc2626" onclick="deleteRecord(<?= $p['data_id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($publications) > 8): ?>
        <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:.5rem">
            + <?= count($publications) - 8 ?> more publications
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Grants + H-Index + Awards -->
    <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Grants -->
        <div class="card editable-section">
            <div class="card-title" style="justify-content:space-between">
                <span><i class="fas fa-trophy" style="color:#8b5cf6"></i> Grants (<?= count($grants) ?>)</span>
                <button class="add-chip edit-only" onclick="openAddRecordModal('grant')"><i class="fas fa-plus"></i> Add</button>
            </div>
            <?php if (empty($grants)): ?>
            <p style="color:var(--muted);font-size:13px">No grants yet.</p>
            <?php else: ?>
            <?php foreach ($grants as $g): ?>
            <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
                <div style="font-weight:600;margin-bottom:2px">
                    <?= htmlspecialchars(substr($g['grant_title'], 0, 60)) ?><?= strlen($g['grant_title']) > 60 ? '…' : '' ?>
                </div>
                <div style="font-size:12px;color:var(--muted);display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                    <span><?= htmlspecialchars($g['grant_category']) ?></span>
                    <span class="badge <?= $g['role']==='PI' ? 'badge-blue' : 'badge-grey' ?>"
                          style="font-size:10px"><?= $g['role'] ?></span>
                    <?php if ($g['amount']): ?>
                    <span style="color:var(--green);font-weight:600">RM <?= number_format((float)$g['amount']) ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $g['status']==='Active' ? 'badge-green' : 'badge-grey' ?>"
                          style="font-size:10px"><?= $g['status'] ?></span>
                </div>
                <!-- inline edit: funder / grant_level / grant_category -->
                <div class="edit-row edit-only">
                    <button class="save-pill" onclick="editRecord('grant',<?= $g['data_id'] ?>)"><i class="fas fa-pen"></i> Edit</button>
                    <button class="save-pill" style="background:#dc2626" onclick="deleteRecord(<?= $g['data_id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- H-Index History -->
        <div class="card editable-section">
            <div class="card-title" style="justify-content:space-between">
                <span><i class="fas fa-chart-line" style="color:var(--teal)"></i> H-Index History</span>
                <button class="add-chip edit-only" onclick="openAddRecordModal('hindex')"><i class="fas fa-plus"></i> Add</button>
            </div>
            <?php if (empty($hindexes)): ?>
            <p style="color:var(--muted);font-size:13px">No h-index records yet.</p>
            <?php else: ?>
            <table class="arams-table">
                <thead><tr><th>Year</th><th>H-Index</th><th>Citations</th><th>Source</th></tr></thead>
                <tbody>
                <?php foreach ($hindexes as $h): ?>
                <tr>
                    <td><?= $h['record_year'] ?></td>
                    <td style="font-weight:700;color:var(--blue);font-size:16px"><?= $h['hindex_value'] ?></td>
                    <td><?= $h['citation_count'] !== null ? number_format($h['citation_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($h['source']) ?>
                        <button class="save-pill edit-only" style="margin-left:6px" onclick="editRecord('hindex',<?= $h['data_id'] ?>)">Edit</button>
                        <button class="save-pill edit-only" style="background:#dc2626;margin-left:6px" onclick="deleteRecord(<?= $h['data_id'] ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Awards -->
        <?php if (!empty($awards)): ?>
        <div class="card">
            <div class="card-title">
                <i class="fas fa-medal" style="color:#f59e0b"></i>
                Awards (<?= count($awards) ?>)
            </div>
            <?php foreach ($awards as $aw): ?>
            <div style="padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
                <div style="font-weight:600"><?= htmlspecialchars($aw['award_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:2px">
                    <?php if ($aw['award_type']): ?>
                    <span style="background:#fef9c3;color:#854d0e;padding:1px 7px;border-radius:10px;font-size:11px">
                        <?= htmlspecialchars($aw['award_type']) ?>
                    </span>
                    <?php endif; ?>
                    <span><?= $aw['award_year'] ?></span>
                    <span class="badge <?= $aw['level']==='International' ? 'badge-blue' : ($aw['level']==='National' ? 'badge-teal' : 'badge-grey') ?>"
                          style="font-size:10px"><?= $aw['level'] ?></span>
                    <?php if ($aw['organiser']): ?>
                    <span style="color:var(--muted)"><?= htmlspecialchars($aw['organiser']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── IP RECORDS ─────────────────────────────────────── -->
<div class="card editable-section" style="margin-bottom:1rem">
    <div class="card-title" style="justify-content:space-between">
        <span><i class="fas fa-lightbulb" style="color:#f59e0b"></i> Intellectual Property (<?= count($ips) ?>)</span>
        <button class="add-chip edit-only" onclick="openAddRecordModal('ip')"><i class="fas fa-plus"></i> Add</button>
    </div>
    <?php if (empty($ips)): ?>
    <p style="color:var(--muted);font-size:13px">No approved IP records.</p>
    <?php else: ?>
    <?php foreach ($ips as $ip): ?>
    <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
        <div style="font-weight:600;margin-bottom:3px">
            <?= htmlspecialchars(substr($ip['ip_title'],0,70)) ?><?= strlen($ip['ip_title'])>70?'…':'' ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12px;color:var(--muted)">
            <span style="font-weight:600">Type of Patent:</span>
            <span class="badge badge-purple"><?= htmlspecialchars($ip['ip_type']) ?></span>
            <?php if ($ip['ip_number']): ?><span><?= htmlspecialchars($ip['ip_number']) ?></span><?php endif; ?>
            <span class="badge badge-green"><?= htmlspecialchars($ip['registration_status']) ?></span>
            <button class="save-pill edit-only" onclick="editRecord('ip',<?= $ip['data_id'] ?>)"><i class="fas fa-pen"></i> Edit</button>
            <button class="save-pill edit-only" style="background:#dc2626" onclick="deleteRecord(<?= $ip['data_id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── CHARTS JS ──────────────────────────────────────── -->
<script src="/arams/assets/js/research_forms.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Quartile Donut
    renderDonut('quartileDonut', [
        { label:'Q1',  value:<?= (int)($qMap['Q1']  ?? 0) ?>, color:'#0B3C5D' },
        { label:'Q2',  value:<?= (int)($qMap['Q2']  ?? 0) ?>, color:'#1B998B' },
        { label:'Q3',  value:<?= (int)($qMap['Q3']  ?? 0) ?>, color:'#3b82f6' },
        { label:'Q4',  value:<?= (int)($qMap['Q4']  ?? 0) ?>, color:'#8b5cf6' },
        { label:'N/A', value:<?= (int)($qMap['N/A'] ?? 0) ?>, color:'#e2e8f0' },
    ]);

    <?php if (!empty($pubTypes)): ?>
    // Publication Type Donut
    renderDonut('pubTypeDonut', [
        <?php foreach ($pubTypes as $i => $pt):
            $col = $pubTypeColors[$i % count($pubTypeColors)];
        ?>
        { label:'<?= addslashes($pt['pub_type']) ?>', value:<?= (int)$pt['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantCats)): ?>
    // Grant Category Donut
    renderDonut('grantCatDonut', [
        <?php foreach ($grantCats as $i => $gc):
            $col = $grantCatColors[$i % count($grantCatColors)];
        ?>
        { label:'<?= addslashes($gc['grant_category']) ?>', value:<?= (int)$gc['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantRoles)): ?>
    // Grant Role Donut
    renderDonut('grantRoleDonut', [
        <?php foreach ($grantRoles as $i => $gr):
            $col = $grantRoleColors[$i % count($grantRoleColors)];
        ?>
        { label:'<?= addslashes($gr['role']) ?>', value:<?= (int)$gr['cnt'] ?>, color:'<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>
});

// ── ADMIN INLINE EDIT LOGIC ─────────────────────────────
const LEC_ID = <?= $lecId ?>;

// ── Record data for pre-filling the edit form ────────────────
const REC = {
    publication: <?= json_encode(array_column($publications, null, 'data_id'), JSON_UNESCAPED_UNICODE) ?>,
    grant:       <?= json_encode(array_column($grants, null, 'data_id'), JSON_UNESCAPED_UNICODE) ?>,
    hindex:      <?= json_encode(array_column($hindexes, null, 'data_id'), JSON_UNESCAPED_UNICODE) ?>,
    ip:          <?= json_encode(array_column($ips, null, 'data_id'), JSON_UNESCAPED_UNICODE) ?>
};

// ── Admin: Full edit of a research record ────────────────────
function editRecord(type, dataId){
    const rec = (REC[type] || {})[dataId];
    if (!rec){ showToast('Record data not found.', 'error'); return; }
    const label = type.charAt(0).toUpperCase() + type.slice(1);
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Edit ${label} Record</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div id="editFormArea"></div>
        <button class="btn btn-teal btn-full" style="margin-top:1rem" onclick="submitEditRecord(this,'${type}',${dataId})">
            <i class="fas fa-save"></i> Save Changes
        </button>`);
    const map = { publication:pubForm, grant:grantForm, hindex:hindexForm, ip:ipForm, income:incomeForm };
    document.getElementById('editFormArea').innerHTML = (map[type] || pubForm)();
    populateForm(rec, type);
}
function populateForm(rec, type){
    const form = document.getElementById('addForm');
    if (!form) return;
    if (type === 'grant'){
        const lvl = form.querySelector('[name="grant_level"]');
        if (lvl && rec.grant_level){ lvl.value = rec.grant_level; cascadeGrantType(rec.grant_level, rec.grant_category); }
        const st = form.querySelector('[name="grant_status"]'); if (st && rec.status) st.value = rec.status;
    }
    Object.keys(rec).forEach(k => {
        const el = form.querySelector('[name="'+k+'"]');
        if (!el) return;
        el.value = (rec[k] === null || rec[k] === undefined) ? '' : String(rec[k]);
    });
}
function submitEditRecord(btn, type, dataId){
    const form = document.getElementById('addForm');
    if (!form || !form.checkValidity()){ form && form.reportValidity(); return; }
    const fd = new FormData(form);
    fd.append('type', type);
    fd.append('data_id', dataId);
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    fetch('/arams/api/admin_update_record.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success){ closeModal(); setTimeout(()=>location.reload(), 1000); }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes'; });
}

// ── Admin: Soft-delete a research record ─────────────────────
function deleteRecord(dataId){
    confirmDialog({
        title: 'Delete this record?',
        message: 'It will be removed from all reports and analytics.<br>This is recoverable by an administrator.',
        confirmText: 'Delete',
        cancelText: 'Cancel',
        danger: true,
        onConfirm: function(){
            const fd = new FormData();
            fd.append('data_id', dataId);
            fetch('/arams/api/admin_delete_record.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    showToast(res.message, res.success ? 'success' : 'error');
                    if (res.success) setTimeout(()=>location.reload(), 900);
                })
                .catch(() => showToast('Network error.', 'error'));
        }
    });
}

// ── Admin: Add Research Record on behalf of this lecturer ────
let _adminFormType = 'publication';
function openAddRecordModal(preType){
    const initial = preType || 'publication';
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Add Research Record</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px" id="adminTypeTabs">
            <button class="btn btn-outline btn-sm" data-ftype="publication" onclick="switchAdminForm('publication',this)">Publication</button>
            <button class="btn btn-outline btn-sm" data-ftype="grant" onclick="switchAdminForm('grant',this)">Grant</button>
            <button class="btn btn-outline btn-sm" data-ftype="hindex" onclick="switchAdminForm('hindex',this)">H-Index</button>
            <button class="btn btn-outline btn-sm" data-ftype="ip" onclick="switchAdminForm('ip',this)">IP</button>
            <button class="btn btn-outline btn-sm" data-ftype="income" onclick="switchAdminForm('income',this)">Income</button>
        </div>
        <div id="adminFormArea"></div>
        <button class="btn btn-teal btn-full" style="margin-top:1rem" onclick="submitAdminRecord(this)">
            <i class="fas fa-save"></i> Add Record (validated)
        </button>`);
    switchAdminForm(initial);
}
function switchAdminForm(type, btn){
    _adminFormType = type;
    const map = { publication:pubForm, grant:grantForm, hindex:hindexForm, ip:ipForm, income:incomeForm };
    document.getElementById('adminFormArea').innerHTML = (map[type] || pubForm)();
    document.querySelectorAll('#adminTypeTabs .btn').forEach(b => b.classList.remove('active'));
    const tab = btn || document.querySelector('#adminTypeTabs .btn[data-ftype="'+type+'"]');
    if (tab) tab.classList.add('active');
}
function submitAdminRecord(btn){
    const form = document.getElementById('addForm');
    if (!form || !form.checkValidity()){ form && form.reportValidity(); return; }
    const fd = new FormData(form);
    fd.append('type', _adminFormType);
    fd.append('lecturer_id', LEC_ID);
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    fetch('/arams/api/admin_add_record.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success){ closeModal(); setTimeout(()=>location.reload(), 1200); }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Add Record (validated)'; });
}
const API = '/arams/api/update_lecturer_admin.php';

async function post(payload, tickId, btn){
    const orig = btn ? btn.textContent : '';
    if(btn){ btn.disabled = true; btn.textContent = 'Saving…'; }
    try{
        const r = await fetch(API, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
        const d = await r.json();
        if(d.success){
            const t = document.getElementById(tickId);
            if(t){ t.style.display='inline'; setTimeout(()=>t.style.display='none', 1800); }
        } else { alert('Error: ' + (d.message || 'failed')); }
    }catch(e){ alert('Request failed: ' + e.message); }
    if(btn){ btn.disabled = false; btn.textContent = orig || 'Save'; }
}
function saveProfile(){
    post({type:'profile', id:LEC_ID,
        research_centre: document.getElementById('ef_rc').value,
        research_group_category: document.getElementById('ef_rgc').value,
        status_researcher: document.getElementById('ef_sr').value,
        managerial_position: document.getElementById('ef_mp').checked
    }, 'tick_profile', document.querySelector('#profileEditPanel .save-pill'));
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
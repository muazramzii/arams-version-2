<?php
$pageTitle  = 'All Lecturers';
$activePage = 'lecturers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/avatar.php';

$db = getDB();
$lecturers = $db->query("SELECT k.*, l.staff_no, l.department, l.research_centre, l.profile_photo
                          FROM vw_lecturer_kpi k
                          JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                          ORDER BY k.total_publications DESC")->fetchAll();
$faculties = $db->query("SELECT faculty_id, faculty_code, faculty_name FROM tbl_faculty ORDER BY faculty_code")->fetchAll();
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>All Lecturers</h1>
        <p>View lecturer accounts and research profiles</p>
    </div>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search by name, faculty…"
           oninput="filterTable(this,'lecTable')">
    <select class="filter-select" onchange="filterBySelect(this,'lecTable',2)">
        <option value="">All Faculties</option>
        <?php foreach ($faculties as $f): ?>
        <option value="<?= htmlspecialchars($f['faculty_code']) ?>"><?= htmlspecialchars($f['faculty_code']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="arams-table" id="lecTable">
            <thead><tr>
                <th>#</th><th>Name</th><th>Faculty</th>
                <th>Pubs</th><th>H-Index</th><th>Grants</th><th>Income (RM)</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lecturers as $i => $l): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <?= lecAvatar($l['profile_photo'] ?? '', $l['full_name']) ?>
                        <div>
                            <div style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($l['staff_no']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-grey"><?= htmlspecialchars($l['faculty_code']) ?></span></td>
                <td>
                    <strong><?= (int)$l['total_publications'] ?></strong>
                    <div style="font-size:11px;color:var(--muted)">Q1: <?= (int)$l['q1_pubs'] ?> Q2: <?= (int)$l['q2_pubs'] ?></div>
                </td>
                <td style="font-weight:700;color:var(--blue)"><?= (int)$l['current_hindex'] ?></td>
                <td><?= (int)$l['total_grants'] ?></td>
                <td style="color:var(--green);font-weight:600">
                    <?= $l['total_income_rm'] ? number_format((float)$l['total_income_rm']) : '—' ?>
                </td>
                <td>
                    <a href="/arams/pages/admin/lecturer_detail.php?id=<?= $l['lecturer_id'] ?>"
                       class="btn btn-outline btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
// ============================================================
//  ARAMS — API: Analytics Drill-Down Detail  (v2: faculty layer)
//  Flow:
//   - Admin clicks a chart segment with NO faculty_id  -> returns
//     a per-faculty SUMMARY (faculty_name + count).      [mode=faculty]
//   - Admin then clicks a faculty (faculty_id supplied)  -> returns
//     the full record list for that faculty.            [mode=records]
//   - TDPP / Lecturer are already scoped to one faculty/self, so they
//     skip straight to the record list.                 [mode=records]
//  Used by both Admin and TDPP analytics pages.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$user = currentUser();
$role = $_SESSION['role'] ?? '';
$db   = getDB();

// ---- Determine scope ---------------------------------------------------
$facId = 0;     // 0 = all faculties (admin)
$lecId = 0;     // 0 = not a single lecturer
if ($role === 'TDPP') {
    $t = $db->prepare("SELECT faculty_id FROM tbl_tdpp WHERE user_id=?");
    $t->execute([$user['user_id']]);
    $facId = (int)$t->fetchColumn();
} elseif ($role === 'Lecturer') {
    $lecId = (int)($user['lecturer_id'] ?? 0);
}

$type    = $_GET['type']  ?? '';   // year | quartile | pubtype | grantcat | grantrole
$value   = $_GET['value'] ?? '';
// NEW: optional faculty drill target (only meaningful for Admin scope)
$drillFac = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;

// ---- Map each chart type to its table + filter column ------------------
//  Centralised so the faculty-summary query and the record query stay
//  perfectly in sync (no duplicated WHERE logic).
$map = [
    'year'      => ['kind'=>'publication','table'=>'tbl_publication','col'=>'pub_year',       'titleFmt'=>'Publications in %s'],
    'quartile'  => ['kind'=>'publication','table'=>'tbl_publication','col'=>'quartile',       'titleFmt'=>'Publications — Quartile %s'],
    'pubtype'   => ['kind'=>'publication','table'=>'tbl_publication','col'=>'pub_type',       'titleFmt'=>'Publications — %s'],
    'grantcat'  => ['kind'=>'grant',      'table'=>'tbl_grant',      'col'=>'grant_category', 'titleFmt'=>'Grants — %s'],
    'grantrole' => ['kind'=>'grant',      'table'=>'tbl_grant',      'col'=>'role',           'titleFmt'=>'Grants — Role: %s'],
    'grantactive' => ['kind'=>'grant',    'table'=>'tbl_grant',      'col'=>'__active__',     'titleFmt'=>'%s Grants'],
];

if (!isset($map[$type])) {
    echo json_encode(['success'=>false,'message'=>'Invalid filter']); exit;
}

$cfg   = $map[$type];
$kind  = $cfg['kind'];
$tbl   = $cfg['table'];
$col   = $cfg['col'];
$alias = ($kind === 'publication') ? 'p' : 'g';
// year is numeric, everything else is a string
$boundValue = ($type === 'year') ? (int)$value : $value;

// Build the filter clause. Most types match a column; grantactive is a
// computed end_date condition (Active = open/future end, else expired).
if ($type === 'grantactive') {
    $filterClause = ($value === 'Active')
        ? "(g.end_date IS NULL OR g.end_date >= CURDATE())"
        : "(g.end_date IS NOT NULL AND g.end_date < CURDATE())";
    $filterParams = [];
} else {
    $filterClause = "{$alias}.{$col} = ?";
    $filterParams = [$boundValue];
}

// ============================================================
//  MODE 1 — Per-faculty summary (Admin only, no faculty chosen yet)
// ============================================================
$wantFacultySummary = ($facId === 0 && $lecId === 0 && $drillFac === 0);

if ($wantFacultySummary) {
    $sql = "SELECT f.faculty_id, f.faculty_code, f.faculty_name, COUNT(*) AS cnt
            FROM {$tbl} {$alias}
            JOIN tbl_research_data rd ON {$alias}.data_id = rd.data_id
            JOIN tbl_lecturer l       ON l.lecturer_id   = rd.lecturer_id
            JOIN tbl_faculty f        ON f.faculty_id    = l.faculty_id
            WHERE rd.status='Approved' AND rd.is_deleted=0 AND {$filterClause}
            GROUP BY f.faculty_id, f.faculty_code, f.faculty_name
            ORDER BY cnt DESC, f.faculty_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($filterParams);
    $facs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($facs as $f) { $total += (int)$f['cnt']; }

    echo json_encode([
        'success'    => true,
        'mode'       => 'faculty',                 // <-- tells JS to render faculty list
        'type'       => $type,
        'value'      => $value,
        'title'      => sprintf($cfg['titleFmt'], $value),
        'kind'       => $kind,
        'total'      => $total,
        'faculties'  => $facs,                     // [{faculty_id, faculty_code, faculty_name, cnt}]
    ]);
    exit;
}

// ============================================================
//  MODE 2 — Record list
//  Scope precedence: lecturer self > TDPP faculty > Admin-chosen faculty
// ============================================================
$scopeSql = '';
$scopeParams = [];
if ($lecId > 0) {
    $scopeSql = " AND rd.lecturer_id = ?";
    $scopeParams = [$lecId];
} elseif ($facId > 0) {
    $scopeSql = " AND l.faculty_id = ?";
    $scopeParams = [$facId];
} elseif ($drillFac > 0) {
    $scopeSql = " AND l.faculty_id = ?";
    $scopeParams = [$drillFac];
}

// Resolve the faculty name for the title (when an Admin drilled into one)
$facName = '';
if ($drillFac > 0) {
    $fn = $db->prepare("SELECT faculty_name FROM tbl_faculty WHERE faculty_id=?");
    $fn->execute([$drillFac]);
    $facName = (string)$fn->fetchColumn();
}

$baseTitle = sprintf($cfg['titleFmt'], $value);
$title = $facName !== '' ? "$baseTitle · $facName" : $baseTitle;

if ($kind === 'publication') {
    $sql = "SELECT l.full_name AS lecturer_name, l.title AS lecturer_title,
                   p.title, p.authors, p.journal_name, p.pub_year, p.pub_type,
                   p.indexing_type, p.quartile, rd.status
            FROM tbl_publication p
            JOIN tbl_research_data rd ON p.data_id=rd.data_id
            JOIN tbl_lecturer l       ON l.lecturer_id=rd.lecturer_id
            WHERE rd.status='Approved' AND rd.is_deleted=0 AND {$filterClause}{$scopeSql}
            ORDER BY l.full_name, p.pub_year DESC, p.title";
} else {
    $sql = "SELECT l.full_name AS lecturer_name, l.title AS lecturer_title,
                   g.grant_title, g.grant_code, g.funder, g.grant_category,
                   g.grant_level, g.role, g.amount, g.status
            FROM tbl_grant g
            JOIN tbl_research_data rd ON g.data_id=rd.data_id
            JOIN tbl_lecturer l       ON l.lecturer_id=rd.lecturer_id
            WHERE rd.status='Approved' AND rd.is_deleted=0 AND {$filterClause}{$scopeSql}
            ORDER BY l.full_name, g.grant_title";
}

$params = array_merge($filterParams, $scopeParams);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'    => true,
    'mode'       => 'records',                     // <-- tells JS to render record list
    'type'       => $type,
    'value'      => $value,
    'faculty_id' => $drillFac,
    'title'      => $title,
    'kind'       => $kind,
    'count'      => count($rows),
    'rows'       => $rows,
]);
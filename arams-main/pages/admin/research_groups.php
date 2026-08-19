<?php
// ============================================================
//  ARAMS — Admin: Lecturers by Research Group (accordion view)
//  A review tool: see who belongs to each research group, who is
//  in an External/Others group, and who is still unassigned.
// ============================================================
$pageTitle  = 'Research Groups';
$activePage = 'researchgroups';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Master research groups (always shown, even when empty)
$groups = $db->query(
    "SELECT rg.group_id, rg.group_code, rg.group_name, f.faculty_code
     FROM tbl_research_group rg
     JOIN tbl_faculty f ON f.faculty_id = rg.faculty_id
     WHERE rg.is_active = 1
     ORDER BY f.faculty_code, rg.group_name"
)->fetchAll();

// All lecturers with their grouping fields
$lecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, l.grade,
            l.research_group_id, l.research_group_category, l.research_centre,
            f.faculty_code
     FROM tbl_lecturer l
     JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     ORDER BY l.full_name"
)->fetchAll();

// Bucket lecturers: master group → external/others (by centre) → unassigned
$byMaster = [];      // group_id  => [lecturers]
$byCentre = [];      // centre    => [lecturers]
$unassigned = [];
$masterIds = array_column($groups, 'group_id');

foreach ($lecturers as $l) {
    if (!empty($l['research_group_id']) && in_array($l['research_group_id'], $masterIds)) {
        $byMaster[$l['research_group_id']][] = $l;
    } elseif (!empty($l['research_centre'])) {
        $byCentre[$l['research_centre']][] = $l;
    } else {
        $unassigned[] = $l;
    }
}
ksort($byCentre);

$totalAssigned = count($lecturers) - count($unassigned);

// Small helper to render a lecturer row
function lecRow(array $l): string {
    $cat = $l['research_group_category'] ?: '—';
    $catColor = $cat === 'FG' ? 'badge-teal' : ($cat === 'External' ? 'badge-blue' : ($cat === 'CoR' ? 'badge-purple' : 'badge-grey'));
    return '<tr>'
        . '<td style="font-weight:600">' . htmlspecialchars($l['full_name']) . '</td>'
        . '<td>' . htmlspecialchars($l['staff_no'] ?: '—') . '</td>'
        . '<td>' . htmlspecialchars($l['faculty_code']) . '</td>'
        . '<td>' . htmlspecialchars($l['grade'] ?: '—') . '</td>'
        . '<td><span class="badge ' . $catColor . '" style="font-size:10px">' . htmlspecialchars($cat) . '</span></td>'
        . '<td><a class="btn btn-outline btn-sm" href="/arams/pages/admin/lecturer_detail.php?id=' . (int)$l['lecturer_id'] . '">View</a></td>'
        . '</tr>';
}

function lecTable(array $lecs): string {
    if (empty($lecs)) {
        return '<p style="color:var(--muted);font-size:13px;padding:.5rem 0">No lecturers in this group yet.</p>';
    }
    $h = '<div class="table-wrap"><table class="arams-table"><thead><tr>'
       . '<th>Lecturer</th><th>Staff No</th><th>Faculty</th><th>Grade</th><th>Category</th><th></th>'
       . '</tr></thead><tbody>';
    foreach ($lecs as $l) $h .= lecRow($l);
    return $h . '</tbody></table></div>';
}

// ── Calculation_FG: staff category breakdown ──────────────────
$catCount = ['FG' => 0, 'CoR' => 0, 'External' => 0, 'Not set' => 0];
foreach ($lecturers as $l) {
    $c = $l['research_group_category'] ?: 'Not set';
    if (!isset($catCount[$c])) $catCount[$c] = 0;
    $catCount[$c]++;
}

// ── Calculation_FG (full): staff grade breakdown per group ────
$gradeOrder = ['DS45','DS51/52','DS53/54','VK06','VK07','VU05','VU06','VU07','VY5'];
$present = [];
foreach ($lecturers as $l) { $g = trim((string)($l['grade'] ?? '')); if ($g !== '') $present[$g] = true; }
$gradeCols = [];
foreach ($gradeOrder as $g) if (isset($present[$g])) { $gradeCols[] = $g; unset($present[$g]); }
foreach (array_keys($present) as $g) $gradeCols[] = $g;   // any non-standard grades last

function gradeRow(array $lecs, array $cols): array {
    $r = array_fill_keys($cols, 0); $t = 0;
    foreach ($lecs as $l) { $g = trim((string)($l['grade'] ?? '')); if (isset($r[$g])) $r[$g]++; $t++; }
    $r['_total'] = $t;
    return $r;
}
?>

<style>
.rg-acc{border:1px solid var(--border);border-radius:10px;margin-bottom:10px;overflow:hidden;background:#fff}
.rg-acc>summary{list-style:none;cursor:pointer;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:600}
.rg-acc>summary::-webkit-details-marker{display:none}
.rg-acc>summary:hover{background:var(--grey)}
.rg-acc[open]>summary{border-bottom:1px solid var(--border)}
.rg-acc .acc-body{padding:8px 16px 16px}
.rg-acc .chev{transition:.2s;color:var(--muted)}
.rg-acc[open] .chev{transform:rotate(90deg)}
.rg-meta{display:flex;align-items:center;gap:10px}
.rg-count{background:var(--grey-mid);border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700;color:var(--text)}
.rgsum-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
.rgsum{background:#fff;border:1px solid var(--border);border-left:4px solid var(--c);border-radius:8px;padding:10px 14px}
.rgsum-n{font-size:24px;font-weight:800;color:var(--c);line-height:1}
.rgsum-l{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:4px}
@media(max-width:768px){.rgsum-grid{grid-template-columns:1fr 1fr}}
</style>

<div class="page-header">
    <h1>Research Groups</h1>
    <p>Lecturers grouped by research group — <?= $totalAssigned ?> assigned, <?= count($unassigned) ?> unassigned</p>
</div>

<!-- ══ Calculation_FG: staff category summary ══ -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-calculator" style="color:var(--teal)"></i> Staff Category Summary <span style="font-size:12px;color:var(--muted);font-weight:400">(Calculation FG)</span></div>
    <div class="rgsum-grid">
        <div class="rgsum" style="--c:#0d9488"><div class="rgsum-n"><?= $catCount['FG'] ?></div><div class="rgsum-l">FG (Focus Group)</div></div>
        <div class="rgsum" style="--c:#8b5cf6"><div class="rgsum-n"><?= $catCount['CoR'] ?></div><div class="rgsum-l">CoR (Centre of Research)</div></div>
        <div class="rgsum" style="--c:#2563eb"><div class="rgsum-n"><?= $catCount['External'] ?></div><div class="rgsum-l">External</div></div>
        <div class="rgsum" style="--c:#94a3b8"><div class="rgsum-n"><?= $catCount['Not set'] ?></div><div class="rgsum-l">Not set</div></div>
        <div class="rgsum" style="--c:#16a34a"><div class="rgsum-n"><?= count($lecturers) ?></div><div class="rgsum-l">Total Staff</div></div>
    </div>

    <div class="table-wrap" style="margin-top:1rem">
        <table class="arams-table">
            <thead><tr><th>Research Group</th><th>Code</th><th>Faculty</th><th style="text-align:center">Members</th></tr></thead>
            <tbody>
            <?php foreach ($groups as $g): $cnt = count($byMaster[$g['group_id']] ?? []); ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($g['group_name']) ?></td>
                <td><?= htmlspecialchars($g['group_code']) ?></td>
                <td><?= htmlspecialchars($g['faculty_code']) ?></td>
                <td style="text-align:center;font-weight:700"><?= $cnt ?></td>
            </tr>
            <?php endforeach; ?>
            <?php foreach ($byCentre as $centre => $members): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($centre) ?></td>
                <td colspan="2"><span class="badge badge-blue" style="font-size:10px">External / Others</span></td>
                <td style="text-align:center;font-weight:700"><?= count($members) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!empty($unassigned)): ?>
            <tr>
                <td style="font-weight:600;color:#b91c1c">Unassigned</td>
                <td colspan="2">—</td>
                <td style="text-align:center;font-weight:700"><?= count($unassigned) ?></td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ Calculation_FG (full): staff by grade per research group ══ -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-table-cells" style="color:var(--teal)"></i> Staff by Grade per Research Group <span style="font-size:12px;color:var(--muted);font-weight:400">(Calculation FG)</span></div>
    <?php if (empty($gradeCols)): ?>
    <p style="color:var(--muted);font-size:13px">No grade data available yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr>
                <th>Research Group</th>
                <?php foreach ($gradeCols as $gc): ?><th style="text-align:center"><?= htmlspecialchars($gc) ?></th><?php endforeach; ?>
                <th style="text-align:center">Total</th>
            </tr></thead>
            <tbody>
            <?php
            $colTotals = array_fill_keys($gradeCols, 0); $grand = 0;
            $renderRow = function ($label, $lecs) use ($gradeCols, &$colTotals, &$grand) {
                $r = gradeRow($lecs, $gradeCols);
                echo '<tr><td style="font-weight:600">' . htmlspecialchars($label) . '</td>';
                foreach ($gradeCols as $gc) { echo '<td style="text-align:center">' . ($r[$gc] ?: '·') . '</td>'; $colTotals[$gc] += $r[$gc]; }
                echo '<td style="text-align:center;font-weight:700">' . $r['_total'] . '</td></tr>';
                $grand += $r['_total'];
            };
            foreach ($groups as $g) $renderRow($g['group_name'], $byMaster[$g['group_id']] ?? []);
            foreach ($byCentre as $centre => $members) $renderRow($centre . ' (External/Others)', $members);
            if (!empty($unassigned)) $renderRow('Unassigned', $unassigned);
            ?>
            <tr style="border-top:2px solid var(--border);font-weight:700;background:var(--grey)">
                <td>Total</td>
                <?php foreach ($gradeCols as $gc): ?><td style="text-align:center"><?= $colTotals[$gc] ?></td><?php endforeach; ?>
                <td style="text-align:center"><?= $grand ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <p style="font-size:11px;color:var(--muted);margin-top:8px">Counts staff by JPA grade within each research group — mirrors the FRT <em>Calculation_FG</em> sheet.</p>
    <?php endif; ?>
</div>

<!-- Master research groups -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-sitemap" style="color:var(--teal)"></i> Faculty Research Groups (FG)</div>
    <?php if (empty($groups)): ?>
    <p style="color:var(--muted);font-size:13px">No research groups defined. Create them via User Management → Manage Groups.</p>
    <?php else: ?>
    <?php foreach ($groups as $g): $members = $byMaster[$g['group_id']] ?? []; ?>
    <details class="rg-acc">
        <summary>
            <span class="rg-meta">
                <i class="fas fa-chevron-right chev"></i>
                <span><?= htmlspecialchars($g['group_name']) ?>
                    <span style="color:var(--muted);font-weight:400;font-size:12px">· <?= htmlspecialchars($g['group_code']) ?> · <?= htmlspecialchars($g['faculty_code']) ?></span>
                </span>
            </span>
            <span class="rg-count"><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?></span>
        </summary>
        <div class="acc-body"><?= lecTable($members) ?></div>
    </details>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- External / Others groups (free-text research centre) -->
<?php if (!empty($byCentre)): ?>
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-network-wired" style="color:var(--blue)"></i> External / Other Groups</div>
    <?php foreach ($byCentre as $centre => $members): ?>
    <details class="rg-acc">
        <summary>
            <span class="rg-meta">
                <i class="fas fa-chevron-right chev"></i>
                <span><?= htmlspecialchars($centre) ?></span>
            </span>
            <span class="rg-count"><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?></span>
        </summary>
        <div class="acc-body"><?= lecTable($members) ?></div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Unassigned -->
<div class="card">
    <div class="card-title"><i class="fas fa-user-slash" style="color:#dc2626"></i> Unassigned Lecturers</div>
    <?php if (empty($unassigned)): ?>
    <p style="color:var(--muted);font-size:13px">Everyone is assigned to a research group. 🎉</p>
    <?php else: ?>
    <p style="color:var(--muted);font-size:12px;margin-bottom:8px">These lecturers have no research group set. Assign them via User Management or their detail page.</p>
    <?= lecTable($unassigned) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
// ============================================================
//  ARAMS — Generate Report API (Admin only)
//  Generates real Excel (CSV), PDF-ready HTML, or CSV files
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

// Accept both form POST and JSON body
if (!empty($_POST)) {
    $type      = $_POST['type']       ?? 'comprehensive';
    $year      = $_POST['year']       ?? 'all';
    $facultyId = $_POST['faculty_id'] ?? 'all';
    $format    = $_POST['format']     ?? 'Excel';
} else {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $type      = $body['type']       ?? 'comprehensive';
    $year      = $body['year']       ?? 'all';
    $facultyId = $body['faculty_id'] ?? 'all';
    $format    = $body['format']     ?? 'Excel';
}

$user    = currentUser();
$adminId = (int)$user['admin_id'];
$db      = getDB();

// ── Build WHERE clauses ───────────────────────────────────
$yearWhere    = $year !== 'all'      ? " AND p.pub_year = " . (int)$year : '';
$facWhere     = $facultyId !== 'all' ? " AND l.faculty_id = " . (int)$facultyId : '';
$yearWhereG   = $year !== 'all'
    ? " AND (g.start_date IS NULL OR YEAR(g.start_date) <= " . (int)$year . ")"
    . " AND (g.end_date   IS NULL OR YEAR(g.end_date)   >= " . (int)$year . ")"
    : '';

// ── Fetch data based on report type ──────────────────────
$rows  = [];
$title = '';
$cols  = [];
$catColIdx = null;   // when set, summary counts records per category instead of summing numbers

switch ($type) {

    case 'publications':
        $title = 'Publications Report';
        $cols  = ['#','Lecturer','Faculty','Title','Type','Indexing','Quartile','Year','Journal','DOI'];
        $sql   = "SELECT l.full_name, f.faculty_code, p.title, p.pub_type,
                         p.indexing_type, p.quartile, p.pub_year, p.journal_name, p.doi
                  FROM tbl_publication p
                  JOIN tbl_research_data rd ON p.data_id = rd.data_id
                  JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' AND rd.is_deleted=0 $yearWhere $facWhere
                  ORDER BY p.pub_year DESC, l.full_name";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['title'],
                       $r['pub_type'], $r['indexing_type'], $r['quartile'],
                       $r['pub_year'], $r['journal_name'], $r['doi']];
        }
        break;

    case 'grants':
        $title = 'Grants & Funding Report';
        $cols  = ['#','Lecturer','Faculty','Grant Title','Code','Category','Level','Role','Amount (RM)','Status','Active?','Start','End'];
        $sql   = "SELECT l.full_name, f.faculty_code, g.grant_title, g.grant_code,
                         g.grant_category, g.grant_level, g.role, g.amount,
                         g.status, g.start_date, g.end_date
                  FROM tbl_grant g
                  JOIN tbl_research_data rd ON g.data_id = rd.data_id
                  JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' AND rd.is_deleted=0 $yearWhereG $facWhere
                  ORDER BY g.start_date DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        $today = date('Y-m-d');
        foreach ($data as $i => $r) {
            $active = (empty($r['end_date']) || $r['end_date'] >= $today) ? 'Active' : 'Non-Active';
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['grant_title'],
                       $r['grant_code'], $r['grant_category'], $r['grant_level'],
                       $r['role'], $r['amount'] ? number_format((float)$r['amount'],2) : '',
                       $r['status'], $active, $r['start_date'], $r['end_date']];
        }
        break;

    case 'hindex':
        $title = 'H-Index & Citations Report';
        $cols  = ['#','Lecturer','Faculty','Year','H-Index','Citations','Source'];
        $sql   = "SELECT l.full_name, f.faculty_code, h.record_year,
                         h.hindex_value, h.citation_count, h.source
                  FROM tbl_hindex h
                  JOIN tbl_research_data rd ON h.data_id = rd.data_id
                  JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' AND rd.is_deleted=0 $facWhere
                  ORDER BY h.record_year DESC, l.full_name";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       $r['record_year'], $r['hindex_value'], $r['citation_count'], $r['source']];
        }
        break;

    case 'faculty':
        $title = 'Faculty Performance Report';
        $cols  = ['#','Faculty','Lecturers','Total Pubs','Q1 Pubs','Q2 Pubs','Total Grants','Avg H-Index','Total Income (RM)'];
        $sql   = "SELECT f.faculty_code, f.faculty_name,
                         COUNT(DISTINCT l.lecturer_id) AS lec_count,
                         SUM(k.total_publications) AS pubs,
                         SUM(k.q1_pubs) AS q1, SUM(k.q2_pubs) AS q2,
                         SUM(k.total_grants) AS grants,
                         AVG(k.current_hindex) AS hindex,
                         SUM(k.total_income_rm) AS income
                  FROM vw_lecturer_kpi k
                  JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  GROUP BY f.faculty_id ORDER BY pubs DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['faculty_code'], $r['lec_count'],
                       (int)$r['pubs'], (int)$r['q1'], (int)$r['q2'],
                       (int)$r['grants'], number_format((float)$r['hindex'],1),
                       $r['income'] ? number_format((float)$r['income'],2) : '0.00'];
        }
        break;

    case 'researchgroup':
        $title = 'Research Group Performance Report';
        $cols  = ['#','Research Group','Code','Faculty','Lecturers','Publications','Grants','Grant Amount (RM)','Income (RM)'];
        $facWhereRG = $facultyId !== 'all' ? " AND rg.faculty_id = " . (int)$facultyId : '';
        $ycPub = $year !== 'all' ? " AND p.pub_year = " . (int)$year : '';
        $ycGr  = $year !== 'all'
            ? " AND (g.start_date IS NULL OR YEAR(g.start_date) <= " . (int)$year . ")"
            . " AND (g.end_date   IS NULL OR YEAR(g.end_date)   >= " . (int)$year . ")"
            : '';
        $ycInc = $year !== 'all' ? " AND inc.year_received = " . (int)$year : '';
        $sql = "SELECT rg.group_code, rg.group_name, f.faculty_code,
                  (SELECT COUNT(*) FROM tbl_lecturer l WHERE l.research_group_id = rg.group_id) AS lecturers,
                  (SELECT COUNT(*) FROM tbl_publication p
                     JOIN tbl_research_data rd ON p.data_id = rd.data_id
                     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                   WHERE l.research_group_id = rg.group_id AND rd.status='Approved' AND rd.is_deleted=0 $ycPub) AS pubs,
                  (SELECT COUNT(*) FROM tbl_grant g
                     JOIN tbl_research_data rd ON g.data_id = rd.data_id
                     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                   WHERE l.research_group_id = rg.group_id AND rd.status='Approved' AND rd.is_deleted=0 $ycGr) AS grants,
                  (SELECT COALESCE(SUM(g.amount),0) FROM tbl_grant g
                     JOIN tbl_research_data rd ON g.data_id = rd.data_id
                     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                   WHERE l.research_group_id = rg.group_id AND rd.status='Approved' AND rd.is_deleted=0 $ycGr) AS grant_amount,
                  (SELECT COALESCE(SUM(inc.amount),0) FROM tbl_research_income inc
                     JOIN tbl_research_data rd ON inc.data_id = rd.data_id
                     JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                   WHERE l.research_group_id = rg.group_id AND rd.status='Approved' AND rd.is_deleted=0 $ycInc) AS income
                FROM tbl_research_group rg
                JOIN tbl_faculty f ON f.faculty_id = rg.faculty_id
                WHERE rg.is_active = 1 $facWhereRG
                ORDER BY f.faculty_code, rg.group_name";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['group_name'], $r['group_code'], $r['faculty_code'],
                       (int)$r['lecturers'], (int)$r['pubs'], (int)$r['grants'],
                       number_format((float)$r['grant_amount'], 2),
                       number_format((float)$r['income'], 2)];
        }
        break;

    case 'individual':
        $title = 'Individual Lecturer Report';
        $cols  = ['#','Lecturer','Faculty','Scopus ID','Total Pubs','Q1','Q2','Total Grants','Grants as PI','H-Index','Citations','Income (RM)'];
        $sql   = "SELECT l.full_name, f.faculty_code, l.scopus_id,
                         k.total_publications, k.q1_pubs, k.q2_pubs,
                         k.total_grants, k.grants_as_pi,
                         k.current_hindex, k.total_citations, k.total_income_rm
                  FROM vw_lecturer_kpi k
                  JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  $( $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '' )
                  ORDER BY k.total_publications DESC";
        // Use proper conditional
        $whereInd = $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '';
        $sql = "SELECT l.full_name, f.faculty_code, l.scopus_id,
                       k.total_publications, k.q1_pubs, k.q2_pubs,
                       k.total_grants, k.grants_as_pi,
                       k.current_hindex, k.total_citations, k.total_income_rm
                FROM vw_lecturer_kpi k
                JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                $whereInd
                ORDER BY k.total_publications DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['scopus_id'],
                       (int)$r['total_publications'], (int)$r['q1_pubs'], (int)$r['q2_pubs'],
                       (int)$r['total_grants'], (int)$r['grants_as_pi'],
                       (int)$r['current_hindex'], (int)$r['total_citations'],
                       $r['total_income_rm'] ? number_format((float)$r['total_income_rm'],2) : '0.00'];
        }
        break;

    case 'awards':
        $title = 'Awards & IP Report';
        $cols  = ['#','Lecturer','Faculty','Type','Name','Level/IP Type','Year/Filing Date','Organiser/Status'];
        $catColIdx = 3;   // summarise by "Type" (Award / Patent / Copyright / …)
        $sql   = "SELECT l.full_name, f.faculty_code, 'Award' AS rec_type,
                         a.award_name AS rec_name, a.level AS rec_level,
                         a.award_year AS rec_year, a.organiser AS rec_org
                  FROM tbl_award a
                  JOIN tbl_lecturer l ON l.lecturer_id = a.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  $facWhere
                  UNION ALL
                  SELECT l.full_name, f.faculty_code, ip.ip_type AS rec_type,
                         ip.ip_title AS rec_name, ip.ip_type AS rec_level,
                         COALESCE(ip.grant_date, ip.filing_date) AS rec_year, ip.registration_status AS rec_org
                  FROM tbl_ip_record ip
                  JOIN tbl_research_data rd ON ip.data_id = rd.data_id
                  JOIN tbl_lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' AND rd.is_deleted=0 $facWhere
                  ORDER BY rec_year DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       $r['rec_type'], $r['rec_name'], $r['rec_level'],
                       $r['rec_year'], $r['rec_org'] ?? '—'];
        }
        break;

    default: // comprehensive
        $title = 'Comprehensive Research Report';
        $cols  = ['#','Lecturer','Faculty','Pubs','Q1','Q2','Grants','PI Grants','H-Index','Citations','Income (RM)'];
        $sql   = "SELECT l.full_name, f.faculty_code,
                         k.total_publications, k.q1_pubs, k.q2_pubs,
                         k.total_grants, k.grants_as_pi,
                         k.current_hindex, k.total_citations, k.total_income_rm
                  FROM vw_lecturer_kpi k
                  JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                  $( $facultyId !== 'all' ? 'WHERE l.faculty_id = ?' : '' )
                  ORDER BY k.total_publications DESC";
        $whereComp = $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '';
        $sql = "SELECT l.full_name, f.faculty_code,
                       k.total_publications, k.q1_pubs, k.q2_pubs,
                       k.total_grants, k.grants_as_pi,
                       k.current_hindex, k.total_citations, k.total_income_rm
                FROM vw_lecturer_kpi k
                JOIN tbl_lecturer l ON l.lecturer_id = k.lecturer_id
                JOIN tbl_faculty  f ON f.faculty_id  = l.faculty_id
                $whereComp
                ORDER BY k.total_publications DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       (int)$r['total_publications'], (int)$r['q1_pubs'], (int)$r['q2_pubs'],
                       (int)$r['total_grants'], (int)$r['grants_as_pi'],
                       (int)$r['current_hindex'], (int)$r['total_citations'],
                       $r['total_income_rm'] ? number_format((float)$r['total_income_rm'],2) : '0.00'];
        }
        break;
}

// ── Save report record to DB ──────────────────────────────
$db->prepare(
    "INSERT INTO tbl_report (report_type, report_year, faculty_filter, format, admin_id)
     VALUES (?,?,?,?,?)"
)->execute([
    $title,
    $year === 'all' ? null : (int)$year,
    $facultyId === 'all' ? null : $facultyId,
    $format,
    $adminId
]);

// ── Audit log ─────────────────────────────────────────────
$db->prepare("INSERT INTO tbl_audit_log (user_id, action, target_type, details) VALUES (?,?,?,?)")
   ->execute([$user['user_id'], 'Generated Report', 'Report',
              "type=$type year=$year faculty=$facultyId format=$format"]);

// ── Generate file content ─────────────────────────────────
$yearLabel = $year === 'all' ? 'All Years' : $year;
$filename  = strtolower(str_replace(' ', '_', $title)) . '_' . $yearLabel . '_' . date('Ymd');

if ($format === 'CSV') {
    // ── CSV output ────────────────────────────────────────
    $csv = "\xEF\xBB\xBF"; // UTF-8 BOM so Excel renders names/symbols correctly
    $csv .= implode(',', array_map(fn($c) => '"' . str_replace('"','""',$c) . '"', $cols)) . "\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($c) => '"' . str_replace('"','""', (string)$c) . '"', $row)) . "\n";
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;

} elseif ($format === 'Excel') {
    // ── Excel XML (opens in Excel) ────────────────────────
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '<Worksheet ss:Name="' . htmlspecialchars($title) . '">' . "\n";
    $xml .= '<Table>' . "\n";

    // Title row
    $xml .= '<Row><Cell ss:MergeAcross="' . (count($cols)-1) . '">';
    $xml .= '<Data ss:Type="String">UTHM ARAMS — ' . htmlspecialchars($title) . ' (' . $yearLabel . ')</Data></Cell></Row>' . "\n";
    $xml .= '<Row><Cell><Data ss:Type="String">Generated: ' . date('d M Y H:i') . '</Data></Cell></Row>' . "\n";
    $xml .= '<Row/>' . "\n";

    // Header row
    $xml .= '<Row>';
    foreach ($cols as $col) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($col) . '</Data></Cell>';
    }
    $xml .= '</Row>' . "\n";

    // Data rows
    foreach ($rows as $row) {
        $xml .= '<Row>';
        foreach ($row as $val) {
            $type_cell = is_numeric($val) && $val !== '' ? 'Number' : 'String';
            $xml .= '<Cell><Data ss:Type="' . $type_cell . '">' . htmlspecialchars((string)$val) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";
    }

    $xml .= '</Table></Worksheet></Workbook>';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Content-Length: ' . strlen($xml));
    echo $xml;
    exit;

} else {
    // ── PDF — visual, print-ready HTML (dashboard style) ──
    // Choose label column = first mostly-text column (skip the "#" index)
    $labelIdx = 0;
    foreach ($cols as $ci => $cn) {
        if (trim((string)$cn) === '#') continue;
        $txt = 0; $tot = 0;
        foreach ($rows as $r) {
            $v = isset($r[$ci]) ? trim((string)$r[$ci]) : '';
            if ($v === '') continue;
            $tot++;
            if (!is_numeric(str_replace(',', '', $v))) $txt++;
        }
        if ($tot > 0 && $txt >= $tot * 0.6) { $labelIdx = $ci; break; }
    }

    // ── Summary: categorical (count by category column) OR numeric (sum of metrics) ──
    $kpis = [['label' => 'Total Records', 'value' => number_format(count($rows))]];
    $chartLabels = $chartValues = [];
    $metricName = ''; $barTitle = ''; $pieTitle = '';

    if ($catColIdx !== null) {
        // Count records per distinct value of the category column
        $counts = [];
        foreach ($rows as $r) {
            $key = trim((string)($r[$catColIdx] ?? ''));
            if ($key === '') $key = '—';
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $metricName = (string)$cols[$catColIdx];
        $kc = 0;
        foreach ($counts as $k => $c) {
            if ($kc++ >= 3) break;
            $kpis[] = ['label' => $k, 'value' => number_format($c)];
        }
        foreach (array_slice($counts, 0, 12, true) as $k => $c) {
            $chartLabels[] = mb_strlen($k) > 16 ? mb_substr($k, 0, 15) . '…' : $k;
            $chartValues[] = $c;
        }
        $hasChart = array_sum($chartValues) > 0;
        $barTitle = 'Records by ' . $metricName;
        $pieTitle = 'Distribution — ' . $metricName;
    } else {
        // Detect numeric metric columns + their sums (skip label column and "#")
        $numCols = [];
        foreach ($cols as $ci => $cn) {
            if ($ci === $labelIdx || trim((string)$cn) === '#') continue;
            $sum = 0.0; $n = 0;
            foreach ($rows as $r) {
                $v = isset($r[$ci]) ? str_replace(',', '', (string)$r[$ci]) : '';
                if ($v !== '' && is_numeric($v)) { $sum += (float)$v; $n++; }
            }
            if ($n > 0 && $n >= count($rows) * 0.4) $numCols[$ci] = $sum;
        }
        arsort($numCols);
        $primaryIdx = $numCols ? array_key_first($numCols) : null;

        $kc = 0;
        foreach ($numCols as $ci => $sum) {
            if ($kc++ >= 3) break;
            $val = ($sum == floor($sum)) ? number_format($sum) : number_format($sum, 2);
            $kpis[] = ['label' => $cols[$ci], 'value' => $val];
        }

        if ($primaryIdx !== null) {
            $cr = array_values(array_filter($rows, function ($r) use ($primaryIdx) {
                return (float)str_replace(',', '', (string)($r[$primaryIdx] ?? 0)) > 0;
            }));
            usort($cr, function ($a, $b) use ($primaryIdx) {
                $av = (float)str_replace(',', '', (string)($a[$primaryIdx] ?? 0));
                $bv = (float)str_replace(',', '', (string)($b[$primaryIdx] ?? 0));
                return $bv <=> $av;
            });
            $cr = array_slice($cr, 0, 12);
            foreach ($cr as $r) {
                $lbl = (string)($r[$labelIdx] ?? '');
                if (mb_strlen($lbl) > 16) $lbl = mb_substr($lbl, 0, 15) . '…';
                $chartLabels[] = $lbl;
                $chartValues[] = (float)str_replace(',', '', (string)($r[$primaryIdx] ?? 0));
            }
        }
        $hasChart   = $primaryIdx !== null && array_sum($chartValues) > 0;
        $metricName = $primaryIdx !== null ? $cols[$primaryIdx] : '';
        $barTitle = 'Top by ' . $metricName;
        $pieTitle = 'Distribution — ' . $metricName;
    }

    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<title>' . htmlspecialchars($title) . '</title>';
    if ($hasChart) $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    $html .= '<style>
        *{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#1e293b;margin:0;background:#fff}
        .wrap{padding:26px 30px}
        .cover{display:flex;align-items:center;gap:18px;padding:4px 4px 14px;border-bottom:3px solid #0B3C5D;margin-bottom:18px}
        .cover-logo{height:64px;width:auto;flex-shrink:0}
        .cover-titles{flex:1;text-align:center}
        .cover-uni{font-size:16px;font-weight:800;letter-spacing:.4px;color:#0B3C5D;line-height:1.2}
        .cover-sub{font-size:10px;color:#64748b;margin:3px 0 6px}
        .cover-rep{font-size:13px;font-weight:700;letter-spacing:1px;color:#0d9488}
        .cover-meta{text-align:right;font-size:10px;color:#64748b;flex-shrink:0;line-height:1.7;min-width:120px}
        .cover-meta .conf{color:#b91c1c;font-weight:700;letter-spacing:.5px}
        .kpis{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap}
        .kpi{flex:1;min-width:130px;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;background:#f8fafc}
        .kpi .v{font-size:24px;font-weight:700;color:#0B3C5D;line-height:1.1}
        .kpi .l{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-top:3px}
        .charts{display:flex;gap:16px;margin-bottom:32px;flex-wrap:wrap}
        .chartbox{flex:1;min-width:300px;border:1px solid #e2e8f0;border-radius:10px;padding:14px}
        .chartbox h3{font-size:12px;margin:0 0 10px;color:#0B3C5D}
        table{width:100%;border-collapse:collapse;margin-top:6px}
        th{background:#0B3C5D;color:#fff;padding:6px 8px;text-align:left;font-size:10px}
        td{padding:5px 8px;border-bottom:1px solid #e2e8f0;font-size:10px}
        tr:nth-child(even) td{background:#f8fafc}
        .sec-title{font-size:13px;color:#0B3C5D;font-weight:700;margin:6px 0 8px;clear:both}
        .footer{margin-top:22px;font-size:10px;color:#94a3b8;text-align:center}
        .toolbar{text-align:right;margin-bottom:10px}
        .btnp{background:#0B3C5D;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:12px;cursor:pointer}
        @page{size:A4 portrait;margin:12mm}
        @media print{.toolbar{display:none}.wrap{padding:0}.cover{border-bottom:3px solid #0B3C5D!important}.charts{page-break-inside:avoid}.chartbox{min-width:0;page-break-inside:avoid}.kpi{break-inside:avoid;page-break-inside:avoid}.chartbox canvas{max-width:100%!important;height:auto!important}}
    </style></head><body><div class="wrap">';

    $html .= '<div class="toolbar"><button class="btnp" onclick="window.print()">Print / Save as PDF</button></div>';
    $html .= '<div class="cover">';
    $html .= '<img src="/arams/assets/images/uthm_logo.png" class="cover-logo" alt="UTHM">';
    $html .= '<div class="cover-titles">';
    $html .= '<div class="cover-uni">UNIVERSITI TUN HUSSEIN ONN MALAYSIA</div>';
    $html .= '<div class="cover-sub">Academic Research Analytics and Monitoring System (ARAMS)</div>';
    $html .= '<div class="cover-rep">' . htmlspecialchars(strtoupper($title)) . '</div>';
    $html .= '</div>';
    $html .= '<div class="cover-meta"><div>Year: ' . $yearLabel . '</div><div>' . date('d M Y') . '</div><div>' . count($rows) . ' records</div><div class="conf">CONFIDENTIAL</div></div>';
    $html .= '</div>';

    $html .= '<div class="kpis">';
    foreach ($kpis as $k) {
        $html .= '<div class="kpi"><div class="v">' . htmlspecialchars($k['value']) . '</div><div class="l">' . htmlspecialchars($k['label']) . '</div></div>';
    }
    $html .= '</div>';

    if ($hasChart) {
        $html .= '<div class="charts">';
        $html .= '<div class="chartbox"><h3>' . htmlspecialchars($barTitle) . '</h3><div style="height:270px;position:relative"><canvas id="barC"></canvas></div></div>';
        $html .= '<div class="chartbox"><h3>' . htmlspecialchars($pieTitle) . '</h3><div style="height:270px;position:relative"><canvas id="pieC"></canvas></div></div>';
        $html .= '</div>';
    }

    $html .= '<div class="sec-title">Detailed Records</div>';
    $html .= '<table><thead><tr>';
    foreach ($cols as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $val) $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div class="footer">UTHM Academic Research Analytics and Monitoring System (ARAMS) &copy; ' . date('Y') . '</div>';
    $html .= '</div>';

    if ($hasChart) {
        $html .= '<script>
        const L = ' . json_encode($chartLabels) . ';
        const V = ' . json_encode($chartValues) . ';
        const P = ["#0B3C5D","#1E88A8","#2BB6A3","#7C9CBF","#F4A259","#BC4B51","#8CB369","#5B5F97","#E9C46A","#A8DADC","#457B9D","#E76F51"];
        function draw(){
          new Chart(document.getElementById("barC"),{type:"bar",data:{labels:L,datasets:[{data:V,backgroundColor:"#1E88A8"}]},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:false}},scales:{x:{ticks:{font:{size:8},maxRotation:90,minRotation:90,autoSkip:false}},y:{beginAtZero:true}}}});
          const top=L.slice(0,6),tv=V.slice(0,6);
          if(V.length>6){top.push("Others");tv.push(V.slice(6).reduce((a,b)=>a+b,0));}
          new Chart(document.getElementById("pieC"),{type:"doughnut",data:{labels:top,datasets:[{data:tv,backgroundColor:P}]},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{position:"bottom",labels:{font:{size:9},boxWidth:10,padding:8}}}}});
          window.addEventListener("beforeprint",function(){var b=Chart.getChart("barC");if(b)b.resize();var p=Chart.getChart("pieC");if(p)p.resize();});
          requestAnimationFrame(function(){ setTimeout(function(){window.print();},800); });
        }
        if(window.Chart){draw();}else{window.addEventListener("load",function(){ if(window.Chart){draw();}else{setTimeout(function(){window.print();},400);} });}
        </script>';
    } else {
        $html .= '<script>window.onload=function(){setTimeout(function(){window.print();},300);}</script>';
    }
    $html .= '</body></html>';

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    echo $html;
    exit;
}
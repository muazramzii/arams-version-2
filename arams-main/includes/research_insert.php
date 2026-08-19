<?php
// ============================================================
//  ARAMS — Shared research-record insert helper
//  Used by:  api/submit_research.php   (lecturer, status Pending)
//            api/admin_add_record.php  (admin, status Approved)
//  Inserts the type-specific child row under an existing
//  tbl_research_data parent (data_id). Caller owns the
//  transaction and the parent row.
// ============================================================

if (!function_exists('insertResearchRecord')) {
function insertResearchRecord(PDO $db, string $type, int $dataId, array $post): void
{
    switch ($type) {
        case 'publication':
            $st = $db->prepare(
                "INSERT INTO tbl_publication
                 (title, authors, author_role, student_author, journal_name, country, issn, pub_year,
                  volume, issue, pages, pub_type, indexing_type, quartile, impact_factor,
                  doi, url, national_collaboration, international_collaboration,
                  industries_collaboration, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($post['title']                    ?? ''),
                sanitize($post['authors']                  ?? ''),
                $post['author_role']                       ?? 'Co-Author',
                (int)($post['student_author']              ?? 0),
                sanitize($post['journal_name']             ?? ''),
                sanitize($post['country']                  ?? ''),
                sanitize($post['issn']                     ?? ''),
                (int)($post['pub_year']                    ?? date('Y')),
                sanitize($post['volume']                   ?? ''),
                sanitize($post['issue']                    ?? ''),
                sanitize($post['pages']                    ?? ''),
                $post['pub_type']                          ?? 'Journal',
                $post['indexing_type']                     ?? 'Others',
                $post['quartile']                          ?? 'N/A',
                !empty($post['impact_factor'])             ? (float)$post['impact_factor'] : null,
                sanitize($post['doi']                      ?? ''),
                sanitize($post['url']                      ?? ''),
                (int)($post['national_collaboration']      ?? 0),
                (int)($post['international_collaboration']  ?? 0),
                (int)($post['industries_collaboration']    ?? 0),
                $dataId
            ]);
            break;

        case 'grant':
            $st = $db->prepare(
                "INSERT INTO tbl_grant
                 (grant_title, grant_code, funder, grant_category, grant_level,
                  role, amount, start_date, end_date, status, mygrants_id, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($post['grant_title']   ?? ''),
                sanitize($post['grant_code']    ?? ''),
                sanitize($post['funder']        ?? ''),
                $post['grant_category']         ?? 'Others',
                $post['grant_level']            ?? null,
                $post['role']                   ?? 'Member',
                !empty($post['amount'])         ? (float)$post['amount'] : null,
                $post['start_date']             ?? null,
                $post['end_date']               ?? null,
                $post['grant_status']           ?? 'Active',
                sanitize($post['mygrants_id']   ?? ''),
                $dataId
            ]);
            break;

        case 'hindex':
            $st = $db->prepare(
                "INSERT INTO tbl_hindex (record_year, hindex_value, citation_count, source, data_id)
                 VALUES (?,?,?,?,?)"
            );
            $st->execute([
                (int)($post['record_year']    ?? date('Y')),
                (int)($post['hindex_value']   ?? 0),
                !empty($post['citation_count']) ? (int)$post['citation_count'] : null,
                $post['source']               ?? 'Scopus',
                $dataId
            ]);
            break;

        case 'ip':
            $st = $db->prepare(
                "INSERT INTO tbl_ip_record
                 (ip_title, ip_type, ip_number, inventors, country, filing_date, grant_date, registration_status, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($post['ip_title']           ?? ''),
                $post['ip_type']                     ?? 'Patent',
                sanitize($post['ip_number']          ?? ''),
                sanitize($post['inventors']          ?? ''),
                sanitize($post['country']            ?? 'Malaysia'),
                $post['filing_date']                 ?? null,
                !empty($post['grant_date']) ? $post['grant_date'] : null,
                $post['registration_status']         ?? 'Filed',
                $dataId
            ]);
            break;

        case 'income':
            $st = $db->prepare(
                "INSERT INTO tbl_research_income
                 (source, income_category, amount, year_received, data_id)
                 VALUES (?,?,?,?,?)"
            );
            $st->execute([
                sanitize($post['source']         ?? ''),
                $post['income_category']         ?? 'Research Grant',
                (float)($post['amount']          ?? 0),
                (int)($post['year_received']     ?? date('Y')),
                $dataId
            ]);
            break;

        default:
            throw new Exception('Invalid record type: ' . $type);
    }
}
}

if (!function_exists('updateResearchRecord')) {
function updateResearchRecord(PDO $db, string $type, int $dataId, array $post): void
{
    switch ($type) {
        case 'publication':
            $db->prepare(
                "UPDATE tbl_publication SET
                   title=?, authors=?, author_role=?, student_author=?, journal_name=?, country=?, issn=?,
                   pub_year=?, volume=?, issue=?, pages=?, pub_type=?, indexing_type=?, quartile=?,
                   impact_factor=?, doi=?, url=?, national_collaboration=?, international_collaboration=?,
                   industries_collaboration=?
                 WHERE data_id=?"
            )->execute([
                sanitize($post['title']                   ?? ''),
                sanitize($post['authors']                 ?? ''),
                $post['author_role']                      ?? 'Co-Author',
                (int)($post['student_author']             ?? 0),
                sanitize($post['journal_name']            ?? ''),
                sanitize($post['country']                 ?? ''),
                sanitize($post['issn']                    ?? ''),
                (int)($post['pub_year']                   ?? date('Y')),
                sanitize($post['volume']                  ?? ''),
                sanitize($post['issue']                   ?? ''),
                sanitize($post['pages']                   ?? ''),
                $post['pub_type']                         ?? 'Journal',
                $post['indexing_type']                    ?? 'Others',
                $post['quartile']                         ?? 'N/A',
                !empty($post['impact_factor'])            ? (float)$post['impact_factor'] : null,
                sanitize($post['doi']                     ?? ''),
                sanitize($post['url']                     ?? ''),
                (int)($post['national_collaboration']     ?? 0),
                (int)($post['international_collaboration'] ?? 0),
                (int)($post['industries_collaboration']   ?? 0),
                $dataId
            ]);
            break;

        case 'grant':
            $db->prepare(
                "UPDATE tbl_grant SET
                   grant_title=?, grant_code=?, funder=?, grant_category=?, grant_level=?,
                   role=?, amount=?, start_date=?, end_date=?, status=?, mygrants_id=?
                 WHERE data_id=?"
            )->execute([
                sanitize($post['grant_title']   ?? ''),
                sanitize($post['grant_code']    ?? ''),
                sanitize($post['funder']        ?? ''),
                $post['grant_category']         ?? 'Others',
                $post['grant_level']            ?? null,
                $post['role']                   ?? 'Member',
                !empty($post['amount'])         ? (float)$post['amount'] : null,
                $post['start_date']             ?: null,
                $post['end_date']               ?: null,
                $post['grant_status']           ?? 'Active',
                sanitize($post['mygrants_id']   ?? ''),
                $dataId
            ]);
            break;

        case 'hindex':
            $db->prepare(
                "UPDATE tbl_hindex SET record_year=?, hindex_value=?, citation_count=?, source=?
                 WHERE data_id=?"
            )->execute([
                (int)($post['record_year']    ?? date('Y')),
                (int)($post['hindex_value']   ?? 0),
                !empty($post['citation_count']) ? (int)$post['citation_count'] : null,
                $post['source']               ?? 'Scopus',
                $dataId
            ]);
            break;

        case 'ip':
            $db->prepare(
                "UPDATE tbl_ip_record SET
                   ip_title=?, ip_type=?, ip_number=?, inventors=?, country=?, filing_date=?, grant_date=?, registration_status=?
                 WHERE data_id=?"
            )->execute([
                sanitize($post['ip_title']     ?? ''),
                $post['ip_type']               ?? 'Patent',
                sanitize($post['ip_number']    ?? ''),
                sanitize($post['inventors']    ?? ''),
                sanitize($post['country']      ?? 'Malaysia'),
                $post['filing_date']           ?: null,
                !empty($post['grant_date'])    ? $post['grant_date'] : null,
                $post['registration_status']   ?? 'Filed',
                $dataId
            ]);
            break;

        case 'income':
            $db->prepare(
                "UPDATE tbl_research_income SET source=?, income_category=?, amount=?, year_received=?
                 WHERE data_id=?"
            )->execute([
                sanitize($post['source']         ?? ''),
                $post['income_category']         ?? 'Research Grant',
                (float)($post['amount']          ?? 0),
                (int)($post['year_received']     ?? date('Y')),
                $dataId
            ]);
            break;

        default:
            throw new Exception('Invalid record type: ' . $type);
    }
}
}
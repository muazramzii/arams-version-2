<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controlled vocabularies.
 *
 * Sourced from assets/js/research_forms.js, which was the most complete of the
 * three divergent copies in ARAMS 1.0 (the others being duplicated PHP arrays
 * in api/update_lecturer_admin.php and the schema ENUMs). Observed values from
 * the real data are folded in, with the drifted spellings normalised — most
 * notably 'University' (32 rows) and 'Universiti' (4 rows), which were the same
 * concept and which made the 1.0 grant-level KPI criterion unmatchable.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->simple('publication_types', [
            'JOURNAL'      => ['Journal', 'Jurnal'],
            'PROCEEDING'   => ['Proceeding / Seminar', 'Prosiding / Seminar'],
            'BOOK_CHAPTER' => ['Book Chapter', 'Bab Buku'],
            'BOOK'         => ['Book', 'Buku'],
            'OTHERS'       => ['Others', 'Lain-lain'],
        ]);

        $this->simple('author_roles', [
            'FIRST_AUTHOR'         => ['UTHM - First Author', 'Penulis Pertama'],
            'CORRESPONDING_AUTHOR' => ['Corresponding Author', 'Penulis Koresponden'],
            'CHAPTER_AUTHOR'       => ['Chapter Author', 'Penulis Dalam Bab'],
            'EDITOR'               => ['Editor', 'Editor'],
            'CO_AUTHOR'            => ['Co-Author', 'Penulis Bersama'],
        ]);

        // A join table now, so "indexed in Scopus" is an EXISTS rather than an
        // equality test against a SET — the 1.0 bug that hid every 'Scopus,WoS'
        // publication from a Scopus criterion.
        $this->simple('indexings', [
            'SCOPUS' => ['Scopus', null],
            'WOS'    => ['Web of Science', null],
            'MYCITE' => ['MyCite', null],
            'ERA'    => ['ERA', null],
            'ERIC'   => ['ERIC', null],
            'OTHERS' => ['Others', 'Lain-lain'],
        ]);

        $countries = json_decode(file_get_contents(database_path('data/countries.json')), true);
        $rows = [];
        foreach ($countries as $i => $name) {
            $rows[] = [
                'code'       => strtoupper(Str::slug($name, '_')),
                'label'      => $name,
                'label_ms'   => null,
                // Malaysia first — it is the default on every form.
                'sort_order' => $name === 'Malaysia' ? 0 : $i + 1,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('countries')->insertOrIgnore($rows);

        $this->simple('grant_levels', [
            'UNIVERSITI'    => ['University', 'Universiti'],
            'NATIONAL'      => ['National', 'Kebangsaan'],
            'INTERNATIONAL' => ['International', 'Antarabangsa'],
            'NGO'           => ['NGO', 'NGO'],
            'INDUSTRIES'    => ['Industries', 'Industri'],
        ]);

        // The FRT cascade, lifted from research_forms.js and now expressed as a
        // foreign key. In 1.0 this map lived only in JavaScript, so the database
        // accepted category/level combinations the form would never produce.
        $cascade = json_decode(file_get_contents(database_path('data/grant_categories.json')), true);
        $levelIds = DB::table('grant_levels')->pluck('id', 'code');
        $catRows = [];
        $order = 0;
        foreach ($cascade as $levelLabel => $categories) {
            $levelCode = strtoupper(Str::slug($levelLabel, '_'));
            foreach ($categories as $category) {
                $catRows[] = [
                    'code'           => strtoupper(Str::slug($category, '_')),
                    'label'          => $category,
                    'label_ms'       => null,
                    'grant_level_id' => $levelIds[$levelCode] ?? null,
                    'sort_order'     => $order++,
                    'is_active'      => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
        }
        DB::table('grant_categories')->insertOrIgnore($catRows);

        $this->simple('grant_roles', [
            'PI'     => ['Principal Investigator', 'Ketua Penyelidik'],
            'CO_I'   => ['Co-Investigator', 'Penyelidik Bersama'],
            'MEMBER' => ['Member', 'Ahli'],
        ]);

        $this->simple('grant_statuses', [
            'ACTIVE'           => ['Active', 'Aktif'],
            'COMPLETED'        => ['Completed', 'Selesai'],
            'TERMINATED'       => ['Terminated', 'Ditamatkan'],
            'PENDING_APPROVAL' => ['Pending Approval', 'Menunggu Kelulusan'],
        ]);

        $this->simple('funders', [
            'MOHE'     => ['Ministry of Higher Education (MOHE)', 'Kementerian Pendidikan Tinggi'],
            'MOSTI'    => ['Ministry of Science, Technology and Innovation (MOSTI)', null],
            'UTHM'     => ['UTHM Internal', 'Dalaman UTHM'],
            'INDUSTRY' => ['Industry', 'Industri'],
            'NGO'      => ['NGO', 'NGO'],
            'INTL'     => ['International Body', 'Badan Antarabangsa'],
            'OTHERS'   => ['Others', 'Lain-lain'],
        ]);

        $this->simple('income_categories', [
            'RESEARCH_GRANT'    => ['Research Grant', 'Geran Penyelidikan'],
            'CONSULTANCY'       => ['Consultancy', 'Perundingan'],
            'CONTRACT_RESEARCH' => ['Contract Research', 'Penyelidikan Kontrak'],
            'COMMERCIALISATION' => ['Commercialisation', 'Pengkomersilan'],
            'TRAINING'          => ['Training', 'Latihan'],
            'ENDOWMENT'         => ['Endowment', 'Wakaf'],
            'IN_KIND'           => ['In-Kind', 'Sumbangan Barangan'],
            'OTHERS'            => ['Others', 'Lain-lain'],
        ]);

        $this->simple('ip_types', [
            'PATENT'            => ['Patent', 'Paten'],
            'COPYRIGHT'         => ['Copyright', 'Hak Cipta'],
            'TRADEMARK'         => ['Trademark', 'Cap Dagangan'],
            'INDUSTRIAL_DESIGN' => ['Industrial Design', 'Reka Bentuk Perindustrian'],
            'TRADE_SECRET'      => ['Trade Secret', 'Rahsia Perdagangan'],
            'OTHERS'            => ['Others', 'Lain-lain'],
        ]);

        $this->simple('ip_registration_statuses', [
            'FILED'   => ['Filed', 'Difailkan'],
            'AWARDED' => ['Awarded', 'Dianugerahkan'],
        ]);

        $this->simple('award_types', [
            'GOLD'    => ['Gold', 'Emas'],
            'SILVER'  => ['Silver', 'Perak'],
            'BRONZE'  => ['Bronze', 'Gangsa'],
            'SPECIAL' => ['Special Award', 'Anugerah Khas'],
            'OTHERS'  => ['Others', 'Lain-lain'],
        ]);

        $this->simple('award_levels', [
            'UNIVERSITY'    => ['University', 'Universiti'],
            'NATIONAL'      => ['National', 'Kebangsaan'],
            'INTERNATIONAL' => ['International', 'Antarabangsa'],
        ]);

        // 1.0 stored these as free text, producing English/Malay duplicates:
        // 'Associate Professor' alongside 'Profesor Madya', and 'Senior Lecturer'
        // alongside 'Pensyarah Kanan (Senior Lecturer)'.
        $this->simple('positions', [
            'PROFESSOR'         => ['Professor', 'Profesor'],
            'ASSOC_PROFESSOR'   => ['Associate Professor', 'Profesor Madya'],
            'SENIOR_LECTURER'   => ['Senior Lecturer', 'Pensyarah Kanan'],
            'LECTURER'          => ['Lecturer', 'Pensyarah'],
            'TEACHING_ENGINEER' => ['Teaching Engineer', 'Jurutera Pengajar'],
            'OTHERS'            => ['Others', 'Lain-lain'],
        ]);

        $this->simple('grades', [
            'VK7'  => ['VK7', null], 'DS54' => ['DS54', null], 'DS53' => ['DS53', null],
            'DS52' => ['DS52', null], 'DS51' => ['DS51', null], 'DS45' => ['DS45', null],
            'DH52' => ['DH52', null], 'DH48' => ['DH48', null],
        ]);

        $this->simple('researcher_statuses', [
            'PRINCIPAL'        => ['Principal Researcher', 'Penyelidik Utama'],
            'HEAD_OF_GROUP'    => ['Head of the Group', 'Ketua Kumpulan'],
            'ACTIVE'           => ['Active Researcher', 'Penyelidik Aktif'],
            'OTHERS'           => ['Others', 'Lain-lain'],
        ]);

        // Was five fixed columns on tbl_lecturer; adding a provider meant a
        // schema change. Now a genuine one-to-many.
        $this->simple('external_id_providers', [
            'SCOPUS'         => ['Scopus Author ID', null],
            'ORCID'          => ['ORCID', null],
            'RESEARCHER_ID'  => ['WoS ResearcherID', null],
            'LENS'           => ['Lens.org ID', null],
            'GOOGLE_SCHOLAR' => ['Google Scholar', null],
        ]);

        // 1.0 held both 'FG' (55 rows) and 'Focus Group' (8 rows) for the same
        // concept, plus blanks. Normalised here.
        $this->simple('research_group_categories', [
            'FG'       => ['Focus Group', 'Kumpulan Fokus'],
            'COR'      => ['Centre of Research (CoR)', 'Pusat Penyelidikan'],
            'COE'      => ['Centre of Excellence (CoE)', 'Pusat Kecemerlangan'],
            'EXTERNAL' => ['External', 'Luaran'],
        ]);

        $this->simple('metric_sources', [
            'SCOPUS'         => ['Scopus', null],
            'WOS'            => ['Web of Science', null],
            'GOOGLE_SCHOLAR' => ['Google Scholar', null],
            'OTHERS'         => ['Others', 'Lain-lain'],
        ]);
    }

    /** @param array<string, array{0:string, 1:?string}> $values */
    private function simple(string $table, array $values): void
    {
        $rows = [];
        $order = 0;
        foreach ($values as $code => [$label, $labelMs]) {
            $rows[] = [
                'code'       => $code,
                'label'      => $label,
                'label_ms'   => $labelMs,
                'sort_order' => $order++,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table($table)->insertOrIgnore($rows);
    }
}

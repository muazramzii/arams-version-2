<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * UTHM faculties and research groups, taken verbatim from the ARAMS 1.0
 * production data (tbl_faculty, 12 rows; tbl_research_group, 13 rows).
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $faculties = [
            ['FSKTM', 'Faculty of Computer Science and Information Technology'],
            ['FKEE',  'Faculty of Electrical and Electronic Engineering'],
            ['FKMP',  'Faculty of Mechanical and Manufacturing Engineering'],
            ['FKAAS', 'Faculty of Applied Sciences and Technology'],
            ['FKTN',  'Faculty of Engineering Technology'],
            ['FKAAB', 'Faculty of Civil Engineering and Built Environment'],
            ['FPTP',  'Faculty of Technology Management and Business'],
            ['FPTV',  'Faculty of Technical and Vocational Education'],
            ['JBS',   'Johor Business School'],
            ['CLS',   'Centre For Language Studies'],
            ['CDS',   'Centre For Diploma Studies'],
            ['CGSC',  'Centre for General Studies and Co-Curricular'],
        ];

        $rows = [];
        foreach ($faculties as $i => [$code, $name]) {
            $rows[] = [
                'code'       => $code,
                'name'       => $name,
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('faculties')->insertOrIgnore($rows);

        $facultyIds = DB::table('faculties')->pluck('id', 'code');
        $fgCategory = DB::table('research_group_categories')->where('code', 'FG')->value('id');
        $corCategory = DB::table('research_group_categories')->where('code', 'COR')->value('id');

        // All 13 belong to FKAAS in the 1.0 data.
        $groups = [
            ['CERCOM',      'Center of Research for Computational Mathematics (CERCOM)', $corCategory],
            ['COR-SUNR',    'Centre of Research Sustainable Uses of Natural Resources (COR-SUNR)', $corCategory],
            ['PDSR',        'Photonics Devices and Sensors Research Center (PDSR)', $corCategory],
            ['CerAm',       'Ceramic and Amorphous (CerAm)', $fgCategory],
            ['RaMP',        'Radiation Monitoring and Protection (RaMP)', $fgCategory],
            ['AdEC',        'Advanced Analytical and Environmental Chemistry (AdEC)', $fgCategory],
            ['NSA',         'Numerical Simulation and Applications (NSA)', $fgCategory],
            ['FMA',         'Fuzzy Mathematics and Applications (FMA)', $fgCategory],
            ['DASM',        'Data Analytics, Sciences and Modeling (DASM)', $fgCategory],
            ['AdHerb',      'Advanced Herbal and Ethnomedical Research (AdHerb)', $fgCategory],
            ['Future Food', 'Future Food Research and Innovation (Future Food)', $fgCategory],
            ['eNCORe',      'Environmental Management and Conservation Research Unit (eNCORe)', $fgCategory],
            ['AI.DA',       'Akademik Intelek dan Data Analitik (AI.DA)', $fgCategory],
        ];

        $groupRows = [];
        foreach ($groups as [$code, $name, $categoryId]) {
            $groupRows[] = [
                'faculty_id'                 => $facultyIds['FKAAS'],
                'research_group_category_id' => $categoryId,
                'code'                       => $code,
                'name'                       => $name,
                'is_active'                  => true,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ];
        }
        DB::table('research_groups')->insertOrIgnore($groupRows);
    }
}

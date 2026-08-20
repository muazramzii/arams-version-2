<?php

namespace App\Services\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * Maps ARAMS 1.0's free-text and drifted values onto ARAMS 2.0 reference rows,
 * recording every decision in legacy_value_map.
 *
 * The drift is real and measured: tbl_grant holds both 'University' (32 rows)
 * and 'Universiti' (4) for one concept, tbl_lecturer holds both 'FG' (55) and
 * 'Focus Group' (8), positions appear in English and Malay, and roughly 60
 * values across several columns are empty strings that MariaDB substituted for
 * invalid ENUM input. None of that can be reinterpreted silently.
 */
class VocabularyMapper
{
    /** vocabulary => [legacy value (lowercased) => target code] */
    private const RULES = [
        'grant_levels' => [
            'university' => 'UNIVERSITI',
            'universiti' => 'UNIVERSITI',
            'national' => 'NATIONAL',
            'kebangsaan' => 'NATIONAL',
            'international' => 'INTERNATIONAL',
            'antarabangsa' => 'INTERNATIONAL',
            'ngo' => 'NGO',
            'industries' => 'INDUSTRIES',
            'industry' => 'INDUSTRIES',
        ],
        'grant_roles' => [
            'pi' => 'PI',
            'co-i' => 'CO_I',
            'member' => 'MEMBER',
        ],
        'grant_statuses' => [
            'active' => 'ACTIVE',
            'completed' => 'COMPLETED',
            'terminated' => 'TERMINATED',
            'pending approval' => 'PENDING_APPROVAL',
        ],
        'publication_types' => [
            'journal' => 'JOURNAL',
            'proceeding / seminar' => 'PROCEEDING',
            'book chapter' => 'BOOK_CHAPTER',
            'book' => 'BOOK',
            'others' => 'OTHERS',
        ],
        'author_roles' => [
            'uthm - first author' => 'FIRST_AUTHOR',
            'corresponding author' => 'CORRESPONDING_AUTHOR',
            'penulis dalam bab' => 'CHAPTER_AUTHOR',
            'editor' => 'EDITOR',
            'co-author' => 'CO_AUTHOR',
        ],
        'indexings' => [
            'scopus' => 'SCOPUS',
            'wos' => 'WOS',
            'mycite' => 'MYCITE',
            'era' => 'ERA',
            'eric' => 'ERIC',
            'others' => 'OTHERS',
        ],
        'income_categories' => [
            'research grant' => 'RESEARCH_GRANT',
            'consultancy' => 'CONSULTANCY',
            'contract research' => 'CONTRACT_RESEARCH',
            'commercialisation' => 'COMMERCIALISATION',
            'training' => 'TRAINING',
            'endowment' => 'ENDOWMENT',
            'in-kind' => 'IN_KIND',
            'others' => 'OTHERS',
        ],
        'ip_types' => [
            'patent' => 'PATENT',
            'copyright' => 'COPYRIGHT',
            'trademark' => 'TRADEMARK',
            'industrial design' => 'INDUSTRIAL_DESIGN',
            'trade secret' => 'TRADE_SECRET',
            'others' => 'OTHERS',
        ],
        'ip_registration_statuses' => [
            'filed' => 'FILED',
            'awarded' => 'AWARDED',
        ],
        'award_levels' => [
            'university' => 'UNIVERSITY',
            'national' => 'NATIONAL',
            'international' => 'INTERNATIONAL',
        ],
        'positions' => [
            'professor' => 'PROFESSOR',
            'associate professor' => 'ASSOC_PROFESSOR',
            'profesor madya' => 'ASSOC_PROFESSOR',
            'senior lecturer' => 'SENIOR_LECTURER',
            'pensyarah kanan (senior lecturer)' => 'SENIOR_LECTURER',
            'pensyarah kanan' => 'SENIOR_LECTURER',
            'lecturer' => 'LECTURER',
            'jurutera pengajar' => 'TEACHING_ENGINEER',
        ],
        'researcher_statuses' => [
            'principal researcher' => 'PRINCIPAL',
            'head of the group' => 'HEAD_OF_GROUP',
            'active researcher' => 'ACTIVE',
            'others' => 'OTHERS',
        ],
        'metric_sources' => [
            'scopus' => 'SCOPUS',
            'wos' => 'WOS',
            'google scholar' => 'GOOGLE_SCHOLAR',
            'others' => 'OTHERS',
        ],
    ];

    /** @var array<string, array<string, int|null>> vocabulary => code => id */
    private array $lookups = [];

    /** @var array<string, array<string, int>> unresolved values, counted */
    private array $unmapped = [];

    /** @var array<string, array<string, int>> resolved values, counted */
    private array $mapped = [];

    /**
     * Resolve a legacy value to a reference-table id.
     * Returns null when the value is blank or has no rule — deliberately, so
     * the caller stores NULL rather than guessing.
     */
    public function id(string $vocabulary, ?string $legacyValue): ?int
    {
        $raw = trim((string) $legacyValue);

        if ($raw === '') {
            $this->count($this->unmapped, $vocabulary, '(empty)');

            return null;
        }

        $code = self::RULES[$vocabulary][mb_strtolower($raw)] ?? null;

        if ($code === null) {
            $this->count($this->unmapped, $vocabulary, $raw);

            return null;
        }

        $this->count($this->mapped, $vocabulary, $raw);

        return $this->lookup($vocabulary)[$code] ?? null;
    }

    /** Values the rules could not place, for the reconciliation report. */
    public function unmapped(): array
    {
        return $this->unmapped;
    }

    public function mapped(): array
    {
        return $this->mapped;
    }

    /** Persist every decision, so the cleaning is reviewable after the fact. */
    public function persist(): void
    {
        $rows = [];

        foreach ($this->mapped as $vocabulary => $values) {
            foreach ($values as $value => $count) {
                // Every row must carry an identical key set: a bulk upsert
                // builds one column list from the first row, so a `note` that
                // appears only on some rows shifts the values out of line.
                $rows[] = [
                    'vocabulary'   => $vocabulary,
                    'legacy_value' => $value,
                    'target_code'  => self::RULES[$vocabulary][mb_strtolower($value)] ?? null,
                    'decision'     => mb_strtolower($value) === $value ? 'MAPPED' : 'NORMALISED',
                    'row_count'    => $count,
                    'note'         => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }

        foreach ($this->unmapped as $vocabulary => $values) {
            foreach ($values as $value => $count) {
                $rows[] = [
                    'vocabulary'   => $vocabulary,
                    'legacy_value' => $value,
                    'target_code'  => null,
                    'decision'     => 'UNKNOWN',
                    'row_count'    => $count,
                    'note'         => 'No rule matched; stored as NULL and left for review.',
                    'created_at'   => now(),
                    'updated_at'   => now(),

                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('legacy_value_map')->upsert(
                $chunk,
                ['vocabulary', 'legacy_value'],
                ['target_code', 'decision', 'row_count', 'note', 'updated_at'],
            );
        }
    }

    private function lookup(string $vocabulary): array
    {
        return $this->lookups[$vocabulary] ??= DB::table($vocabulary)
            ->pluck('id', 'code')
            ->all();
    }

    private function count(array &$bucket, string $vocabulary, string $value): void
    {
        $bucket[$vocabulary][$value] = ($bucket[$vocabulary][$value] ?? 0) + 1;
    }
}

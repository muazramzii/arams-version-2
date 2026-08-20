<?php

namespace App\Services\Research;

use App\Models\ResearchRecord;
use App\Models\ResearchType;
use App\Models\StaffProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and updates a research record plus its subtype row in one
 * transaction, and derives the supertype fields the rest of the system
 * depends on: display_title and the D4 effective date.
 *
 * Driven by the research_types registry, so adding a sixth type (D6) means
 * adding a rules entry and a subtype table — not editing five controllers.
 */
class ResearchRecordWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Validation rules per type, used by the controller's Form Request. */
    public static function rulesFor(string $typeCode): array
    {
        return match ($typeCode) {
            'PUBLICATION' => [
                'title'                  => ['required', 'string', 'max:500'],
                'journal_name'           => ['nullable', 'string', 'max:255'],
                'issn'                   => ['nullable', 'string', 'max:20'],
                'pub_year'               => ['required', 'integer', 'min:1950', 'max:2100'],
                'publication_type_id'    => ['nullable', 'integer', 'exists:publication_types,id'],
                'author_role_id'         => ['nullable', 'integer', 'exists:author_roles,id'],
                'country_id'             => ['nullable', 'integer', 'exists:countries,id'],
                'quartile'               => ['nullable', 'in:Q1,Q2,Q3,Q4,N/A'],
                'impact_factor'          => ['nullable', 'numeric', 'min:0'],
                'doi'                    => ['nullable', 'string', 'max:255'],
                'url'                    => ['nullable', 'url', 'max:500'],
                'volume'                 => ['nullable', 'string', 'max:20'],
                'issue'                  => ['nullable', 'string', 'max:20'],
                'pages'                  => ['nullable', 'string', 'max:30'],
                'raw_authors'            => ['nullable', 'string'],
                'indexing_ids'           => ['array'],
                'indexing_ids.*'         => ['integer', 'exists:indexings,id'],
                'student_author'              => ['boolean'],
                'national_collaboration'      => ['boolean'],
                'international_collaboration' => ['boolean'],
                'industries_collaboration'    => ['boolean'],
            ],
            'GRANT' => [
                'grant_project_id' => ['required', 'integer', 'exists:grant_projects,id'],
                'grant_role_id'    => ['required', 'integer', 'exists:grant_roles,id'],
                'allocated_amount' => ['nullable', 'numeric', 'min:0'],
            ],
            'IP_RECORD' => [
                'title'                     => ['required', 'string', 'max:500'],
                'ip_type_id'                => ['required', 'integer', 'exists:ip_types,id'],
                'ip_registration_status_id' => ['nullable', 'integer', 'exists:ip_registration_statuses,id'],
                'country_id'                => ['nullable', 'integer', 'exists:countries,id'],
                'ip_number'                 => ['nullable', 'string', 'max:100'],
                'filing_date'               => ['nullable', 'date'],
                'grant_date'                => ['nullable', 'date', 'after_or_equal:filing_date'],
                'raw_inventors'             => ['nullable', 'string'],
            ],
            'RESEARCH_INCOME' => [
                'source_name'        => ['required', 'string', 'max:255'],
                'income_category_id' => ['required', 'integer', 'exists:income_categories,id'],
                'amount'             => ['required', 'numeric', 'gt:0'],
                'year_received'      => ['required', 'integer', 'min:1950', 'max:2100'],
                'received_on'        => ['nullable', 'date'],
                'grant_project_id'   => ['nullable', 'integer', 'exists:grant_projects,id'],
            ],
            'AWARD' => [
                'title'          => ['required', 'string', 'max:500'],
                'award_type_id'  => ['nullable', 'integer', 'exists:award_types,id'],
                'award_level_id' => ['nullable', 'integer', 'exists:award_levels,id'],
                'organiser'      => ['nullable', 'string', 'max:255'],
                'award_year'     => ['required', 'integer', 'min:1950', 'max:2100'],
            ],
            default => throw new InvalidArgumentException("Unknown research type: {$typeCode}"),
        };
    }

    public function create(ResearchType $type, StaffProfile $owner, array $data): ResearchRecord
    {
        return DB::transaction(function () use ($type, $owner, $data) {
            [$effectiveDate, $precision] = $this->effectiveDate($type->code, $data);

            $record = ResearchRecord::create([
                'research_type_id'         => $type->id,
                'owner_staff_profile_id'   => $owner->id,
                'display_title'            => $this->displayTitle($type->code, $data),
                'effective_date'           => $effectiveDate,
                'effective_date_precision' => $precision,
            ]);

            $this->writeSubtype($type->code, $record, $data, creating: true);
            $this->audit->log(AuditLogger::RECORD_CREATED, $record, null, ['type' => $type->code]);

            return $record->refresh();
        });
    }

    public function update(ResearchRecord $record, array $data): ResearchRecord
    {
        $typeCode = $record->researchType->code;

        return DB::transaction(function () use ($record, $typeCode, $data) {
            $before = $record->only(['display_title', 'effective_date', 'effective_date_precision']);

            [$effectiveDate, $precision] = $this->effectiveDate($typeCode, $data);

            $record->update([
                'display_title'            => $this->displayTitle($typeCode, $data),
                'effective_date'           => $effectiveDate,
                'effective_date_precision' => $precision,
            ]);

            $this->writeSubtype($typeCode, $record, $data, creating: false);

            $this->audit->logChange(
                AuditLogger::RECORD_UPDATED,
                $record,
                $before,
                $record->only(['display_title', 'effective_date', 'effective_date_precision']),
            );

            return $record->refresh();
        });
    }

    /**
     * D4: the record's own effective date drives KPI credit.
     *
     * A grant with no start date and an IP record with no filing date land on
     * UNKNOWN rather than being guessed — 70 grants and all 18 IP records in
     * the 1.0 data are in exactly that state.
     */
    private function effectiveDate(string $typeCode, array $data): array
    {
        return match ($typeCode) {
            'PUBLICATION' => [Carbon::create((int) $data['pub_year'], 1, 1), 'YEAR'],
            'AWARD'       => [Carbon::create((int) $data['award_year'], 1, 1), 'YEAR'],
            'RESEARCH_INCOME' => isset($data['received_on'])
                ? [Carbon::parse($data['received_on']), 'DAY']
                : [Carbon::create((int) $data['year_received'], 1, 1), 'YEAR'],
            'IP_RECORD' => match (true) {
                ! empty($data['filing_date']) => [Carbon::parse($data['filing_date']), 'DAY'],
                ! empty($data['grant_date'])  => [Carbon::parse($data['grant_date']), 'DAY'],
                default                       => [null, 'UNKNOWN'],
            },
            'GRANT' => $this->grantEffectiveDate($data),
            default => [null, 'UNKNOWN'],
        };
    }

    private function grantEffectiveDate(array $data): array
    {
        $start = DB::table('grant_projects')
            ->where('id', $data['grant_project_id'] ?? 0)
            ->value('start_date');

        return $start ? [Carbon::parse($start), 'DAY'] : [null, 'UNKNOWN'];
    }

    private function displayTitle(string $typeCode, array $data): string
    {
        return match ($typeCode) {
            'PUBLICATION', 'IP_RECORD', 'AWARD' => $data['title'],
            'RESEARCH_INCOME' => $data['source_name'],
            'GRANT' => (string) DB::table('grant_projects')
                ->where('id', $data['grant_project_id'] ?? 0)
                ->value('title'),
            default => 'Research record',
        };
    }

    private function writeSubtype(string $typeCode, ResearchRecord $record, array $data, bool $creating): void
    {
        $table = $record->researchType->subtype_table;
        $key   = ['research_record_id' => $record->id];

        $payload = match ($typeCode) {
            'PUBLICATION' => [
                'journal_name' => $data['journal_name'] ?? null,
                'issn' => $data['issn'] ?? null,
                'pub_year' => $data['pub_year'],
                'volume' => $data['volume'] ?? null,
                'issue' => $data['issue'] ?? null,
                'pages' => $data['pages'] ?? null,
                'publication_type_id' => $data['publication_type_id'] ?? null,
                'author_role_id' => $data['author_role_id'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'quartile' => $data['quartile'] ?? 'N/A',
                'impact_factor' => $data['impact_factor'] ?? null,
                'doi' => $data['doi'] ?? null,
                'url' => $data['url'] ?? null,
                'raw_authors' => $data['raw_authors'] ?? null,
                'student_author' => $data['student_author'] ?? false,
                'national_collaboration' => $data['national_collaboration'] ?? false,
                'international_collaboration' => $data['international_collaboration'] ?? false,
                'industries_collaboration' => $data['industries_collaboration'] ?? false,
            ],
            'GRANT' => [
                'grant_project_id'       => $data['grant_project_id'],
                'grant_role_id'          => $data['grant_role_id'],
                'allocated_amount'       => $data['allocated_amount'] ?? null,
                'owner_staff_profile_id' => $record->owner_staff_profile_id,
            ],
            'IP_RECORD' => [
                'ip_type_id' => $data['ip_type_id'],
                'ip_registration_status_id' => $data['ip_registration_status_id'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'ip_number' => $data['ip_number'] ?? null,
                'filing_date' => $data['filing_date'] ?? null,
                'grant_date' => $data['grant_date'] ?? null,
                'raw_inventors' => $data['raw_inventors'] ?? null,
            ],
            'RESEARCH_INCOME' => [
                'grant_project_id'   => $data['grant_project_id'] ?? null,
                'income_category_id' => $data['income_category_id'],
                'source_name'        => $data['source_name'],
                'amount'             => $data['amount'],
                'currency'           => 'MYR',
                'year_received'      => $data['year_received'],
                'received_on'        => $data['received_on'] ?? null,
            ],
            'AWARD' => [
                'award_type_id'  => $data['award_type_id'] ?? null,
                'award_level_id' => $data['award_level_id'] ?? null,
                'organiser'      => $data['organiser'] ?? null,
                'award_year'     => $data['award_year'],
            ],
            default => [],
        };

        $payload['updated_at'] = now();

        if ($creating) {
            DB::table($table)->insert($key + $payload + ['created_at' => now()]);
        } else {
            DB::table($table)->where($key)->update($payload);
        }

        if ($typeCode === 'PUBLICATION') {
            $this->syncIndexings($record, $data['indexing_ids'] ?? []);
        }
    }

    /** Replaces the 1.0 SET column, whose `=` comparison hid multi-indexed papers. */
    private function syncIndexings(ResearchRecord $record, array $indexingIds): void
    {
        DB::table('publication_indexings')->where('research_record_id', $record->id)->delete();

        if ($indexingIds === []) {
            return;
        }

        DB::table('publication_indexings')->insert(
            collect($indexingIds)->unique()->map(fn ($id) => [
                'research_record_id' => $record->id,
                'indexing_id'        => $id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ])->all()
        );
    }
}

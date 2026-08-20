<?php

namespace App\Services\Kpi;

use App\Models\KpiTarget;
use App\Models\ResearchRecord;

/**
 * Evaluates kpi_target_criteria rows against a research record.
 *
 * Two 1.0 defects are fixed structurally here:
 *
 *  - `contains` handles set-valued facts like indexing. The 1.0 matcher used
 *    `p.indexing_type = 'Scopus'` against a MySQL SET, so every publication
 *    indexed 'Scopus,WoS' was invisible to a Scopus criterion — 4 of them.
 *
 *  - Criteria are rows referencing reference tables by code, so a criterion
 *    cannot name a value the vocabulary does not contain. The 1.0 grant-level
 *    criterion offered 'Universiti' while 32 rows of data said 'University',
 *    and the mismatch failed silently to zero.
 */
class CriteriaEvaluator
{
    public function matches(KpiTarget $target, ResearchRecord $record): bool
    {
        foreach ($target->criteria as $criterion) {
            if (! $this->test($criterion->field_path, $criterion->operator, $criterion->value, $record)) {
                return false;
            }
        }

        return true;
    }

    private function test(string $path, string $operator, string $expected, ResearchRecord $record): bool
    {
        $actual = $this->resolve($path, $record);

        return match ($operator) {
            '='        => $this->scalar($actual) == $expected,
            '!='       => $this->scalar($actual) != $expected,
            '>='       => (float) $this->scalar($actual) >= (float) $expected,
            '<='       => (float) $this->scalar($actual) <= (float) $expected,
            '>'        => (float) $this->scalar($actual) >  (float) $expected,
            '<'        => (float) $this->scalar($actual) <  (float) $expected,
            'in'       => in_array((string) $this->scalar($actual), array_map('trim', explode(',', $expected)), true),
            // Set-valued: actual is a list of codes.
            'contains' => in_array($expected, (array) $actual, true),
            default    => false,
        };
    }

    /**
     * Resolve a dotted field path against the record or its subtype.
     * 'quartile' -> subtype column; 'indexings' -> list of indexing codes.
     */
    private function resolve(string $path, ResearchRecord $record): mixed
    {
        if ($path === 'indexings') {
            return $record->publication?->indexings
                ->map(fn ($i) => $i->indexing?->code)
                ->filter()
                ->values()
                ->all() ?? [];
        }

        if (str_starts_with($path, 'record.')) {
            return data_get($record, substr($path, 7));
        }

        $detail = $record->detail();

        return $detail ? data_get($detail, $path) : null;
    }

    private function scalar(mixed $value): mixed
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}

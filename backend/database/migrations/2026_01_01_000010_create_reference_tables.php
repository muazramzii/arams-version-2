<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Controlled vocabularies.
 *
 * In ARAMS 1.0 these lived in three incompatible places: hardcoded arrays in
 * assets/js/research_forms.js, re-declared PHP arrays in api/update_lecturer_admin.php,
 * and ENUMs in the schema. Nothing kept them in sync, which is how 'University'
 * and 'Universiti' both ended up in tbl_grant, and how 52 of 57 rows in tbl_report
 * came to hold an empty string for report_type.
 *
 * Here every vocabulary is a table with a foreign key. Those states become
 * unrepresentable rather than merely discouraged.
 *
 * Deliberately NOT a single generic reference_values table — that pattern gives
 * up per-vocabulary foreign keys, which are the whole mechanism doing the work.
 */
return new class extends Migration
{
    /** Vocabularies that need nothing beyond the common shape. */
    private const SIMPLE = [
        'publication_types',
        'author_roles',
        'indexings',
        'countries',
        'grant_levels',
        'grant_roles',
        'grant_statuses',
        'funders',
        'income_categories',
        'ip_types',
        'ip_registration_statuses',
        'award_types',
        'award_levels',
        'positions',
        'grades',
        'researcher_statuses',
        'external_id_providers',
        'research_group_categories',
        'metric_sources',
    ];

    public function up(): void
    {
        foreach (self::SIMPLE as $name) {
            Schema::create($name, function (Blueprint $table) {
                $this->commonColumns($table);
            });
        }

        // Grant categories hang off a level. This encodes the UTHM/FRT cascade
        // (Universiti -> Tier 1, RE-GG, GPPS ...) in data. In 1.0 that map existed
        // only in JavaScript, so the database happily accepted mismatched values.
        Schema::create('grant_categories', function (Blueprint $table) {
            $this->commonColumns($table);
            $table->foreignId('grant_level_id')->nullable()
                  ->constrained('grant_levels')->nullOnDelete();
            $table->index('grant_level_id', 'idx_grantcat_level');
        });

        // The D6 extension point. Adding Research Projects or Postgraduate
        // Supervision later is one row here plus one subtype table — submissions,
        // KPI, audit, notifications and analytics are untouched.
        Schema::create('research_types', function (Blueprint $table) {
            $this->commonColumns($table);
            $table->string('subtype_table', 64);
            $table->string('model_class', 191);
            $table->boolean('requires_submission')->default(true);
            $table->string('effective_date_source', 64)
                  ->comment('Subtype column supplying effective_date (D4)');
            $table->string('icon', 40)->nullable();
        });
    }

    /** id · code · label · label_ms · sort_order · is_active · timestamps */
    private function commonColumns(Blueprint $table): void
    {
        $table->id();
        $table->string('code', 60)->unique();
        $table->string('label', 150);
        $table->string('label_ms', 150)->nullable();
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['is_active', 'sort_order'], 'idx_active_sort');
    }

    public function down(): void
    {
        Schema::dropIfExists('research_types');
        Schema::dropIfExists('grant_categories');

        foreach (array_reverse(self::SIMPLE) as $name) {
            Schema::dropIfExists($name);
        }
    }
};

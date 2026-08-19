<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — IP records, research income, and awards.
 *
 * Awards enter the submission workflow here. In 1.0 they attached straight to
 * a lecturer with no validation, yet kpi_autocomplete.php scored Award-type
 * KPI tasks against them — unvalidated data feeding an official performance
 * measure. Per the locked assumptions, awards become full research records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_records', function (Blueprint $table) {
            $table->foreignId('research_record_id')->primary()
                  ->constrained('research_records')->cascadeOnDelete();

            $table->foreignId('ip_type_id')->constrained('ip_types')->restrictOnDelete();
            $table->foreignId('ip_registration_status_id')->nullable()
                  ->constrained('ip_registration_statuses')->restrictOnDelete();
            $table->foreignId('country_id')->nullable()
                  ->constrained('countries')->restrictOnDelete();

            $table->string('ip_number', 100)->nullable()->comment('MyIPO reference');
            // All 18 rows in the 1.0 data have BOTH of these NULL, so every one
            // migrates with effective_date_precision = UNKNOWN.
            $table->date('filing_date')->nullable();
            $table->date('grant_date')->nullable();
            $table->text('raw_inventors')->nullable();

            $table->timestamps();

            $table->unique('ip_number', 'uq_ip_number');
            $table->index('filing_date', 'idx_ip_filing');
        });

        Schema::create('ip_inventors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_record_id')
                  ->constrained('ip_records', 'research_record_id')->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->nullable()
                  ->constrained('staff_profiles')->nullOnDelete();
            $table->string('person_name', 191);
            $table->unsignedSmallInteger('inventor_order');
            $table->string('affiliation_text', 255)->nullable();
            $table->timestamps();

            $table->unique(['research_record_id', 'inventor_order'], 'uq_ipinv_order');
            $table->index('staff_profile_id', 'idx_ipinv_staff');
        });

        Schema::create('research_incomes', function (Blueprint $table) {
            $table->foreignId('research_record_id')->primary()
                  ->constrained('research_records')->cascadeOnDelete();

            // Points at the project, not a participation — 70 of 72 rows in the
            // 1.0 data already link to a grant and none exceeds its parent amount.
            $table->foreignId('grant_project_id')->nullable()
                  ->constrained('grant_projects')->nullOnDelete();
            $table->foreignId('income_category_id')->constrained('income_categories')->restrictOnDelete();

            $table->string('source_name', 255);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('MYR');
            $table->unsignedSmallInteger('year_received');
            $table->date('received_on')->nullable();

            $table->timestamps();

            $table->index(['year_received', 'income_category_id'], 'idx_income_year');
            $table->index('grant_project_id', 'idx_income_project');
        });

        Schema::create('awards', function (Blueprint $table) {
            $table->foreignId('research_record_id')->primary()
                  ->constrained('research_records')->cascadeOnDelete();

            $table->foreignId('award_type_id')->nullable()
                  ->constrained('award_types')->restrictOnDelete();
            $table->foreignId('award_level_id')->nullable()
                  ->constrained('award_levels')->restrictOnDelete();
            $table->string('organiser', 255)->nullable();
            $table->unsignedSmallInteger('award_year');

            $table->timestamps();

            $table->index('award_year', 'idx_award_year');
        });

        DB::statement('ALTER TABLE `ip_records` ADD CONSTRAINT `chk_ip_dates`
                       CHECK (grant_date IS NULL OR filing_date IS NULL OR grant_date >= filing_date)');
        DB::statement('ALTER TABLE `research_incomes` ADD CONSTRAINT `chk_income_amount`
                       CHECK (amount > 0)');
        DB::statement('ALTER TABLE `research_incomes` ADD CONSTRAINT `chk_income_year`
                       CHECK (year_received BETWEEN 1950 AND 2100)');
        DB::statement('ALTER TABLE `awards` ADD CONSTRAINT `chk_award_year`
                       CHECK (award_year BETWEEN 1950 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
        Schema::dropIfExists('research_incomes');
        Schema::dropIfExists('ip_inventors');
        Schema::dropIfExists('ip_records');
    }
};

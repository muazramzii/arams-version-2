<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Publications.
 *
 * Two 1NF violations from 1.0 are resolved here:
 *
 *  - `authors` was a comma-separated string. It becomes publication_authors,
 *    which is what makes internal co-authorship, fractional credit across
 *    faculties, and honest duplicate detection possible. The original string
 *    is kept verbatim in raw_authors so nothing is lost in migration.
 *
 *  - `indexing_type` was a MySQL SET. The 1.0 KPI matcher compared it with
 *    `=`, so a publication indexed 'Scopus,WoS' never matched a 'Scopus'
 *    criterion — 4 publications were invisible to that filter. As a join
 *    table the test becomes an EXISTS, which cannot fail that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            // The subtype PK *is* the FK — this is what makes 1:1 structural.
            $table->foreignId('research_record_id')->primary()
                  ->constrained('research_records')->cascadeOnDelete();

            $table->string('journal_name', 255)->nullable();
            $table->string('issn', 20)->nullable();
            $table->unsignedSmallInteger('pub_year');
            $table->string('volume', 20)->nullable();
            $table->string('issue', 20)->nullable();
            $table->string('pages', 30)->nullable();

            $table->foreignId('publication_type_id')->nullable()
                  ->constrained('publication_types')->restrictOnDelete();
            $table->foreignId('author_role_id')->nullable()
                  ->constrained('author_roles')->restrictOnDelete();
            $table->foreignId('country_id')->nullable()
                  ->constrained('countries')->restrictOnDelete();

            // Closed set fixed by JCR/SJR, carries no metadata — an enum is right.
            $table->enum('quartile', ['Q1', 'Q2', 'Q3', 'Q4', 'N/A'])->default('N/A');
            $table->decimal('impact_factor', 6, 3)->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('url', 500)->nullable();

            $table->boolean('student_author')->default(false);
            $table->boolean('national_collaboration')->default(false);
            $table->boolean('international_collaboration')->default(false);
            $table->boolean('industries_collaboration')->default(false);

            $table->text('raw_authors')->nullable()
                  ->comment('Original 1.0 author string, preserved verbatim');

            $table->timestamps();

            // Prevents the 16 duplicate publications found in the 1.0 data.
            $table->unique('doi', 'uq_pub_doi');
            $table->index(['pub_year', 'quartile'], 'idx_pub_year_quartile');
        });

        Schema::create('publication_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_record_id')
                  ->constrained('publications', 'research_record_id')->cascadeOnDelete();
            // NULL for external co-authors who are not UTHM staff.
            $table->foreignId('staff_profile_id')->nullable()
                  ->constrained('staff_profiles')->nullOnDelete();
            $table->string('person_name', 191);
            $table->unsignedSmallInteger('author_order');
            $table->boolean('is_corresponding')->default(false);
            $table->boolean('is_student')->default(false);
            $table->string('affiliation_text', 255)->nullable();
            $table->timestamps();

            $table->unique(['research_record_id', 'author_order'], 'uq_pubauthor_order');
            $table->index('staff_profile_id', 'idx_pubauthor_staff');
        });

        Schema::create('publication_indexings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_record_id')
                  ->constrained('publications', 'research_record_id')->cascadeOnDelete();
            $table->foreignId('indexing_id')->constrained('indexings')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['research_record_id', 'indexing_id'], 'uq_pubindex');
            // Serves the indexing filter that replaced the broken SET match.
            $table->index(['indexing_id', 'research_record_id'], 'idx_pubindex_lookup');
        });

        DB::statement('ALTER TABLE `publications` ADD CONSTRAINT `chk_pub_year`
                       CHECK (pub_year BETWEEN 1950 AND 2100)');
        DB::statement('ALTER TABLE `publications` ADD CONSTRAINT `chk_pub_impact`
                       CHECK (impact_factor IS NULL OR impact_factor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_indexings');
        Schema::dropIfExists('publication_authors');
        Schema::dropIfExists('publications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Organisation structure.
 *
 * departments is new: ARAMS 1.0 held department as free text on tbl_lecturer,
 * which is why it drifted and could never be reported on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique();
            $table->string('name', 150);
            $table->string('name_ms', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['faculty_id', 'name'], 'uq_dept_faculty_name');
            $table->index(['faculty_id', 'is_active'], 'idx_dept_faculty');
        });

        Schema::create('research_groups', function (Blueprint $table) {
            $table->id();
            // Nullable: a university-wide group belongs to no single faculty.
            $table->foreignId('faculty_id')->nullable()
                  ->constrained('faculties')->nullOnDelete();
            $table->foreignId('research_group_category_id')->nullable()
                  ->constrained('research_group_categories')->nullOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['faculty_id', 'is_active'], 'idx_rgroup_faculty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_groups');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculties');
    }
};

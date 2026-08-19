<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Submission and validation.
 *
 * Fixes three defects measured in the 1.0 data:
 *
 *  1. decided_by references `users`, so any role can be recorded as reviewer.
 *     In 1.0 the only reviewer column was tbl_research_data.admin_id, with an
 *     FK to tbl_admin. A TDPP has no row there, so every TDPP approval wrote
 *     NULL — 108 of 272 approved records have no recorded approver.
 *
 *  2. submission_reviews is append-only, so decisions accumulate instead of
 *     overwriting. In 1.0 each decision overwrote the last: no history at all.
 *
 *  3. UNIQUE(research_record_id) enforces D3. 67 of 278 submissions in the
 *     1.0 data hold more than one research record, so a single Approve
 *     validated a bundle the reviewer could not decide on separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            // ── This constraint IS D3: one submission = one research record ──
            $table->foreignId('research_record_id')->unique()
                  ->constrained('research_records')->cascadeOnDelete();

            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'UNDER_REVIEW',
                'APPROVED',
                'REJECTED',
                'REVISION_REQUESTED',
                'WITHDRAWN',
                'SUPERSEDED',
            ])->default('DRAFT');

            $table->unsignedSmallInteger('current_revision')->default(1);

            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            // Routes the queue and is frozen: a transfer must not move a
            // pending item into another faculty's queue mid-review.
            $table->foreignId('faculty_id_at_submission')->nullable()
                  ->constrained('faculties')->restrictOnDelete();

            $table->dateTime('first_submitted_at')->nullable();
            $table->dateTime('submitted_at')->nullable()->comment('Latest submission or resubmission');

            // Prevents two TDPPs in a large faculty duplicating review work.
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('claimed_at')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();

            $table->enum('origin', ['ARAMS_2', 'MIGRATED_V1'])->default('ARAMS_2');
            $table->timestamps();

            // The TDPP validation queue.
            $table->index(['faculty_id_at_submission', 'status', 'submitted_at'], 'idx_sub_queue');
            // Queue-age monitoring and escalation.
            $table->index(['status', 'submitted_at'], 'idx_sub_status_age');
        });

        // Snapshot of the record as submitted, per revision — so "what did the
        // reviewer actually see when they rejected version 1?" has an answer.
        Schema::create('submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_no');
            $table->json('payload');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(['submission_id', 'revision_no'], 'uq_subrev');
        });

        // APPEND ONLY. The application exposes no update or delete path, and
        // the runtime database user is granted INSERT/SELECT only on this table.
        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_no');

            // Nullable only for migrated 1.0 rows whose approver was never
            // recorded. That provenance loss is permanent and must stay visible.
            $table->foreignId('reviewer_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // D1: TDPP is the only valid reviewer for new decisions.
            // ADMIN_LEGACY exists solely to carry 1.0 history and is rejected
            // on insert for any row with origin = ARAMS_2.
            $table->enum('reviewer_role', ['TDPP', 'ADMIN_LEGACY']);
            $table->enum('decision', ['APPROVED', 'REJECTED', 'REVISION_REQUESTED']);
            $table->text('remarks')->nullable();
            $table->dateTime('decided_at');
            $table->enum('origin', ['ARAMS_2', 'MIGRATED_V1'])->default('ARAMS_2');
            $table->timestamps();

            $table->index(['submission_id', 'revision_no', 'decided_at'], 'idx_review_timeline');
            $table->index('reviewer_user_id', 'idx_review_reviewer');
        });

        // Legal moves as data rather than as if-statements, so the state
        // machine is inspectable, testable, and auditable in one place.
        Schema::create('submission_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->enum('actor', ['OWNER', 'TDPP', 'ADMIN']);
            $table->boolean('requires_remarks')->default(false);
            $table->string('label', 80);
            $table->timestamps();

            $table->unique(['from_status', 'to_status', 'actor'], 'uq_transition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_transitions');
        Schema::dropIfExists('submission_reviews');
        Schema::dropIfExists('submission_revisions');
        Schema::dropIfExists('submissions');
    }
};

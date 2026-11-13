<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('application_no', 20)->nullable()->unique();
            $table->string('status', 20)->default('draft')->index(); // draft | submitted
            $table->json('completed_steps')->nullable();

            // 1. Basic details
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable()->index();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('family_income', 30)->nullable();
            $table->string('last_school')->nullable();
            $table->string('last_school_city')->nullable();

            // 2. Address
            $table->string('cur_line1')->nullable();
            $table->string('cur_line2')->nullable();
            $table->string('cur_city')->nullable();
            $table->foreignId('cur_state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('cur_district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('cur_district_other')->nullable();
            $table->string('cur_pin', 6)->nullable();
            $table->boolean('perm_same')->default(false);
            $table->string('perm_line1')->nullable();
            $table->string('perm_line2')->nullable();
            $table->string('perm_city')->nullable();
            $table->foreignId('perm_state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('perm_district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('perm_district_other')->nullable();
            $table->string('perm_pin', 6)->nullable();
            $table->foreignId('domicile_state_id')->nullable()->constrained('states')->nullOnDelete();

            // 3. Category, religion, PwBD
            $table->string('category_code', 20)->nullable()->index();
            $table->string('christian_subcategory', 20)->nullable()->index();
            $table->boolean('is_cst')->default(false);
            $table->text('baptism_details')->nullable(); // encrypted JSON
            $table->boolean('is_pwbd')->default(false)->index();
            $table->foreignId('pwbd_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('pwbd_percentage')->nullable();
            $table->text('udid_no')->nullable(); // encrypted
            $table->boolean('needs_scribe')->default(false);

            // 4. Class 10 & 12
            $table->string('c10_board')->nullable();
            $table->unsignedSmallInteger('c10_year')->nullable();
            $table->string('c10_roll_no', 30)->nullable();
            $table->unsignedSmallInteger('c10_max')->nullable();
            $table->decimal('c10_obtained', 7, 2)->nullable();
            $table->string('c12_board')->nullable();
            $table->unsignedSmallInteger('c12_year')->nullable();
            $table->string('c12_roll_no', 30)->nullable();

            // 5. CUET
            $table->string('cuet_roll_no', 20)->nullable();

            // 6. Courses
            $table->string('csas_application_no', 30)->nullable();
            $table->boolean('residence')->default(false)->index();

            // 7. Statement
            $table->text('statement_college')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('application_class12_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class12_subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('max_marks');
            $table->decimal('marks_obtained', 6, 2);
            $table->timestamps();
            $table->unique(['application_id', 'class12_subject_id']);
        });

        Schema::create('application_cuet_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 7, 2);
            $table->timestamps();
            $table->unique(['application_id', 'subject_id']);
        });

        Schema::create('application_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('du_preference');
            $table->unsignedSmallInteger('jmc_preference')->nullable();
            $table->boolean('jmc_not_applied')->default(false);
            $table->string('combination')->nullable();       // e.g. "PCM" or a B.A. Programme pair
            $table->json('papers');                          // subject ids counted for this course
            $table->decimal('cuet_score', 8, 2)->default(0); // sum of counted papers
            $table->text('statement')->nullable();
            $table->boolean('added_after_payment')->default(false);
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamps();
            $table->unique(['application_id', 'course_id']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->boolean('is_current')->default(true);
            $table->string('status', 20)->default('pending'); // pending | verified | rejected (step 4)
            $table->timestamps();
            $table->index(['application_id', 'type', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_courses');
        Schema::dropIfExists('application_cuet_scores');
        Schema::dropIfExists('application_class12_marks');
        Schema::dropIfExists('applications');
    }
};

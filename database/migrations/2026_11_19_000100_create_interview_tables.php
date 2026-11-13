<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One interview call letter per applicant per course.
        Schema::create('call_letters', function (Blueprint $table) {
            $table->id();
            $table->string('letter_no', 20)->nullable()->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_course_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->date('interview_date');
            $table->string('reporting_time', 20);
            $table->string('venue');
            $table->string('qr_token', 64)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->string('attendance', 10)->nullable(); // present | absent
            $table->timestamp('attendance_at')->nullable();
            $table->foreignId('attendance_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'interview_date']);
        });

        // Each panel member's interview marks out of 15; the merit list uses the average.
        Schema::create('interview_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $table->decimal('marks', 5, 2);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
            $table->unique(['call_letter_id', 'admin_id']);
        });

        // Seats offered in each allotment round.
        Schema::create('seat_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_no', 20)->nullable()->unique();
            $table->unsignedSmallInteger('round');
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('seat_category', 12);
            $table->unsignedInteger('merit_rank');
            $table->decimal('cuet_part', 6, 2);
            $table->decimal('interview_part', 6, 2);
            $table->decimal('total', 6, 2);
            $table->string('status', 12)->default('offered')->index(); // offered | accepted | declined | lapsed
            $table->timestamp('deadline_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('responded_ip', 45)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_offers');
        Schema::dropIfExists('interview_marks');
        Schema::dropIfExists('call_letters');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic scrutiny: one per paid course choice (by that course's department).
        // Religious scrutiny: one per Christian-category application (application_course_id is null).
        Schema::create('scrutinies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);                       // academic | religious
            $table->string('status', 20)->default('pending')->index(); // pending | query | approved | rejected
            $table->decimal('verified_cuet_score', 8, 2)->nullable();   // academic: CUET total as verified from the scorecard
            $table->json('checklist')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('reply_received')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'type', 'application_course_id']);
        });

        Schema::create('scrutiny_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrutiny_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('message');
            $table->json('document_types')->nullable();       // documents asked for
            $table->string('status', 20)->default('open')->index(); // open | answered | closed
            $table->string('token_hash', 64)->nullable();      // secure upload link (sha256 of the token)
            $table->timestamp('expires_at')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('reply_ip', 45)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->foreignId('query_id')->nullable()->after('application_id')->constrained('scrutiny_queries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('query_id');
        });
        Schema::dropIfExists('scrutiny_queries');
        Schema::dropIfExists('scrutinies');
    }
};

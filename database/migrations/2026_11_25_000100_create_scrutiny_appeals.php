<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One appeal per rejected scrutiny record, decided by the TFA admin. */
    public function up(): void
    {
        Schema::create('scrutiny_appeals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('scrutiny_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->text('rejection_remarks')->nullable();   // the rejection being appealed, kept as it was
            $t->text('reason');
            $t->string('status', 20)->default('pending'); // pending, upheld, allowed
            $t->text('decision_remarks')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index('status');
        });

        Schema::table('application_documents', function (Blueprint $t) {
            $t->foreignId('appeal_id')->nullable()->after('query_id')->constrained('scrutiny_appeals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('appeal_id');
        });
        Schema::dropIfExists('scrutiny_appeals');
    }
};

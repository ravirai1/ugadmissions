<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the scrutiny staff verified, item by item. The applicant's own data is never changed.
        Schema::create('scrutiny_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrutiny_id')->constrained()->cascadeOnDelete();
            $table->string('field', 60);                  // e.g. cuet_12, c12_34, baptism_date
            $table->string('label', 200);
            $table->text('applicant_value')->nullable();  // what the applicant entered, at the time of checking
            $table->text('verified_value')->nullable();   // what staff confirmed from the documents
            $table->string('result', 12);                 // ok | corrected
            $table->foreignId('verified_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scrutiny_id', 'field']);
        });

        Schema::table('scrutinies', function (Blueprint $table) {
            $table->decimal('verified_class12_percent', 5, 2)->nullable()->after('verified_cuet_score');
            $table->string('verified_subcategory', 20)->nullable()->after('verified_class12_percent');
            $table->boolean('verified_cst')->nullable()->after('verified_subcategory');
            $table->text('review_comment')->nullable()->after('remarks');
            $table->foreignId('review_by')->nullable()->after('review_comment')->constrained('admins')->nullOnDelete();
            $table->timestamp('review_at')->nullable()->after('review_by');
        });
    }

    public function down(): void
    {
        Schema::table('scrutinies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_by');
            $table->dropColumn(['verified_class12_percent', 'verified_subcategory', 'verified_cst', 'review_comment', 'review_at']);
        });
        Schema::dropIfExists('scrutiny_verifications');
    }
};

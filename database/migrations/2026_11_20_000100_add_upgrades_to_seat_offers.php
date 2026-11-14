<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_offers', function (Blueprint $table) {
            // The earlier offer this one replaced when the candidate moved up to a higher DU preference.
            $table->foreignId('upgraded_from_id')->nullable()->after('status')->constrained('seat_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seat_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgraded_from_id');
        });
    }
};

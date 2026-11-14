<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When and from where the applicant agreed to the Privacy Policy and Terms (DPDP Act, 2023). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('consent_at')->nullable();
            $t->string('consent_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['consent_at', 'consent_ip']);
        });
    }
};

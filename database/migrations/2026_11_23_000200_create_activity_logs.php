<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Separate activity logs for applicants and for staff: every login, logout, failed login and request that changes something (staff: every page too). */
    public function up(): void
    {
        foreach (['applicant_activity_logs' => ['user_id', 'users'], 'staff_activity_logs' => ['admin_id', 'admins']] as $table => [$fk, $on]) {
            Schema::create($table, function (Blueprint $t) use ($fk, $on) {
                $t->id();
                $t->foreignId($fk)->nullable()->constrained($on)->nullOnDelete();
                $t->string('login_id', 150)->nullable();   // email/CUET no. typed, for failed logins
                $t->string('event', 60);                    // login, logout, login_failed, or the route name
                $t->string('method', 8)->nullable();
                $t->string('path', 255)->nullable();
                $t->unsignedSmallInteger('status')->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 255)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index([$fk, 'created_at']);
                $t->index(['event', 'created_at']);
                $t->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_activity_logs');
        Schema::dropIfExists('staff_activity_logs');
    }
};

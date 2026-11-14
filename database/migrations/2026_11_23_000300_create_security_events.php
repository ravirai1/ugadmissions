<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Attack attempts (SQL injection, script injection, path probing, scanners) refused by DetectAttacks. */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20);
            $t->string('ip', 45);
            $t->string('method', 8);
            $t->string('path', 255);
            $t->string('field', 100)->nullable();
            $t->string('sample', 200)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->boolean('caused_block')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['ip', 'created_at']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};

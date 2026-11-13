<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('fee_paid_at')->nullable()->index();
            $table->boolean('residence_added_after_payment')->default(false);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 30)->unique();
            $table->string('purpose', 20)->index();          // application | add_course | add_residence
            $table->json('items');                            // what the amount covers
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('initiated')->index(); // initiated | pending | success | failed
            $table->boolean('is_duplicate')->default(false)->index();
            $table->string('gateway_ref', 50)->nullable()->index();      // SBIePay reference (ATRN)
            $table->string('bank_ref', 50)->nullable();
            $table->string('pay_mode', 30)->nullable();
            $table->string('gateway_status', 50)->nullable();
            $table->string('gateway_reason')->nullable();
            $table->string('receipt_no', 30)->nullable()->unique();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('check_count')->default(0);
            $table->string('initiated_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);   // redirect | push | status_check | reconcile | admin
            $table->string('status', 20)->nullable();
            $table->text('detail')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['fee_paid_at', 'residence_added_after_payment']);
        });
    }
};

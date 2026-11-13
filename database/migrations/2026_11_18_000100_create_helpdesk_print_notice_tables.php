<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Helpdesk: "chat" from logged-in applicants, "query" from anyone (public form).
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 20)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 10);                    // chat | query
            $table->string('name');
            $table->string('email');
            $table->string('mobile', 10)->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('open')->index(); // open | answered | closed
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('unread_for_staff')->default(true)->index();
            $table->boolean('unread_for_user')->default(false);
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('created_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10);                      // user | staff
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item', 40);                        // application | receipt | documents | bulk_applications | call_letter
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 20)->default('public'); // public | applicants | submitted | paid
            $table->boolean('is_published')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedInteger('emailed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
        Schema::dropIfExists('print_logs');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};

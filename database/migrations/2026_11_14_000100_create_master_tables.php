<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_ut')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['state_id', 'name']);
        });

        // Categories: DU categories and Christian sub-categories (parent = CHRISTIAN).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('parent_code', 20)->nullable()->index();
            $table->string('certificate_label')->nullable(); // document required when chosen
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // PwBD types (RPwD Act 2016 benchmark groups, as used by DU CSAS).
        Schema::create('pwbd_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('group_no');
            $table->string('group_name');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // CUET (NTA) subjects / test papers.
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20); // language | domain | general
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Class 12 subject names as boards print them, each mapped to the CUET paper it counts for.
        Schema::create('class12_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('offered_at_jmc')->default(false);
            $table->json('eligibility');           // see App\Services\Eligibility
            $table->json('seats')->nullable();     // per category, used in step 5
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('class12_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('pwbd_types');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
    }
};

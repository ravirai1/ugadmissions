<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('blood_group', 12)->nullable()->after('gender');
            $table->string('nationality', 40)->nullable()->after('blood_group');
            $table->string('mother_tongue', 40)->nullable()->after('nationality');
            $table->string('mother_tongue_other', 60)->nullable()->after('mother_tongue');
            foreach (['father', 'mother'] as $p) {
                $table->string("{$p}_title", 20)->nullable()->after('mother_name');
                $table->string("{$p}_qualification", 80)->nullable();
                $table->string("{$p}_occupation", 60)->nullable();
                $table->string("{$p}_designation", 100)->nullable();
                $table->string("{$p}_organisation", 150)->nullable();
                $table->string("{$p}_office_address", 300)->nullable();
                $table->string("{$p}_mobile", 10)->nullable();
                $table->string("{$p}_email", 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $cols = ['blood_group', 'nationality', 'mother_tongue', 'mother_tongue_other'];
            foreach (['father', 'mother'] as $p) {
                foreach (['title', 'qualification', 'occupation', 'designation', 'organisation', 'office_address', 'mobile', 'email'] as $c) {
                    $cols[] = "{$p}_{$c}";
                }
            }
            $table->dropColumn($cols);
        });
    }
};

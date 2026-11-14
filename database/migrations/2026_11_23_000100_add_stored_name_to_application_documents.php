<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Every uploaded file gets a unique name at upload time (see DocumentStore::uniqueName). */
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $t) {
            $t->string('stored_name', 120)->nullable()->after('path');
        });

        // Earlier uploads already had a random name; keep it as their stored name.
        DB::table('application_documents')->orderBy('id')->each(function ($d) {
            DB::table('application_documents')->where('id', $d->id)->update(['stored_name' => basename($d->path)]);
        });

        Schema::table('application_documents', function (Blueprint $t) {
            $t->unique('stored_name');
            $t->unique('path');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $t) {
            $t->dropUnique(['stored_name']);
            $t->dropUnique(['path']);
            $t->dropColumn('stored_name');
        });
    }
};

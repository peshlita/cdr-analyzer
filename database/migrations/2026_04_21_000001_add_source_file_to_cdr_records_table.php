<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdr_records', function (Blueprint $table) {
            $table->string('source_file')->nullable()->after('hour')->index();
        });
    }

    public function down(): void
    {
        Schema::table('cdr_records', function (Blueprint $table) {
            $table->dropIndex(['source_file']);
            $table->dropColumn('source_file');
        });
    }
};

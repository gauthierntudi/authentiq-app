<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ENCODAGES_PAGES', 'quality_score')) {
            return;
        }

        Schema::table('ENCODAGES_PAGES', function (Blueprint $table) {
            $table->decimal('quality_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ENCODAGES_PAGES', 'quality_score')) {
            return;
        }

        Schema::table('ENCODAGES_PAGES', function (Blueprint $table) {
            $table->decimal('quality_score', 3, 2)->nullable()->change();
        });
    }
};

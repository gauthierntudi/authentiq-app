<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ENCODAGES_PAGES')) {
            return;
        }

        Schema::table('ENCODAGES_PAGES', function (Blueprint $table) {
            if (! Schema::hasColumn('ENCODAGES_PAGES', 'textract_status')) {
                $table->string('textract_status', 20)->nullable()->after('ocr_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ENCODAGES_PAGES')) {
            return;
        }

        Schema::table('ENCODAGES_PAGES', function (Blueprint $table) {
            if (Schema::hasColumn('ENCODAGES_PAGES', 'textract_status')) {
                $table->dropColumn('textract_status');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ENCODAGES')) {
            return;
        }

        Schema::table('ENCODAGES', function (Blueprint $table) {
            if (! Schema::hasColumn('ENCODAGES', 'numero')) {
                $table->string('numero', 32)->nullable()->unique()->after('status');
            }
            if (! Schema::hasColumn('ENCODAGES', 'qr_path')) {
                $table->string('qr_path', 512)->nullable()->after('numero');
            }
        });

        DB::statement("ALTER TABLE `ENCODAGES` MODIFY COLUMN `status` ENUM('incomplete','complete','expired') NOT NULL DEFAULT 'incomplete'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('ENCODAGES')) {
            return;
        }

        DB::table('ENCODAGES')->where('status', 'expired')->update(['status' => 'complete']);

        DB::statement("ALTER TABLE `ENCODAGES` MODIFY COLUMN `status` ENUM('incomplete','complete') NOT NULL DEFAULT 'incomplete'");

        Schema::table('ENCODAGES', function (Blueprint $table) {
            if (Schema::hasColumn('ENCODAGES', 'qr_path')) {
                $table->dropColumn('qr_path');
            }
            if (Schema::hasColumn('ENCODAGES', 'numero')) {
                $table->dropColumn('numero');
            }
        });
    }
};

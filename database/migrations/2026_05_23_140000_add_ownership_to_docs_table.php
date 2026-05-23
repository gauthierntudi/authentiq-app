<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('DOCS')) {
            return;
        }

        if (! Schema::hasColumn('DOCS', 'ownership')) {
            Schema::table('DOCS', function (Blueprint $table) {
                $table->enum('ownership', ['single', 'multiple'])
                    ->default('single')
                    ->after('validite');
            });
        }

        DB::table('DOCS')->whereNull('ownership')->update(['ownership' => 'single']);
    }

    public function down(): void
    {
        if (Schema::hasTable('DOCS') && Schema::hasColumn('DOCS', 'ownership')) {
            Schema::table('DOCS', function (Blueprint $table) {
                $table->dropColumn('ownership');
            });
        }
    }
};

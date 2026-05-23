<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ENCODAGE_CLIENTS')) {
            return;
        }

        DB::statement('ALTER TABLE `ENCODAGE_CLIENTS` MODIFY `id_encodage` INT NOT NULL');
        DB::statement('ALTER TABLE `ENCODAGE_CLIENTS` MODIFY `id_client` INT NOT NULL');

        $foreignKeys = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ENCODAGE_CLIENTS'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        "))->pluck('CONSTRAINT_NAME');

        if (! $foreignKeys->contains('encodage_clients_id_encodage_foreign')) {
            Schema::table('ENCODAGE_CLIENTS', function (Blueprint $table) {
                $table->foreign('id_encodage')
                    ->references('id_encodage')
                    ->on('ENCODAGES')
                    ->cascadeOnDelete();
            });
        }

        if (! $foreignKeys->contains('encodage_clients_id_client_foreign')) {
            Schema::table('ENCODAGE_CLIENTS', function (Blueprint $table) {
                $table->foreign('id_client')
                    ->references('id_client')
                    ->on('CLIENTS')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ENCODAGE_CLIENTS')) {
            return;
        }

        Schema::table('ENCODAGE_CLIENTS', function (Blueprint $table) {
            $table->dropForeign(['id_encodage']);
            $table->dropForeign(['id_client']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ENCODAGE_CLIENTS')) {
            return;
        }

        Schema::create('ENCODAGE_CLIENTS', function (Blueprint $table) {
            $table->id('id');
            $table->integer('id_encodage');
            $table->integer('id_client');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_encodage', 'id_client'], 'encodage_clients_unique');
            $table->index('id_client', 'encodage_clients_client_idx');

            $table->foreign('id_encodage')
                ->references('id_encodage')
                ->on('ENCODAGES')
                ->cascadeOnDelete();
            $table->foreign('id_client')
                ->references('id_client')
                ->on('CLIENTS')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ENCODAGE_CLIENTS');
    }
};

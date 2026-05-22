<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('CLIENT_KYC_SUBMISSIONS')) {
            Schema::create('CLIENT_KYC_SUBMISSIONS', function (Blueprint $table) {
                $table->id('id_submission');
                $table->unsignedInteger('id_client');
                $table->string('status', 20)->default('pending');
                $table->string('type_piece_identite', 50)->nullable();
                $table->string('recto_path', 500)->nullable();
                $table->string('verso_path', 500)->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('reviewed_at')->nullable();

                $table->index(['id_client', 'status']);
            });
        }

        if (! Schema::hasTable('CLIENT_NOTIFICATIONS')) {
            Schema::create('CLIENT_NOTIFICATIONS', function (Blueprint $table) {
                $table->id('id_notification');
                $table->unsignedInteger('id_client');
                $table->string('type', 50)->default('info');
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['id_client', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('CLIENT_NOTIFICATIONS');
        Schema::dropIfExists('CLIENT_KYC_SUBMISSIONS');
    }
};

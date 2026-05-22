<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('CLIENTS') && ! Schema::hasColumn('CLIENTS', 'password')) {
            Schema::table('CLIENTS', function (Blueprint $table) {
                $table->string('password', 255)->nullable()->after('email');
                $table->timestamp('mobile_registered_at')->nullable()->after('password');
            });
        }

        if (! Schema::hasTable('CLIENT_API_TOKENS')) {
            Schema::create('CLIENT_API_TOKENS', function (Blueprint $table) {
                $table->id('id_token');
                $table->unsignedInteger('id_client');
                $table->string('token_hash', 64)->unique();
                $table->string('name', 100)->default('mobile');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('id_client');
            });
        }

        if (! Schema::hasTable('DOCUMENT_VERIFY_GRANTS')) {
            Schema::create('DOCUMENT_VERIFY_GRANTS', function (Blueprint $table) {
                $table->id('id_grant');
                $table->unsignedInteger('id_encodage');
                $table->unsignedInteger('id_client_owner');
                $table->unsignedInteger('id_client_grantee');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['id_client_grantee', 'id_encodage']);
                $table->index(['id_client_owner', 'id_encodage']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('DOCUMENT_VERIFY_GRANTS');
        Schema::dropIfExists('CLIENT_API_TOKENS');

        if (Schema::hasTable('CLIENTS')) {
            Schema::table('CLIENTS', function (Blueprint $table) {
                if (Schema::hasColumn('CLIENTS', 'mobile_registered_at')) {
                    $table->dropColumn('mobile_registered_at');
                }
                if (Schema::hasColumn('CLIENTS', 'password')) {
                    $table->dropColumn('password');
                }
            });
        }
    }
};

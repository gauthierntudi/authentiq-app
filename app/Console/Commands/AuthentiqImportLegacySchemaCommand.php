<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthentiqImportLegacySchemaCommand extends Command
{
    protected $signature = 'authentiq:import-legacy-schema
                            {--force : Réimporter même si des tables existent déjà}';

    protected $description = 'Importe database/schema-legacy.sql (tables CLIENTS, ENCODAGES, USERS, etc.)';

    public function handle(): int
    {
        if (Schema::hasTable('USERS') && ! $this->option('force')) {
            $this->info('Schéma legacy déjà présent (table USERS). Rien à importer.');

            return self::SUCCESS;
        }

        $path = database_path('schema-legacy.sql');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            $this->error('Fichier SQL vide ou illisible.');

            return self::FAILURE;
        }

        $this->info('Import du schéma legacy en cours…');

        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*\n/', $sql) ?: [];

        $run = 0;
        $skipped = 0;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '' || preg_match('/^(COMMIT|START TRANSACTION|SET\s)/i', $statement)) {
                    continue;
                }

                try {
                    DB::unprepared($statement);
                    $run++;
                } catch (\Throwable $e) {
                    if ($this->option('force') || ! str_contains($e->getMessage(), 'already exists')) {
                        $this->warn('Statement ignoré : '.substr($statement, 0, 60).'… — '.$e->getMessage());
                    }
                    $skipped++;
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info("Import terminé : {$run} requête(s) exécutée(s), {$skipped} ignorée(s).");

        return self::SUCCESS;
    }
}

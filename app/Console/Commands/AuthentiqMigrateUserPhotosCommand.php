<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserPhotoStorage;
use Illuminate\Console\Command;

class AuthentiqMigrateUserPhotosCommand extends Command
{
    protected $signature = 'authentiq:migrate-user-photos
                            {--dry-run : Affiche les actions sans modifier la base ni S3}';

    protected $description = 'Copie les photos utilisateurs (uploads locaux) vers le disque configuré (S3 en production)';

    public function handle(UserPhotoStorage $photos): int
    {
        $disk = $photos->diskName();
        $this->info("Disque photos utilisateurs : {$disk}");

        if ($disk !== 's3') {
            $this->warn('AUTHENTIQ_DOCUMENTS_DISK n\'est pas « s3 » — migration utile surtout en production Laravel Cloud.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $users = User::query()
            ->whereNotNull('photo')
            ->where('photo', '!=', '')
            ->orderBy('id_user')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Aucun utilisateur avec photo en base.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach ($users as $user) {
            $path = $user->photo;
            if ($dryRun) {
                $exists = $photos->url($path) !== null;
                $this->line(sprintf(
                    '  #%d %s — %s',
                    $user->id_user,
                    $user->nom_complet,
                    $exists ? 'déjà accessible' : 'à migrer (fichier local requis)'
                ));
                $exists ? $skip++ : $fail++;

                continue;
            }

            if ($photos->migrateUserPhoto($user)) {
                $user->refresh();
                $this->line("  OK #{$user->id_user} → {$user->photo}");
                $ok++;
            } else {
                $this->warn("  Échec #{$user->id_user} ({$path}) — fichier introuvable localement ni sur S3");
                $fail++;
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry-run : {$users->count()} utilisateur(s), {$skip} déjà OK, {$fail} à migrer."
            : "Terminé : {$ok} migré(s), {$fail} échec(s).");

        if ($fail > 0 && $disk === 's3') {
            $this->comment('Astuce : copiez public/uploads/users/ depuis Valet sur la machine de deploy, ou relancez depuis un poste qui a encore les fichiers.');
        }

        return $fail > 0 && ! $dryRun ? self::FAILURE : self::SUCCESS;
    }
}

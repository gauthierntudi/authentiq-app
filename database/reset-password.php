<?php

/**
 * Réinitialisation du mot de passe utilisateur (CLI, Laravel).
 *
 * Usage:
 *   php database/reset-password.php <email_ou_tel> <nouveau_mot_de_passe> [--admin]
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$login = $argv[1] ?? '';
$password = $argv[2] ?? '';
$setAdmin = in_array('--admin', $argv, true);

if ($login === '' || $password === '') {
    fwrite(STDERR, "Usage: php database/reset-password.php <email_ou_tel> <nouveau_mot_de_passe> [--admin]\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Le mot de passe doit faire au moins 8 caractères.\n");
    exit(1);
}

$user = User::query()
    ->where('email', $login)
    ->orWhere('tel', $login)
    ->first();

if (! $user) {
    fwrite(STDERR, "Aucun utilisateur trouvé pour: {$login}\n");
    exit(1);
}

$role = $setAdmin ? 'admin' : $user->role;

$user->password = password_hash($password, PASSWORD_DEFAULT);
$user->role = $role;
$user->save();

echo "OK — Mot de passe réinitialisé.\n";
echo "  ID      : {$user->id_user}\n";
echo "  Nom     : {$user->nom_complet}\n";
echo "  Email   : {$user->email}\n";
echo "  Téléphone : {$user->tel}\n";
echo "  Rôle    : {$role}\n";

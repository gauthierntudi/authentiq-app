# Déploiement Laravel Cloud — Authentiq

Après un `git push` sur GitHub, Laravel Cloud redéploie l’app. Configure **une fois** le dashboard, puis chaque push suffit.

## 1. Lier le dépôt GitHub

- Repo : `gauthierntudi/authentiq-app`
- Branche : `main`
- **Root du projet** : si le repo contient tout le monorepo, indique le sous-dossier `authentiq-laravel` comme racine de l’application. Si le repo **ne contient que** le dossier Laravel à la racine, laisse `/`.

## 2. Variables d’environnement (Laravel Cloud → Environment)

Copie-colle et remplis les valeurs (secrets Cloud) :

```env
APP_NAME=Authentiq
APP_ENV=production
APP_DEBUG=false
APP_KEY=                    # généré par Cloud ou php artisan key:generate
APP_URL=https://votre-app.cloud

APP_LOCALE=fr

DB_CONNECTION=mysql
DB_HOST=                    # fourni par Laravel Cloud MySQL
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

AUTHENTIQ_DOCUMENTS_DISK=s3
AUTHENTIQ_DOCUMENTS_PREFIX=fileAuthentiq
AUTHENTIQ_QUEUE=default
AUTHENTIQ_OTP_TTL_MINUTES=10

AUTHENTIQ_TEXTRACT_ENABLED=true
AUTHENTIQ_REKOGNITION_ENABLED=true
AUTHENTIQ_REKOGNITION_COLLECTION=authentiq-clients
AUTHENTIQ_REKOGNITION_MIN_SIMILARITY=85

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=                    # OBLIGATOIRE si AUTHENTIQ_DOCUMENTS_DISK=s3 (sinon erreur HeadObject / Bucket vide)

TWILIO_SID=
TWILIO_TOKEN=
TWILIO_WHATSAPP_SENDER=
TWILIO_WHATSAPP_TEMPLATE_SID=
TWILIO_WHATSAPP_OTP_VARIABLE=1

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
AUTHENTIQ_MAIL_FROM=
AUTHENTIQ_MAIL_FROM_NAME=Authentiq
```

**Object Storage Laravel Cloud** : alternative avec bucket R2 géré par Cloud. Si vous utilisez **votre propre bucket AWS S3** (`authentiq-files3`), voir la section 12 ci-dessous.

## 12. Bucket AWS S3 `authentiq-files3` (console Amazon)

Créer le compartiment suffit ; il reste **vide** tant que l’app n’a pas d’identifiants IAM valides. Aucun objet public n’est requis (les photos passent par `/api/clients/{id}/photo`).

### A. Utilisateur IAM (recommandé)

1. **IAM** → **Utilisateurs** → **Créer un utilisateur** (ex. `authentiq-laravel-cloud`).
2. **Politique** (JSON) limitée au bucket :

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AuthentiqS3Objects",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::authentiq-files3",
        "arn:aws:s3:::authentiq-files3/*"
      ]
    }
  ]
}
```

3. **Clés d’accès** → créer une clé **Application** → noter `AWS_ACCESS_KEY_ID` et `AWS_SECRET_ACCESS_KEY`.

Rekognition / Textract : si activés, attacher aussi les politiques AWS managées ou droits `rekognition:*` / `textract:*` sur la même région (`us-east-1`).

### B. Compartiment S3

| Paramètre | Valeur |
|-----------|--------|
| Nom | `authentiq-files3` |
| Région | `us-east-1` (Virginie du Nord — comme votre console) |
| Bloquer l’accès public | **Activé** (OK, l’app utilise les clés IAM) |
| CORS | **Non obligatoire** (affichage via proxy Laravel) |

Rien à « activer » dans l’onglet Objets : après le premier upload réussi, vous verrez des dossiers `clients/`, `fileAuthentiq/`, etc.

### C. Variables Laravel Cloud (Environment)

```env
AUTHENTIQ_DOCUMENTS_DISK=s3
AWS_BUCKET=authentiq-files3
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=AKIA...        # utilisateur IAM ci-dessus
AWS_SECRET_ACCESS_KEY=...
# Pas de AWS_ENDPOINT pour un vrai bucket S3 AWS (laisser vide)
```

Puis :

```bash
php artisan config:clear
php artisan authentiq:storage-status
```

Le test doit afficher **Test lecture/écriture S3 : OK**. Ensuite ré-uploader une photo client et vérifier dans S3 que `clients/client_4_....jpg` apparaît.

## 3. Commande de déploiement (obligatoire)

Dans **App cluster → Deployments → Deploy commands**, une seule ligne :

```bash
php artisan authentiq:deploy
```

Cette commande :

1. Importe `database/schema-legacy.sql` si la base est vide (tables `USERS`, `ENCODAGES`, etc.)
2. Lance les migrations Laravel (`jobs`, `cache`, `sessions`, colonnes Textract/QR)
3. Prépare Rekognition si activé
4. Met en cache config/routes/vues en production

**Ne pas** mettre `queue:work` dans les deploy commands (utiliser un background process).

## 4. Schéma phpMyAdmin (Valet) vs `schema-legacy.sql`

| Source | Contenu |
|--------|---------|
| **`schema-legacy.sql`** | Structure de base + colonnes Laravel (`numero`, `qr_path`, `textract_status`, `expired`). **Sans tes données.** |
| **phpMyAdmin Valet (`authentiq.db`)** | Ta base **réelle** : données + colonnes éventuelles ajoutées à la main (`extracted_data`, `qrcode_path`, `client_photo_path` — non utilisées par l’app Laravel). |

**Pour Cloud avec tes encodages/clients actuels**, exporte depuis Valet :

```bash
mysqldump -u root -p -h 127.0.0.1 -P 3306 authentiq.db > authentiq-valet-complet.sql
```

Importe `authentiq-valet-complet.sql` dans MySQL Cloud, puis deploy :

```bash
php artisan authentiq:deploy --skip-import
```

**Base Cloud vide** (première install) : laisse `authentiq:deploy` importer `schema-legacy.sql` + migrations.

## 5. Base déjà remplie (données locales)

Si tu as déjà importé un dump SQL sur Cloud :

```bash
php artisan authentiq:deploy --skip-import
```

Sinon l’import automatique détecte `USERS` et ne refait rien.

## 6. File d’attente Textract + Rekognition

**App cluster** ou **Worker cluster** → **Background processes** → ajouter :

```bash
php artisan queue:work --queue=default --tries=2 --timeout=120
```

## 7. Planificateur

**App cluster** → activer **Scheduler** (tâche `encodage:mark-expired` chaque nuit).

## 8. Premier push

```bash
cd authentiq-laravel
git add .
git commit -m "Configure deploy Laravel Cloud"
git push origin main
```

Puis vérifie les logs de déploiement sur Laravel Cloud.

## 9. Photos clients (S3)

Les **documents scannés** utilisent déjà `AUTHENTIQ_DOCUMENTS_DISK=s3`. Les **photos clients** utilisent le **même disque** : nouvelles captures → S3 automatiquement.

Les clients importés depuis Valet ont des chemins du type `uploads/clients/...` **sans fichier sur le serveur Cloud**. Après deploy :

1. Vérifier sur Cloud : `AUTHENTIQ_DOCUMENTS_DISK=s3`, `AWS_BUCKET`, clés AWS.
2. Depuis une machine qui a encore `public/uploads/clients/` (Valet), lancer une fois :
   ```bash
   php artisan authentiq:migrate-client-photos
   ```
   (Lit les fichiers locaux, les envoie sur S3, met à jour `CLIENTS.photo`.)

   Dry-run : `php artisan authentiq:migrate-client-photos --dry-run`

3. Réindexer Rekognition si besoin : `php artisan rekognition:index-clients`

Sans migration, la grille clients affiche l’avatar par défaut ; les nouvelles photos prises sur Cloud fonctionnent.

Les photos s’affichent via **`/api/clients/{id}/photo`** (proxy Laravel), pas via URL S3 directe — évite les erreurs CORS / 404 du navigateur sur `*.s3.amazonaws.com`.

## 9b. Photos utilisateurs (staff)

Même cause que les clients : les chemins `uploads/users/...` en base pointent vers **`public/uploads/users/`**, effacé à chaque redéploiement Laravel Cloud.

- **Nouvelles photos** (profil ou grille utilisateurs) → S3 automatiquement si `AUTHENTIQ_DOCUMENTS_DISK=s3`.
- **Anciennes photos** : migration one-shot depuis une machine qui a encore les fichiers locaux :

  ```bash
  php artisan authentiq:migrate-user-photos
  php artisan authentiq:migrate-user-photos --dry-run
  ```

Affichage : **`/api/users/{id}/photo`** (header, profil, grille admin).

## 10. Commandes manuelles (optionnel, console Cloud)

```bash
php artisan rekognition:index-clients
php artisan textract:enqueue-missing --sync
php artisan authentiq:migrate-client-photos
php artisan migrate:status
```

## 11. « Identifiants incorrects » après deploy

La base Cloud est **vide** ou mal connectée. Vérifiez :

```bash
php artisan authentiq:db-status
```

Si `USERS` = 0 lignes :

1. **Importer le dump Valet** dans MySQL Cloud (phpMyAdmin / TablePlus / CLI Cloud).
2. Ou importer le schéma + données de base :
   ```bash
   php artisan authentiq:import-legacy-schema
   ```
3. Puis créer/réinitialiser un mot de passe (console Cloud) :
   ```bash
   php artisan authentiq:reset-password gauthierntudi@gmail.com "VotreMotDePasse" --admin
   ```

Vérifiez aussi que les variables `DB_*` sur Cloud pointent vers la **même** base que celle où vous avez importé le SQL.

## 13. Mot de passe admin (Cloud ou local)

```bash
php artisan authentiq:reset-password email@example.com "NouveauMotDePasse" --admin
```

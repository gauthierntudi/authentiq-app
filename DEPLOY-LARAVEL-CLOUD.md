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
AWS_BUCKET=

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

## 9. Commandes manuelles (optionnel, console Cloud)

```bash
php artisan rekognition:index-clients
php artisan textract:enqueue-missing --sync
php artisan migrate:status
```

## 10. « Identifiants incorrects » après deploy

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

## 11. Mot de passe admin (Cloud ou local)

```bash
php artisan authentiq:reset-password email@example.com "NouveauMotDePasse" --admin
```

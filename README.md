# Authentiq — Laravel (projet actif)

Application Authentiq sous Laravel 13. Le code PHP à la racine du dépôt parent est **legacy** (non utilisé en production locale).

## Démarrage local

### Valet (recommandé, TLD `.web`)

```bash
cd authentiq-laravel   # depuis la racine du repo : cd authentiq-laravel
valet link authentiq
valet secure authentiq
```

- Connexion : **https://authentiq.web/accueil**
- `.env` : `APP_URL=https://authentiq.web`

### Artisan serve

```bash
php artisan serve
```

- Connexion : http://127.0.0.1:8000/accueil
- OTP : http://127.0.0.1:8000/verify
- Dashboard : http://127.0.0.1:8000/dashboard

## Base de données

Le projet utilise la même base MySQL que l'app legacy (`authentiq.db` sur le port MAMP `8889`).

Schéma de référence : `database/schema-legacy.sql`

## Assets & design

Copiés depuis le projet parent :

- `public/assets/` — thème admin
- `public/js/` — scripts encodage
- `public/ajax/` — grilles GridJS
- `public/bg/` — fond login/OTP

Les vues auth reprennent le HTML d'origine (`login-raw.blade.php`, `otp-raw.blade.php`) avec `<base href>` pour conserver les chemins relatifs.

## Architecture

| Legacy | Laravel |
|--------|---------|
| `index.php`, `otp.php` | `resources/views/auth/*-raw.blade.php` |
| `home.php` | `resources/views/pages/dashboard.blade.php` |
| `includes/header.php` | `resources/views/layouts/partials/header-raw.blade.php` |
| `php/login-user.php` | `POST /api/auth/login` |
| `php/verifyOtpUser.php` | `POST /api/auth/verify-otp` |
| `php/resendOtpUser.php` | `POST /api/auth/resend-otp` |
| `php/getUsers.php` | `GET /api/users` |
| `functions/*` | `app/Services/*` |

## Variables d'environnement (.env)

### WhatsApp OTP (Twilio)

```env
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_WHATSAPP_SENDER=+243846516270
TWILIO_WHATSAPP_TEMPLATE_SID=HX811dea26e1c8af3d049eb70a1f664c2d
TWILIO_WHATSAPP_OTP_VARIABLE=1
```

### Template Authentication (Meta / Twilio)

Type **whatsapp/authentication** — message imposé par Meta, par ex. :

> `403239` is your verification code. For your security, do not share this code.

Tu n’as **pas** à ajouter `{{1}}` ou `{{2}}` dans l’éditeur : Twilio injecte automatiquement `{{1}}` pour le code. À l’envoi, l’app envoie :

```json
{"1": "403239"}
```

Variable `.env` : `TWILIO_WHATSAPP_OTP_VARIABLE=1` (défaut, ne pas changer sauf instruction Twilio).

### Email OTP (SMTP)

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.gauthierntudi.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=authentiq@gauthierntudi.com
MAIL_PASSWORD=
AUTHENTIQ_MAIL_FROM=authentiq@gauthierntudi.com
```

## Prochaines étapes de migration

1. Migrer `users.php` + `ajax/gridUsers.js` → `/api/users` (déjà prêt côté API)
2. Clients, docs, géographie, encodage
3. Remplacer les pages `placeholder` par les Blade complets
4. Mettre à jour tous les `fetch('../php/...')` dans `public/ajax/`

## Réinitialiser un mot de passe

```bash
php database/reset-password.php email@example.com "NouveauMotDePasse" --admin
```

(Utilise `authentiq-laravel/.env` — MySQL port **3306**.)

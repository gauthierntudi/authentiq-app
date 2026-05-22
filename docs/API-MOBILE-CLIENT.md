# API mobile client (Flutter)

Base : `/api/mobile/client`  
Authentification staff (agents) : session cookie — routes `/api/*` avec `auth.user` + `role.staff`.

## Géographie (public, inscription)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/geo/provinces` | Liste des provinces |
| GET | `/geo/villes-by-province?id_province=1` | Villes d'une province |

## Inscription / connexion (public)

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/register` | Inscription autonome (`nom_complet`, `tel`, `id_province`, `id_ville`, `email?`, `password?`) → OTP |
| POST | `/verify-otp` | Active le compte + retourne `auth.token` Bearer |
| POST | `/send-otp` | Renvoyer OTP (`client_id` ou `tel`) |
| POST | `/login` | Connexion mot de passe (`login`, `password`) → Bearer |

## Authentifié (header `Authorization: Bearer {token}`)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/me` | Profil client |
| POST | `/logout` | Révoque les tokens |
| GET | `/documents` | Documents encodés du client |
| POST | `/documents/verify` | Scan QR (`numero`) — propriétaire ou avec autorisation |
| GET | `/documents/verify-grants` | Liste des autorisations accordées (`?encodage_id=`) |
| POST | `/documents/verify-grants` | Accorder accès (`id_encodage`, `grantee_tel` ou `grantee_id_client`, `expires_at?`) |
| DELETE | `/documents/verify-grants/{grantId}` | Révoquer une autorisation |

## Vérification document tiers

1. Client A scanne le QR d’un document de client B → `403` + `need_authorization: true`.
2. Client B accorde l’accès via `POST /documents/verify-grants`.
3. Client A rescane → `200` avec détails du document.

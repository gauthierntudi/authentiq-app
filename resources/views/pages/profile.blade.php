<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Mon profil | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta content="Authentiq" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <link rel="shortcut icon" href="assets/images/ico.png">
    <script src="assets/js/config.js"></script>
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <style>
        html[data-layout-mode="detached"]:not([data-layout="topnav"]) .wrapper {
            max-width: 95%;
            margin: 0px auto;
        }
        .form-control, .form-select {
            border: 2px solid #40465e;
            border-radius: 15px;
            color: #fff !important;
            background-color: transparent;
        }
        .form-control:focus, .form-select:focus {
            border: 2px solid #0eedee !important;
            background-color: transparent;
        }
        #photoDropZoneProfile {
            border: 2px dashed #40465e !important;
            border-radius: 16px;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        #photoDropZoneProfile:hover,
        #photoDropZoneProfile.dragover {
            border-color: #0eedee !important;
            background: rgba(14, 237, 238, 0.08);
        }
        #previewPhotoProfile {
            border: 4px solid #0eedee !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }
        .cropper-profile-wrap {
            min-height: 320px;
            max-height: min(55vh, 480px);
            background: #12131a;
            border-radius: 16px;
            overflow: hidden;
        }
        .cropper-profile-wrap > img {
            max-width: 100%;
            display: block;
        }
        .cropper-profile-wrap .cropper-container {
            width: 100% !important;
        }
        .cropper-profile-wrap .cropper-view-box,
        .cropper-profile-wrap .cropper-face {
            border-radius: 50%;
        }
        .cropper-profile-wrap .cropper-view-box {
            box-shadow: 0 0 0 1px rgba(14, 237, 238, 0.6);
            outline: none;
        }
        .cropper-profile-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .cropper-profile-toolbar .btn-tool {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 10px;
            border: 1px solid #40465e;
            background: rgba(64, 70, 94, 0.4);
            color: #c8cdd8;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .cropper-profile-toolbar .btn-tool:hover {
            border-color: #0eedee;
            color: #0eedee;
            background: rgba(14, 237, 238, 0.1);
        }
        .cropper-profile-preview-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8b93a8;
            margin-bottom: 0.65rem;
        }
        .cropper-profile-preview {
            width: 112px;
            height: 112px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #0eedee;
            background: #1a1b22;
        }
        .cropper-profile-preview .cropper-preview-circle {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .profile-sidebar-card {
            border-radius: 35px;
            overflow: visible;
            border: none;
        }
        .profile-sidebar-hero {
            position: relative;
            height: 96px;
            background: linear-gradient(135deg, #1a2744 0%, #2d3a5c 45%, #1e3a5f 100%);
            overflow: hidden;
            border-radius: 35px 35px 0 0;
        }
        .profile-sidebar-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(14, 237, 238, 0.25), transparent 55%);
        }
        .profile-sidebar-avatar-wrap {
            position: relative;
            z-index: 3;
            width: 116px;
            height: 116px;
            margin: -58px auto 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-sidebar-avatar {
            width: 108px;
            height: 108px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #0eedee;
            box-shadow: 0 0 0 4px rgba(14, 237, 238, 0.15), 0 12px 28px rgba(0, 0, 0, 0.45);
            display: block;
        }
        .profile-sidebar-body {
            text-align: center;
        }
        .profile-sidebar-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.35rem;
        }
        .profile-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.02em;
        }
        .profile-role-badge--admin {
            background: rgba(14, 237, 238, 0.15);
            color: #0eedee;
            border: 1px solid rgba(14, 237, 238, 0.35);
        }
        .profile-role-badge--user {
            background: rgba(108, 117, 125, 0.2);
            color: #adb5bd;
            border: 1px solid rgba(108, 117, 125, 0.35);
        }
        .profile-sidebar-section {
            text-align: left;
            margin-top: 1.35rem;
            padding-top: 1.15rem;
            border-top: 1px dashed rgba(255, 255, 255, 0.08);
        }
        .profile-sidebar-section__title {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8b93a8;
            margin-bottom: 0.85rem;
        }
        .profile-sidebar-section__title iconify-icon {
            font-size: 1.1rem;
            color: #0eedee;
        }
        .profile-info-row {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.65rem 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 14px;
            background: rgba(64, 70, 94, 0.35);
            border: 1px solid rgba(64, 70, 94, 0.6);
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .profile-info-row:hover {
            border-color: rgba(14, 237, 238, 0.35);
            background: rgba(14, 237, 238, 0.06);
        }
        .profile-info-row__icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(14, 237, 238, 0.12);
            color: #0eedee;
            font-size: 1.15rem;
        }
        .profile-info-row__label {
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8b93a8;
            margin-bottom: 0.1rem;
        }
        .profile-info-row__value {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #f0f2f8;
            word-break: break-word;
        }
        .profile-affectation-box {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.85rem 1rem;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(14, 237, 238, 0.08), rgba(64, 70, 94, 0.25));
            border: 1px solid rgba(14, 237, 238, 0.2);
            margin-bottom: 0.75rem;
        }
        .profile-affectation-box__icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(14, 237, 238, 0.15);
            color: #0eedee;
            font-size: 1.5rem;
        }
        .profile-affectation-box__label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8b93a8;
        }
        .profile-affectation-box__value {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }
        .profile-geo-trail {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .profile-geo-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.06);
            color: #c8cdd8;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .profile-geo-chip iconify-icon {
            font-size: 0.9rem;
            color: #0eedee;
        }
        .profile-geo-chip--empty {
            opacity: 0.5;
        }
        .profile-main-card .profile-tabs-wrap {
            margin: 0 -0.25rem;
        }
        .profile-underline-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            gap: 0.25rem;
            flex-wrap: nowrap;
        }
        .profile-underline-tabs .nav-item {
            margin-bottom: 0;
        }
        .profile-underline-tabs .nav-link {
            border: none !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: #8b93a8;
            font-weight: 600;
            font-size: 0.9375rem;
            padding: 0.85rem 1.35rem;
            margin-bottom: -1px;
            transition: color 0.2s ease, border-color 0.2s ease;
        }
        .profile-underline-tabs .nav-link:hover,
        .profile-underline-tabs .nav-link:focus {
            color: #c8cdd8;
            border: none !important;
            background: transparent !important;
            box-shadow: none;
        }
        .profile-underline-tabs .nav-link.active {
            color: #0eedee !important;
            background: transparent !important;
            border: none !important;
            border-bottom: 3px solid #0eedee !important;
        }
        .profile-tab-content {
            padding-top: 1.5rem !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        @include('layouts.partials.header-raw')

        <div class="page-content">
            <div class="page-container">
                <div class="row mt-1">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 fw-semibold m-0">Mon profil</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-xl-4 col-lg-5 mb-4">
                        <div class="card profile-sidebar-card border-0 p-0">
                            <div class="profile-sidebar-hero"></div>
                            <div class="profile-sidebar-avatar-wrap">
                                <img id="profilePagePhoto" src="{{ $profile['photo_url'] }}" alt="Photo" class="profile-sidebar-avatar">
                            </div>
                            <div class="profile-sidebar-body p-3 px-4 pb-4">
                                <h4 id="profileDisplayName" class="profile-sidebar-name mb-0">{{ $profile['nom_complet'] }}</h4>
                                <span id="profileDisplayRole" class="profile-role-badge profile-role-badge--{{ $profile['role'] === 'admin' ? 'admin' : 'user' }}">
                                    <iconify-icon icon="solar:shield-user-bold-duotone"></iconify-icon>
                                    <span id="profileDisplayRoleLabel">{{ $profile['role'] ?? 'utilisateur' }}</span>
                                </span>

                                <div class="profile-sidebar-section">
                                    <h6 class="profile-sidebar-section__title">
                                        <iconify-icon icon="solar:chat-round-dots-bold-duotone"></iconify-icon>
                                        Coordonnées
                                    </h6>
                                    <div class="profile-info-row">
                                        <span class="profile-info-row__icon">
                                            <iconify-icon icon="solar:phone-bold-duotone"></iconify-icon>
                                        </span>
                                        <span>
                                            <span class="profile-info-row__label">Téléphone</span>
                                            <span id="profileDisplayTel" class="profile-info-row__value">{{ $profile['tel'] }}</span>
                                        </span>
                                    </div>
                                    <div class="profile-info-row">
                                        <span class="profile-info-row__icon">
                                            <iconify-icon icon="solar:letter-bold-duotone"></iconify-icon>
                                        </span>
                                        <span>
                                            <span class="profile-info-row__label">Email</span>
                                            <span id="profileDisplayEmail" class="profile-info-row__value">{{ $profile['email'] }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="profile-sidebar-section">
                                    <h6 class="profile-sidebar-section__title">
                                        <iconify-icon icon="solar:map-point-bold-duotone"></iconify-icon>
                                        Affectation
                                    </h6>
                                    <div class="profile-affectation-box">
                                        <span class="profile-affectation-box__icon">
                                            <iconify-icon icon="solar:map-point-hospital-bold-duotone"></iconify-icon>
                                        </span>
                                        <span>
                                            <span class="profile-affectation-box__label">Commune</span>
                                            <span id="profileDisplayAffectation" class="profile-affectation-box__value d-block">{{ $profile['affectation'] ?: ($profile['nom_commune'] ?? '—') }}</span>
                                        </span>
                                    </div>
                                    <div class="profile-geo-trail">
                                        <span id="profileChipProvince" class="profile-geo-chip {{ empty($profile['nom_province']) ? 'profile-geo-chip--empty' : '' }}">
                                            <iconify-icon icon="solar:global-bold-duotone"></iconify-icon>
                                            <span id="profileDisplayProvince">{{ $profile['nom_province'] ?? 'Province' }}</span>
                                        </span>
                                        <span id="profileChipVille" class="profile-geo-chip {{ empty($profile['nom_ville']) ? 'profile-geo-chip--empty' : '' }}">
                                            <iconify-icon icon="solar:city-bold-duotone"></iconify-icon>
                                            <span id="profileDisplayVille">{{ $profile['nom_ville'] ?? 'Ville' }}</span>
                                        </span>
                                        <span id="profileChipCommune" class="profile-geo-chip {{ empty($profile['nom_commune']) ? 'profile-geo-chip--empty' : '' }}">
                                            <iconify-icon icon="solar:home-2-bold-duotone"></iconify-icon>
                                            <span id="profileDisplayCommune">{{ $profile['nom_commune'] ?? 'Commune' }}</span>
                                        </span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8 col-lg-7">
                        <div class="card p-3 profile-main-card" style="border-radius:35px">
                            <div class="profile-tabs-wrap">
                                <ul class="nav nav-tabs profile-underline-tabs border-0" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-infos" type="button" role="tab">Informations</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-password" type="button" role="tab">Mot de passe</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content profile-tab-content">
                                <div class="tab-pane fade show active" id="tab-infos">
                                    <form id="formProfileInfo">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="profileNom" value="{{ $profile['nom_complet'] }}" required>
                                                    <label for="profileNom">Nom complet</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="profileTel" value="{{ $profile['tel'] }}" required>
                                                    <label for="profileTel">Téléphone</label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <div class="form-floating">
                                                    <input type="email" class="form-control" id="profileEmail" value="{{ $profile['email'] }}" required>
                                                    <label for="profileEmail">Email</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <div class="form-floating">
                                                    <select class="form-select" id="profileProvince"></select>
                                                    <label for="profileProvince">Province</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <div class="form-floating">
                                                    <select class="form-select" id="profileVille"></select>
                                                    <label for="profileVille">Ville</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <div class="form-floating">
                                                    <select class="form-select" id="profileCommune"></select>
                                                    <label for="profileCommune">Commune</label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="profileAffectation" value="{{ $profile['affectation'] }}" readonly>
                                                    <label for="profileAffectation">Affectation (commune)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label text-muted small mb-2">Photo de profil</label>
                                                <div id="photoDropZoneProfile" class="p-4 text-center d-flex flex-column justify-content-center align-items-center" style="cursor:pointer; min-height:120px;">
                                                    <iconify-icon icon="solar:gallery-send-line-duotone" style="font-size:2rem; color:#0eedee;"></iconify-icon>
                                                    <p class="mb-0 mt-2 text-muted">Glisser-déposer ou cliquer pour choisir</p>
                                                    <p class="mb-0 small text-muted opacity-75">JPG, PNG — rognage circulaire</p>
                                                    <input type="file" id="profilePhotoInput" accept="image/*" style="display:none;">
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3 text-center">
                                                <label class="form-label text-muted small mb-2 d-block">Aperçu</label>
                                                <img id="previewPhotoProfile" src="{{ $profile['photo_url'] }}" alt="Aperçu" width="100" height="100" style="width:100px;height:100px;object-fit:cover;border-radius:50%;display:block;margin:0 auto;">
                                            </div>
                                        </div>
                                        <button type="submit" id="btnSaveProfile" class="btn btn-primary btn-lg fw-semibold" style="border-radius:15px;">Enregistrer</button>
                                    </form>
                                </div>

                                <div class="tab-pane fade" id="tab-password">
                                    <form id="formProfilePassword">
                                        <div class="mb-3">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="profileCurrentPassword">
                                                <label for="profileCurrentPassword">Mot de passe actuel</label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="profileNewPassword">
                                                <label for="profileNewPassword">Nouveau mot de passe</label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="profileConfirmPassword">
                                                <label for="profileConfirmPassword">Confirmer le mot de passe</label>
                                            </div>
                                        </div>
                                        <button type="submit" id="btnSavePassword" class="btn btn-primary btn-lg fw-semibold" style="border-radius:15px;">Changer le mot de passe</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.partials.footer-raw')
    </div>

    <div class="modal fade" id="cropperModalProfile" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content" style="border-radius:35px!important">
                <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                    <h5 class="modal-title mb-0">
                        Rogner la photo
                        <br>
                        <span class="fw-normal" style="font-size:.8em;">Ajustez le cadrage de votre photo de profil</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-8">
                            <div class="cropper-profile-wrap">
                                <img id="cropperImageProfile" src="" alt="Image à rogner">
                            </div>
                            <div class="cropper-profile-toolbar">
                                <button type="button" class="btn-tool" data-crop-action="zoom-in" title="Zoomer">
                                    <iconify-icon icon="solar:magnifer-zoom-in-bold-duotone"></iconify-icon>
                                    Zoom +
                                </button>
                                <button type="button" class="btn-tool" data-crop-action="zoom-out" title="Dézoomer">
                                    <iconify-icon icon="solar:magnifer-zoom-out-bold-duotone"></iconify-icon>
                                    Zoom −
                                </button>
                                <button type="button" class="btn-tool" data-crop-action="rotate-left" title="Pivoter">
                                    <iconify-icon icon="solar:restart-bold-duotone"></iconify-icon>
                                    Gauche
                                </button>
                                <button type="button" class="btn-tool" data-crop-action="rotate-right" title="Pivoter">
                                    <iconify-icon icon="solar:restart-bold-duotone" style="transform:scaleX(-1)"></iconify-icon>
                                    Droite
                                </button>
                                <button type="button" class="btn-tool" data-crop-action="reset" title="Réinitialiser">
                                    <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon>
                                    Réinitialiser
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <p class="cropper-profile-preview-label mb-0">Aperçu profil</p>
                            <div class="cropper-profile-preview">
                                <div class="cropper-preview-circle"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <a class="btn btn-light btn-lg fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Annuler</a>
                    <button type="button" id="cropperValidateProfile" class="btn btn-primary btn-lg fw-semibold" style="border-radius:12px">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                        Valider le rognage
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        window.PROFILE_INITIAL = @json($profile);
    </script>
    <script src="{{ asset('ajax/profile.js') }}"></script>
</body>
</html>

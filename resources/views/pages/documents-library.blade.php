<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">
<head>
    <meta charset="utf-8" />
    <title>Documents encodés | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">
    <link rel="shortcut icon" href="assets/images/ico.png">
    <script src="assets/js/config.js"></script>
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/authentiq-encodage.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/authentiq-documents-library.css') }}" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <style>
        html[data-layout-mode="detached"]:not([data-layout="topnav"]) .wrapper { max-width: 95%; margin: 0 auto; }
        .border-radius { border-radius: 40px; }
    </style>
</head>
<body>
<div class="wrapper">
    @include('layouts.partials.header-raw')

    <div class="page-content">
        <div class="page-container doc-lib" id="documents-library-app">

            <div class="doc-lib__header">
                <div>
                    <h4 class="fs-18">Mes documents</h4>
                    <p id="docLibSubtitle">Tous les documents encodés, classés par client</p>
                </div>
                <button type="button" class="btn btn-light" style="border-radius:14px;" data-bs-toggle="offcanvas" data-bs-target="#docLibFilterPanel">
                    <iconify-icon icon="solar:filter-bold-duotone"></iconify-icon>
                    Filtrer
                </button>
            </div>

            <div class="doc-lib-folders" id="docLibFolders" aria-label="Clients"></div>

            <div class="doc-lib-layout">
                <div class="doc-lib-panel">
                    <div class="doc-lib-panel__head">
                        <h5 class="doc-lib-panel__title" id="docLibListTitle">Fichiers récents</h5>
                        <span class="text-muted small" id="docLibListCount"></span>
                    </div>
                    <ul class="doc-lib-file-list" id="docLibFileList"></ul>
                    <div class="doc-lib-empty d-none" id="docLibEmpty">
                        <iconify-icon icon="solar:folder-open-bold-duotone" style="font-size:3rem;"></iconify-icon>
                        <p class="mt-2 mb-0">Aucun document pour cette sélection</p>
                    </div>
                </div>

                <aside class="doc-lib-panel doc-lib-aside">
                    <div class="doc-lib-aside__hero">
                        <iconify-icon icon="solar:cloud-storage-bold-duotone"></iconify-icon>
                    </div>
                    <div class="doc-lib-storage-ring">
                        <div id="docLibStorageChart"></div>
                        <div class="doc-lib-storage-ring__label">
                            <span id="docLibStorageValue">0</span>
                            <small>documents</small>
                        </div>
                    </div>
                    <div id="docLibStats"></div>
                    <ul class="doc-lib-file-list mt-3" id="docLibAsideRecent" style="max-height:200px;"></ul>
                    <div class="doc-lib-cta">
                        <iconify-icon icon="solar:folder-with-files-bold-duotone" style="font-size:2rem;color:#fff;"></iconify-icon>
                        <p class="text-white mb-0 small">Encoder un nouveau document</p>
                        <a href="{{ url('/encodage-document') }}">
                            <iconify-icon icon="solar:add-circle-bold"></iconify-icon>
                            Nouvel encodage
                        </a>
                    </div>
                </aside>
            </div>

        </div>

        @include('layouts.partials.footer-raw')
    </div>
</div>

<div class="offcanvas offcanvas-end doc-lib-filter-offcanvas" tabindex="-1" id="docLibFilterPanel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Filtres</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-3">
            <label class="form-label" for="docLibSearch">Recherche</label>
            <input type="search" id="docLibSearch" class="form-control" placeholder="Client, ID, affectation…">
        </div>
        <div class="mb-3">
            <label class="form-label" for="docLibStatus">Statut</label>
            <select id="docLibStatus" class="form-select">
                <option value="">Tous</option>
                <option value="complete">Finalisés</option>
                <option value="incomplete">En cours</option>
                <option value="expired">Expirés</option>
            </select>
        </div>
        <button type="button" class="btn btn-primary w-100" id="docLibApplyFilter" style="border-radius:12px;">Appliquer</button>
        <button type="button" class="btn btn-light w-100 mt-2" id="docLibResetFilter" style="border-radius:12px;">Réinitialiser</button>
    </div>
</div>

@include('pages.partials.encodage-pages-viewer-modal')

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="{{ asset('ajax/documentsLibrary.js') }}"></script>
</body>
</html>

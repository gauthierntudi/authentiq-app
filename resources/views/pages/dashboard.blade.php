<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Dashboard | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta content="Authentiq" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/ico.png">

    <!-- Theme Config Js -->
    <script src="assets/js/config.js"></script>

    <!-- gridjs css (avant app.min.css pour que le thème sombre de l’app s’applique) -->
    <link rel="stylesheet" href="assets/vendor/gridjs/theme/mermaid.min.css">

    <!-- Vendor css -->
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css -->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

    <!-- Assurez-vous d’inclure iziToast -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <style>
        html[data-layout-mode="detached"]:not([data-layout="topnav"]) .wrapper {
            max-width: 95%;
            margin: 0px auto;
        }

        .custom--home--container {
            width: auto;
            margin: 0 auto;
        }

        .custom--home--buttons-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .custom--home--button-wrapper {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .custom--home--button {
            background: #40465e;
            color: white;
            border: none;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0);
        }

        .custom--home--button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0);
            background: #4766e7;
        }

        .custom--home--button:active {
            transform: translateY(0);
        }

        .custom--home--button-icon {
            font-size: 36px;
            margin-bottom: 8px;
            width: 36px;
            height: 36px;
        }

        .custom--home--button-text {
            font-size: 12px;
            font-weight: 500;
            color: white;
            text-align: center;
            line-height: 1.2;
        }

        .dashboard-stats-row {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.65rem;
            margin-top: 1rem;
        }
        .dashboard-stats-row .dashboard-stat-card {
            padding: 1rem 0.85rem;
            gap: 0.65rem;
        }
        .dashboard-stats-row .dashboard-stat-card__icon {
            width: 42px;
            height: 42px;
            font-size: 1.35rem;
            border-radius: 12px;
        }
        .dashboard-stats-row .stat-value {
            font-size: 1.45rem;
        }
        .dashboard-stats-row .stat-label {
            font-size: 0.62rem;
        }
        .dashboard-stats-row .stat-hint {
            font-size: 0.62rem;
            line-height: 1.25;
        }
        @media (max-width: 1199px) {
            .dashboard-stats-row {
                grid-template-columns: repeat(6, minmax(140px, 1fr));
                overflow-x: auto;
                padding-bottom: 0.25rem;
                scrollbar-width: thin;
            }
        }
        .dashboard-stat-card {
            --stat-accent: #6b7fd4;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.15rem 1.35rem;
            border-radius: 20px;
            background: rgba(48, 54, 72, 0.55);
            border: 1px solid rgba(64, 70, 94, 0.5);
            height: 100%;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        a.dashboard-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
            border-color: rgba(14, 237, 238, 0.25);
        }
        .dashboard-stat-card__icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.65rem;
            background: rgba(255, 255, 255, 0.06);
            color: var(--stat-accent);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .dashboard-stat-card__body {
            flex: 1;
            min-width: 0;
        }
        .dashboard-stat-card .stat-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8b93a8;
            margin-bottom: 0.2rem;
            font-weight: 600;
        }
        .dashboard-stat-card .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.1;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .dashboard-stat-card .stat-hint {
            font-size: 0.72rem;
            color: #6b7289;
            margin-top: 0.25rem;
        }
        .dashboard-stat-card--total { --stat-accent: #8b9cff; }
        .dashboard-stat-card--incomplete { --stat-accent: #f9c45c; }
        .dashboard-stat-card--incomplete .stat-value { color: #f9c45c; }
        .dashboard-stat-card--complete { --stat-accent: #0eedee; }
        .dashboard-stat-card--complete .stat-value { color: #0eedee; }
        .dashboard-stat-card--expired { --stat-accent: #f87171; }
        .dashboard-stat-card--expired .stat-value { color: #f87171; }
        .dashboard-stat-card--agents { --stat-accent: #a78bfa; }
        .dashboard-stat-card--agents .stat-value { color: #c4b5fd; }
        .dashboard-stat-card--clients { --stat-accent: #34d399; }
        .dashboard-stat-card--clients .stat-value { color: #6ee7b7; }
        .encodage-status-badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .encodage-status-badge--complete {
            background: rgba(14, 237, 238, 0.15);
            color: #0eedee;
            border: 1px solid rgba(14, 237, 238, 0.35);
        }
        .encodage-status-badge--incomplete {
            background: rgba(249, 196, 92, 0.15);
            color: #f9c45c;
            border: 1px solid rgba(249, 196, 92, 0.35);
        }
        .encodage-status-badge--expired {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.35);
        }
        .enc-table-client {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
        }
        .enc-table-client__photo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid rgba(14, 237, 238, 0.35);
            background: #12151f;
        }
        .enc-table-client__name {
            font-size: 0.88rem;
            font-weight: 600;
            color: #e8ecf4;
            line-height: 1.3;
            word-break: break-word;
        }
        .gridjs-th-content { font-weight: 800 !important; }
        /* Tableau encodages — thème sombre */
        #table-encodages .gridjs-container { color: #e8ecf4; }
        #table-encodages .gridjs-wrapper,
        #table-encodages .gridjs-footer {
            background: transparent !important;
            border-color: rgba(64, 70, 94, 0.55) !important;
            box-shadow: none !important;
        }
        #table-encodages th.gridjs-th {
            background: rgba(64, 70, 94, 0.55) !important;
            color: #c8cdd8 !important;
            border-color: rgba(64, 70, 94, 0.45) !important;
        }
        #table-encodages th.gridjs-th-sort:hover,
        #table-encodages th.gridjs-th-sort:focus {
            background: rgba(64, 70, 94, 0.75) !important;
        }
        #table-encodages td.gridjs-td {
            background: rgba(26, 30, 42, 0.55) !important;
            color: #e8ecf4 !important;
            border-color: rgba(64, 70, 94, 0.35) !important;
        }
        #table-encodages .gridjs-pagination,
        #table-encodages .gridjs-summary { color: #8b93a8 !important; }
        #table-encodages .gridjs-pagination .gridjs-pages button {
            background: #2e3344 !important;
            border-color: #40465e !important;
            color: #e8ecf4 !important;
        }
        #table-encodages .gridjs-pagination .gridjs-pages button:hover:not(:disabled) {
            background: rgba(64, 70, 94, 0.85) !important;
            color: #0eedee !important;
        }
        #table-encodages .gridjs-pagination .gridjs-pages button.gridjs-currentPage {
            background: #0eedee !important;
            color: #1a1e2a !important;
            border-color: #0eedee !important;
        }
        #table-encodages .gridjs-loading-bar {
            background-color: rgba(26, 30, 42, 0.85) !important;
        }
        #table-encodages button:is(.gridjs-sort-neutral, .gridjs-sort-asc, .gridjs-sort-desc) {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .border-radius { border-radius: 40px; }
        #filterStatus, #filterPeriod, #filterSearch {
            border: 2px solid #40465e;
            border-radius: 15px;
            color: #fff !important;
            background-color: transparent;
        }
        #filterStatus:focus, #filterPeriod:focus, #filterSearch:focus {
            border-color: #0eedee !important;
            background-color: transparent;
        }
        .encodages-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
        }
        .encodages-filters__left {
            flex: 1 1 auto;
            text-align: left;
        }
        .encodages-filters__left > .d-flex {
            justify-content: flex-start !important;
        }
        .encodages-filters__left .encodages-filter-field {
            text-align: left;
        }
        .encodages-filters__left .encodages-filter-field .form-label {
            text-align: left;
            display: block;
        }
        .encodages-filters__left .encodages-filter-field--status {
            min-width: 150px;
        }
        .encodages-filters__left .encodages-filter-field--period {
            min-width: 200px;
        }
        .encodages-filters__left .encodages-filter-field--search {
            min-width: 220px;
            flex: 1 1 240px;
            max-width: 360px;
        }
        .encodages-filters__right {
            flex: 0 0 auto;
            text-align: right;
        }
        .encodages-filters__right > .d-flex {
            justify-content: flex-end !important;
        }
        .encodages-filters__right .btn {
            white-space: nowrap;
        }
        .btn-filter-reset {
            width: 42px;
            height: 42px;
            min-width: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #40465e !important;
            background: rgba(64, 70, 94, 0.45) !important;
            color: #c8cdd8 !important;
            flex-shrink: 0;
            transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
        }
        .btn-filter-reset iconify-icon {
            font-size: 1.45rem;
            line-height: 1;
        }
        .btn-filter-reset:hover {
            border-color: #0eedee !important;
            color: #0eedee !important;
            background: rgba(14, 237, 238, 0.12) !important;
        }

        @media (max-width: 768px) {
            .custom--home--buttons-container {
                gap: 15px;
            }
            
            .custom--home--button {
                width: 100px;
                height: 100px;
            }
            
            .custom--home--button-icon {
                font-size: 32px;
                width: 32px;
                height: 32px;
            }
            
            .custom--home--button-text {
                font-size: 11px;
            }
            
            .custom--home--message {
                font-size: 18px;
            }
        }

        .form-control {
          border: 2px solid #40465e;
          border-radius: 15px;
          color: #fff!important;
          background-color: transparent; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .form-control:focus {
          border: 2px solid #0eedee!important;
          background-color: transparent; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .form-select {
          border: 2px solid #40465e;
          border-radius: 15px;
          color: #fff!important;
          background-color: transparent; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .form-select:focus {
          border: 2px solid #0eedee!important;
          background-color: transparent; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .border-radius{
            border-radius: 40px;
        }

    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        
        <!-- header main -->
        @include('layouts.partials.header-raw')
        <!-- header main -->

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->
        <div class="page-content">
            <div class="page-container">

                <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column mt-1 mb-2">
                    <div class="flex-grow-1">
                        <h4 class="fs-18 fw-semibold m-0">Dashboard</h4>
                    </div>
                    <div class="mt-3 mt-sm-0">
                        <a href="{{ url('/encodage-document') }}" class="btn btn-lg btn-primary" style="border-radius: 15px;">
                            <iconify-icon icon="solar:add-square-bold-duotone"></iconify-icon>
                            <span>Encoder docs</span>
                        </a>
                    </div>
                </div>

                <div class="dashboard-stats-row" id="dashboardStats">
                        <div class="dashboard-stat-card dashboard-stat-card--total">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Total encodages</div>
                                <div class="stat-value" id="statTotal">0</div>
                                <div class="stat-hint">Selon filtres</div>
                            </div>
                        </div>
                        <div class="dashboard-stat-card dashboard-stat-card--incomplete">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:clock-circle-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Incomplets</div>
                                <div class="stat-value" id="statIncomplete">0</div>
                                <div class="stat-hint">À finaliser</div>
                            </div>
                        </div>
                        <div class="dashboard-stat-card dashboard-stat-card--complete">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Complets</div>
                                <div class="stat-value" id="statComplete">0</div>
                                <div class="stat-hint">Finalisés</div>
                            </div>
                        </div>
                        <div class="dashboard-stat-card dashboard-stat-card--expired">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:calendar-mark-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Expirés</div>
                                <div class="stat-value" id="statExpired">0</div>
                                <div class="stat-hint">Date dépassée</div>
                            </div>
                        </div>
                        <a href="{{ url('/gestion-utilisateurs') }}" class="dashboard-stat-card dashboard-stat-card--agents">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Agents</div>
                                <div class="stat-value" id="statAgents">—</div>
                                <div class="stat-hint" id="statAgentsHint">Utilisateurs</div>
                            </div>
                        </a>
                        <a href="{{ url('/gestion-clients') }}" class="dashboard-stat-card dashboard-stat-card--clients">
                            <div class="dashboard-stat-card__icon">
                                <iconify-icon icon="solar:user-id-bold-duotone"></iconify-icon>
                            </div>
                            <div class="dashboard-stat-card__body">
                                <div class="stat-label">Clients</div>
                                <div class="stat-value" id="statClients">—</div>
                                <div class="stat-hint" id="statClientsHint">Profils</div>
                            </div>
                        </a>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card p-3" style="border-radius:35px">
                            <div class="card-header border-bottom border-dashed d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <h4 class="card-title mb-0" style="font-size:1.5em;">
                                    <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                                    Encodages
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="encodages-filters mb-3">
                                    <div class="encodages-filters__left">
                                        <div class="d-flex flex-wrap gap-3 align-items-end">
                                            <div class="encodages-filter-field encodages-filter-field--status">
                                                <label class="form-label small text-muted mb-1">Statut</label>
                                                <select id="filterStatus" class="form-select">
                                                    <option value="">Tous</option>
                                                    <option value="incomplete">Incomplet</option>
                                                    <option value="complete">Complet</option>
                                                    <option value="expired">Expiré</option>
                                                </select>
                                            </div>
                                            <div class="encodages-filter-field encodages-filter-field--period">
                                                <label class="form-label small text-muted mb-1">Période</label>
                                                <select id="filterPeriod" class="form-select">
                                                    <option value="all">Toutes</option>
                                                    <option value="today">Aujourd'hui</option>
                                                    <option value="7days">7 derniers jours</option>
                                                    <option value="30days">30 derniers jours</option>
                                                    <option value="legacy">Complets 24h / Incomplets 7j</option>
                                                </select>
                                            </div>
                                            <div class="encodages-filter-field encodages-filter-field--search">
                                                <label class="form-label small text-muted mb-1">Recherche</label>
                                                <input type="text" id="filterSearch" class="form-control" placeholder="ID, client, affectation…">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="encodages-filters__right">
                                        <div class="d-flex flex-wrap gap-2 align-items-end">
                                            <button type="button" id="btnFilterApply" class="btn btn-primary" style="border-radius:12px;">Filtrer</button>
                                            <button type="button" id="btnFilterReset" class="btn btn-filter-reset rounded-circle btn-icon" title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres">
                                                <iconify-icon icon="solar:refresh-circle-bold-duotone"></iconify-icon>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="table-encodages"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div> 
            <!-- container -->



            <!-- Footer Start -->
            @include('layouts.partials.footer-raw')
            <!-- end Footer -->

        </div>

        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>

    <script src="assets/vendor/gridjs/gridjs.umd.js"></script>
    <script src="{{ asset('ajax/dashboardEncodages.js') }}"></script>

</body>

</html>
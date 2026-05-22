<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">
<head>
    <meta charset="utf-8" />
    <title>{{ $pageTitle }} | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">
    <link rel="shortcut icon" href="assets/images/ico.png">
    <script src="assets/js/config.js"></script>
    <link rel="stylesheet" href="assets/vendor/gridjs/theme/mermaid.min.css">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/authentiq-reports.css') }}" rel="stylesheet" type="text/css" />
    <link href="assets/vendor/flatpickr/flatpickr.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <style>
        html[data-layout-mode="detached"]:not([data-layout="topnav"]) .wrapper { max-width: 95%; margin: 0 auto; }
        .form-control, .form-select {
            border: 2px solid #40465e;
            border-radius: 15px;
            color: #fff !important;
            background-color: transparent;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0eedee !important;
            background-color: transparent;
        }
        .border-radius { border-radius: 40px; }
    </style>
</head>
<body>
<div class="wrapper">
    @include('layouts.partials.header-raw')

    <div class="page-content">
        <div class="page-container report-app" id="report-app" data-report-type="{{ $reportType }}">

            <div class="row mt-1">
                <div class="col-12">
                    <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-18 fw-semibold m-0">{{ $pageTitle }}</h4>
                            <p class="text-muted mb-0 mt-1">{{ $pageSubtitle }} — <span id="reportPeriodLabel" class="text-info">…</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card border-radius">
                        <div class="card-body">
                            <div class="report-period-toolbar">
                                @if ($reportType !== 'global')
                                    <div class="report-date-range">
                                        <div>
                                            <label class="form-label" for="reportDateFrom">Date début</label>
                                            <input type="text" id="reportDateFrom" class="form-control report-datepicker" placeholder="jj/mm/aaaa" autocomplete="off" readonly>
                                        </div>
                                        <div>
                                            <label class="form-label" for="reportDateTo">Date fin</label>
                                            <input type="text" id="reportDateTo" class="form-control report-datepicker" placeholder="jj/mm/aaaa" autocomplete="off" readonly>
                                        </div>
                                    </div>
                                @endif
                                <div class="ms-auto">
                                    <button type="button" id="btnReportRefresh" class="btn btn-primary" style="border-radius:12px;">
                                        <iconify-icon icon="solar:refresh-circle-bold-duotone"></iconify-icon>
                                        Actualiser
                                    </button>
                                </div>
                            </div>

                            <div id="reportStats" class="report-stats-grid"></div>

                            <div class="report-charts-row">
                                <div class="report-panel">
                                    <div class="report-panel__title" id="reportChartTitle">
                                        @if ($reportType === 'global')
                                            Évolution mensuelle
                                        @else
                                            Activité sur la période
                                        @endif
                                    </div>
                                    <div id="reportChartMain" class="report-chart"></div>
                                </div>
                                <div class="d-flex flex-column gap-3">
                                    @if ($reportType === 'global')
                                        <div class="report-panel flex-grow-1">
                                            <div class="report-panel__title">Répartition par statut</div>
                                            <ul id="reportBreakdownStatus" class="report-breakdown-list"></ul>
                                        </div>
                                    @endif
                                    <div class="report-panel flex-grow-1">
                                        <div class="report-panel__title">Par type de document</div>
                                        <ul id="reportBreakdownDoc" class="report-breakdown-list"></ul>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-lg-6">
                                    <div class="report-panel h-100">
                                        <div class="report-panel__title">Par agent</div>
                                        <ul id="reportBreakdownAgent" class="report-breakdown-list"></ul>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="report-panel h-100">
                                        <div class="report-panel__title">Par commune / affectation</div>
                                        <ul id="reportBreakdownCommune" class="report-breakdown-list"></ul>
                                    </div>
                                </div>
                            </div>

                            <div class="report-panel">
                                <div class="report-panel__title">Détail des encodages</div>
                                <div id="report-recent-table"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @include('layouts.partials.footer-raw')
    </div>
</div>

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/vendor/flatpickr/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script src="assets/vendor/gridjs/gridjs.umd.js"></script>
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="{{ asset('ajax/reports.js') }}?v={{ @filemtime(public_path('ajax/reports.js')) }}"></script>
</body>
</html>

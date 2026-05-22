<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Validation KYC | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta content="Authentiq" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <link rel="shortcut icon" href="assets/images/ico.png">
    <script src="assets/js/config.js"></script>
    <link rel="stylesheet" href="assets/vendor/gridjs/theme/mermaid.min.css">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

    <style>
        .border-radius { border-radius: 40px; }
        .gridjs-th-content { font-weight: 800!important; }
        .kyc-id-img {
            max-height: 280px;
            width: 100%;
            object-fit: contain;
            border-radius: 16px;
            background: rgba(0,0,0,.25);
        }
        .status-badge-pending { background: #f59e0b; color: #111; }
        .status-badge-approved { background: #22c55e; color: #111; }
        .status-badge-rejected { background: #ef4444; color: #fff; }
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
                                <h4 class="fs-18 fw-semibold m-0">Validation KYC</h4>
                                <p class="text-muted mb-0 mt-1">Vérifier les pièces d'identité soumises par les clients mobile.</p>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <span id="kycPendingBadge" class="badge bg-warning text-dark d-none fs-6 px-3 py-2" style="border-radius:12px"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center mt-4">
                    <div class="col-lg-12">
                        <div class="card p-3" style="border-radius:35px">
                            <div class="card-header border-bottom border-dashed d-flex flex-wrap align-items-center gap-2">
                                <h4 class="card-title mb-0 flex-grow-1" style="font-size: 1.5em;">
                                    <iconify-icon icon="solar:shield-check-bold-duotone"></iconify-icon>
                                    Soumissions KYC
                                </h4>
                                <select id="kycStatusFilter" class="form-select w-auto" style="border-radius:12px;max-width:220px">
                                    <option value="pending" selected>En attente</option>
                                    <option value="">Toutes</option>
                                    <option value="approved">Approuvées</option>
                                    <option value="rejected">Refusées</option>
                                </select>
                            </div>
                            <div class="card-body">
                                <div id="table-kyc"></div>
                            </div>
                        </div>

                        <div class="modal fade" id="kycDetailModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content" style="border-radius:35px!important">
                                    <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                                        <h5 class="modal-title" id="kycModalTitle">Détail KYC</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3 mb-3" id="kycClientInfo"></div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <p class="fw-semibold mb-2">Recto</p>
                                                <img id="kycImgRecto" class="kyc-id-img" alt="Recto pièce d'identité" src="">
                                            </div>
                                            <div class="col-md-6" id="kycVersoCol">
                                                <p class="fw-semibold mb-2">Verso</p>
                                                <img id="kycImgVerso" class="kyc-id-img" alt="Verso pièce d'identité" src="">
                                            </div>
                                        </div>
                                        <div id="kycRejectBlock" class="mt-3 d-none border-top pt-3">
                                            <label for="kycRejectReason" class="form-label">Motif du refus (optionnel)</label>
                                            <textarea id="kycRejectReason" class="form-control" rows="2" maxlength="500" placeholder="Ex. photo floue, document illisible…"></textarea>
                                        </div>
                                        <div id="kycReviewedInfo" class="alert alert-secondary mt-3 d-none" style="border-radius:12px"></div>
                                    </div>
                                    <div class="modal-footer border-0" id="kycModalActions">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:12px">Fermer</button>
                                        <button type="button" id="btnKycReject" class="btn btn-danger" style="border-radius:12px">Refuser</button>
                                        <button type="button" id="btnKycApprove" class="btn btn-success" style="border-radius:12px">Approuver</button>
                                    </div>
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
    <script src="assets/vendor/gridjs/gridjs.umd.js"></script>
    <script src="{{ asset('ajax/gridKyc.js') }}"></script>
</body>
</html>

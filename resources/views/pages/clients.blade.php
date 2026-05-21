<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Clients | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta content="Authentiq" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/ico.png">

    <!-- Theme Config Js -->
    <script src="assets/js/config.js"></script>

    <!-- gridjs css -->
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
    <!-- Cropper CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">

    <!-- Cropper JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
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

        .custom--home--message-container {
            background-color: #1a1b22;
            border-radius: 35px;
            height: 600px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0);
            border: 2px dashed #3a3f53;
        }

        .custom--home--message {
            color: #3a3f53;
            font-size: 1.2em;
            font-weight: 300;
            text-align: center;
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

        .gridjs-th-content{
            font-weight: 800!important;
        }

        .client-photo-camera {
            position: relative;
            max-width: 100%;
            background: #1a1b22;
            border: 2px solid #40465e;
            border-radius: 16px;
            overflow: hidden;
        }
        .client-photo-camera #clientCameraVideo {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            display: block;
            background: #12131a;
        }
        #clientPhotoPreview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #0eedee;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }
        .cropper-profile-wrap {
            min-height: 320px;
            max-height: min(55vh, 480px);
            background: #12131a;
            border-radius: 16px;
            overflow: hidden;
        }
        .cropper-profile-wrap > img { max-width: 100%; display: block; }
        .cropper-profile-wrap .cropper-container { width: 100% !important; }
        .cropper-profile-wrap .cropper-view-box,
        .cropper-profile-wrap .cropper-face { border-radius: 50%; }
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

            <div class="row mt-1">
                <div class="col-12">
                    <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-18 fw-semibold m-0">Clients</h4>
                        </div>
                        <div class="mt-3 mt-sm-0">
                            <div class="row g-2 mb-0 align-items-center">
                                <div class="col-auto">
                                    <button id="btnAddClient" class="btn btn-lg btn-primary" style="border-radius: 15px;">
                                        <iconify-icon icon="solar:user-plus-bold-duotone"></iconify-icon>
                                        <span>
                                            Nouv. client
                                        </span>
                                    </button>
                                </div>
                                
                            </div>
                            <!--end row-->
                        </div>
                    </div><!-- end card header -->
                </div>
                <!--end col-->
            </div> <!-- end row-->

            <div class="row justify-content-center mt-4">
                <div class="col">

                <div class="row">
                <div class="col-lg-12">
                    
                    <!-- Start Card affichage des communes -->
                    <div class="card p-3" style="border-radius:35px">
                      <div class="card-header border-bottom border-dashed">
                        <h4 class="card-title mb-0 flex-grow-1" style="font-size: 1.5em;">
                            <iconify-icon icon="solar:users-group-rounded-line-duotone"></iconify-icon>
                            Liste des clients
                        </h4>
                      </div>
                      <div class="card-body">
                        <div id="table-clients"></div>
                      </div>
                    </div>
                    <!-- End Card affichage des communes -->


<!-- Modal Client CRUD -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h5 class="modal-title" id="clientModalTitle">Ajouter / Modifier un client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formClient">
                    <input type="hidden" id="clientId">

                    <div class="row px-3 mt-3">
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" placeholder="Nom complet*" id="clientNom" required>
                            <label for="clientNom" class="form-label">Nom complet*</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="clientTel" placeholder="0XXXXXXXXX">
                            <label for="clientTel" class="form-label">Téléphone</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3">
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="email" class="form-control" placeholder="Email" id="clientEmail">
                            <label for="clientEmail" class="form-label">Email</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select id="clientTypePiece" class="form-select">
                                <option value="">Sélectionner</option>
                                <option value="CNI">CNI</option>
                                <option value="Passeport">Passeport</option>
                            </select>
                            <label for="clientTypePiece" class="form-label">Type pièce identité</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3">
                        <div class="col-md-12 mb-3" id="numNationalContainer" style="display:none;">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="clientNumNational" placeholder="Numéro national">
                            <label for="clientNumNational" class="form-label">Numéro national</label>
                        </div>
                        </div>
                        <div class="col-md-12 mb-3" id="numPassportContainer" style="display:none;">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="clientNumPassport" placeholder="Numéro passeport">
                            <label for="clientNumPassport" class="form-label">Numéro passeport</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3">
                        
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select id="clientProvince" class="form-select"></select>
                            <label for="clientProvince" class="form-label">Province</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select id="clientVille" class="form-select"></select>
                            <label for="clientVille" class="form-label">Ville</label>
                        </div>
                        </div>

                    </div>

                    <div class="row px-3">
                        <div class="col-md-12 mb-3">
                        <div class="form-floating">
                            <textarea class="form-control" id="clientAdresse" rows="2" placeholder="Numéro, avenue, quartier..."></textarea>
                            <label for="clientAdresse" class="form-label">Adresse complète</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3 align-items-start">
                        <div class="col-md-7 mb-3">
                            <label class="form-label text-muted small mb-2">
                                <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon> Photo du client <span class="text-danger">*</span> (caméra)
                            </label>
                            <div class="client-photo-camera">
                                <video id="clientCameraVideo" autoplay playsinline muted></video>
                                <canvas id="clientCameraCanvas" class="d-none" aria-hidden="true"></canvas>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" id="btnClientCapturePhoto" class="btn btn-sm btn-primary">
                                    <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon> Capturer
                                </button>
                                <button type="button" id="btnClientStartCamera" class="btn btn-sm btn-outline-secondary">
                                    <iconify-icon icon="solar:videocamera-record-bold-duotone"></iconify-icon> Caméra
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Photo obligatoire : capturez le visage avec la caméra (pas de fichier joint).</small>
                        </div>
                        <div class="col-md-5 mb-3 text-center">
                            <label class="form-label text-muted small mb-2 d-block">Aperçu</label>
                            <img id="clientPhotoPreview" src="assets/images/user.jpg" alt="Aperçu client" width="100" height="100">
                        </div>
                    </div>

                    <div class="modal-footer border-0">
                       <a class="btn btn-light btn-lg fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Close</a>
                       <button type="submit" id="clientSubmit" class="btn btn-primary fw-semibold btn-lg" style="border-radius:12px">Enregistrer</button>
                   </div>

                </form>
            </div>
        </div>
    </div>
</div>
<!-- End Modal Client CRUD -->

@include('pages.partials.client-otp-modal')
@include('pages.partials.client-duplicate-modal')

@include('pages.partials.client-photo-cropper-modal', ['modalId' => 'clientCropperModal', 'imageId' => 'clientCropperImage', 'validateId' => 'clientCropperValidate'])

<!-- Modal Détails Client -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important;background: #364063;">
                <h5 class="modal-title">
                    Détails du client
                    <br> 
                    <span class="fw-normal" style="font-size: .8em;">
                        Profil client authentiq
                    </span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="d-flex align-items-center gap-2 mb-3">
                    <img id="detailPhoto" src="assets/images/user.jpg" class="avatar-xl rounded-circle" style=" object-fit: cover;width: 70px;height: 70px;object-position: center;border: solid 3px #00f1f0;">
                    <div>
                        <h4 class="text-dark fw-medium" id="detailNom"></h4>
                        <p class="mb-0 text-muted">user authentiq</p>
                    </div>
                    <div class="ms-auto">
                        <span class="badge bg-success px-2 py-1" style="font-size:1.1em" id="detailStatut">Actif</span>
                    </div>

                </div>
                

                <div class="d-flex p-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:phone-calling-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Téléphone</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailTel">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:letter-opened-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Email</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailEmail">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:user-id-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Type pièce d'identité</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailTypePiece">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:key-minimalistic-square-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Numéro d'identité</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailNumIdentite">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:point-on-map-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Province</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailProvince">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:map-arrow-square-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Ville</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailVille">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:calendar-date-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Date d'inscription</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailDate">-</p>
                    </div>
                </div>

                <div class="d-flex p-2 mt-2 rounded align-items-center gap-2 border" style="border-radius:15px!important">
                    <div class="avatar avatar-lg bg-info-subtle  d-flex align-items-center justify-content-center rounded-circle" >
                        <iconify-icon icon="solar:map-point-wave-bold-duotone" class="text-info fs-32"></iconify-icon>
                    </div>
                    <div>
                        <p class="text-muted fw-medium mb-1 fs-12">Adresse complète</p>
                        <p class="text-dark mb-0 fs-12 fw-bold" id="detailAdresse">-</p>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0">
                <a class="btn btn-light btn-lg fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Terminer</a>
            </div>
        </div>
    </div>
</div>
<!-- End Modal Détails Client -->


                </div>
                    <!-- end col -->
                </div>
                <!-- end row -->
            </div> 
            <!-- end col-->
            

            
        </div> <!-- end row-->

    </div> <!-- container -->
            

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

    <!-- gridjs js -->
    <script src="assets/vendor/gridjs/gridjs.umd.js"></script>

    <script>
        window.AUTHENTIQ_REKOGNITION_ENABLED = @json(config('authentiq.rekognition_enabled'));
    </script>
    <script src="{{ asset('js/authentiq-client-duplicate.js') }}?v={{ @filemtime(public_path('js/authentiq-client-duplicate.js')) }}"></script>
    <script src="{{ asset('ajax/gridClients.js') }}"></script>

</body>

</html>
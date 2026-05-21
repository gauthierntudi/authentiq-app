<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Utilisateurs | Authentiq</title>
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

        #photoDropZone {
            background-color: #1a1b22;
            border: 2px dashed #40465e !important;
            border-radius: 16px;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        #photoDropZone:hover,
        #photoDropZone.dragover {
            border-color: #0eedee !important;
            background: rgba(14, 237, 238, 0.08);
        }
        #previewPhoto {
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
                            <h4 class="fs-18 fw-semibold m-0">Utilisateurs</h4>
                        </div>
                        <div class="mt-3 mt-sm-0">
                            <div class="row g-2 mb-0 align-items-center">
                                <div class="col-auto">
                                    <button id="btnAddUser" class="btn btn-lg btn-primary" style="border-radius: 15px;">
                                        <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                                        <span>
                                            Nouv. user
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
                            <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                            Les utilisateurs
                        </h4>
                      </div>
                      <div class="card-body">
                        <div id="table-users"></div>
                      </div>
                    </div>
                    <!-- End Card affichage des communes -->


<!-- Start Modal utilisateur -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h5 class="modal-title" id="userModalTitle">
                    Ajouter / Modifier un utilisateur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formUser" enctype="multipart/form-data">
                    <input type="hidden" id="userId">
                    
                    <div class="row px-3">
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" placeholder="Nom complet *" id="userNom" required>
                            <label for="userNom" class="form-label">Nom complet *</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" placeholder="Téléphone *" id="userTel" required>
                            <label for="userTel" class="form-label">Téléphone *</label>
                        </div>
                        </div>
                    </div>
                    
                    <div class="row px-3">
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="email" class="form-control" placeholder="Email *" id="userEmail" required>
                            <label for="userEmail" class="form-label">Email</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="password" class="form-control" placeholder="Mot de passe" id="userPassword">
                            <label for="userPassword" class="form-label">Mot de passe</label>
                        </div>
                        </div>
                    </div>
                    
                    <div class="row px-3">
                        <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="userRole" required>
                                <option selected disabled>Spécifier rôle</option>
                                <option value="admin">Admin</option>
                                <option value="user">Utilisateur</option>
                            </select>
                            <label for="userRole" class="form-label">Rôle*</label>
                        </div>
                        </div>
                        <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="userProvince"></select>
                            <label for="userProvince" class="form-label">Province</label>
                        </div>
                        </div>
                        <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="userVille"></select>
                            <label for="userVille" class="form-label">Ville</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3">
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="userCommune"></select>
                            <label for="userCommune" class="form-label">Commune</label>
                        </div>
                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="userAffectation" placeholder="Affectation" readonly>
                            <label for="userAffectation" class="form-label">Affectation</label>
                        </div>
                        </div>
                    </div>

                    <div class="row px-3">
                      <div class="col-md-8 mb-3">
                        <label class="form-label text-muted small mb-2">Photo de profil</label>
                        <div id="photoDropZone" class="p-4 text-center d-flex flex-column justify-content-center align-items-center"
                             style="cursor:pointer; min-height:120px;">
                            <iconify-icon icon="solar:gallery-send-line-duotone" style="font-size:2rem; color:#0eedee;"></iconify-icon>
                            <span id="photoDropText" class="mt-2 text-muted">Glisser-déposer ou cliquer pour choisir</span>
                            <span class="small text-muted opacity-75">JPG, PNG — rognage circulaire</span>
                            <input type="file" id="userPhoto" accept="image/*" style="display:none;">
                        </div>
                      </div>
                      <div class="col-md-4 mb-3 text-center">
                        <label class="form-label text-muted small mb-2 d-block">Aperçu</label>
                        <img id="previewPhoto" src="assets/images/user.jpg" alt="Aperçu" width="100" height="100"
                             style="display:none; width:100px; height:100px; object-fit:cover; border-radius:50%; margin:0 auto;">
                      </div>
                    </div>

                    <div class="modal-footer border-0">
                       <a class="btn btn-light btn-lg fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Close</a>
                       <button type="submit" id="userSubmit" class="btn btn-primary fw-semibold btn-lg" style="border-radius:12px">Enregistrer</button>
                   </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- End Modal utilisateur -->

<!-- Modal rogner la photo -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h5 class="modal-title mb-0">
                    Rogner la photo
                    <br>
                    <span class="fw-normal" style="font-size:.8em;">Ajustez le cadrage de la photo de profil</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="cropper-profile-wrap">
                            <img id="cropperImage" src="" alt="Image à rogner">
                        </div>
                        <div class="cropper-profile-toolbar">
                            <button type="button" class="btn-tool" data-crop-action="zoom-in">
                                <iconify-icon icon="solar:magnifer-zoom-in-bold-duotone"></iconify-icon>
                                Zoom +
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="zoom-out">
                                <iconify-icon icon="solar:magnifer-zoom-out-bold-duotone"></iconify-icon>
                                Zoom −
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="rotate-left">
                                <iconify-icon icon="solar:restart-bold-duotone"></iconify-icon>
                                Gauche
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="rotate-right">
                                <iconify-icon icon="solar:restart-bold-duotone" style="transform:scaleX(-1)"></iconify-icon>
                                Droite
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="reset">
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
                <button type="button" id="cropperValidate" class="btn btn-primary btn-lg fw-semibold" style="border-radius:12px">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                    Valider le rognage
                </button>
            </div>
        </div>
    </div>
</div>


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

    <script src="{{ asset('ajax/gridUsers.js') }}"></script>

</body>

</html>
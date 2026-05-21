<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Documents | Authentiq</title>
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
                            <h4 class="fs-18 fw-semibold m-0">Documents</h4>
                        </div>
                        <div class="mt-3 mt-sm-0">
                            <div class="row g-2 mb-0 align-items-center">
                                <div class="col-auto">
                                    <button id="btnAddDoc" class="btn btn-lg btn-primary" style="border-radius: 15px;">
                                        <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                                        <span>
                                            Nouv. document
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
                <div class="col-md-9">

                <div class="row">
                <div class="col-lg-12">
                    
                    <!-- Card affichage des communes -->
                    <div class="card p-3" style="border-radius:35px">
                      <div class="card-header border-bottom border-dashed">
                        <h4 class="card-title mb-0 flex-grow-1" style="font-size: 1.5em;">
                            <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                            Types documents
                        </h4>
                      </div>
                      <div class="card-body">
                        <div id="table-docs"></div>
                      </div>
                    </div>


                    <!-- Modal ajout / édition document -->
                    <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content" style="border-radius:35px!important">
                                <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                                    <h5 class="modal-title" id="docModalTitle">Ajouter / Modifier document
                                    <br> 
                                    <span class="fw-normal" style="font-size: .8em;">Faites des opérations sur les docs</span>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="formDoc">
                                        <input type="hidden" id="docId">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="docNom" required>
                                            <label for="docNom" class="form-label">Nom du document</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="docType" required>
                                                <option value="free">Free</option>
                                                <option value="payant">Payant</option>
                                            </select>
                                            <label for="docType" class="form-label">Type</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="docMontant" value="0" min="0">
                                            <label for="docMontant" class="form-label">Montant</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="docValidite" required>
                                                <option value="court">Court</option>
                                                <option value="moyen">Moyen</option>
                                                <option value="long">Long</option>
                                            </select>
                                            <label for="docValidite" class="form-label">Validité</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="docDuree" value="0" min="0">
                                            <label for="docDuree" class="form-label">Durée (mois)</label>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Durée illimitée (À vie)</h6>
                                            <input type="checkbox" id="docIllimite" checked data-switch="success" />
                                            <label for="docIllimite" data-on-label="Yes" data-off-label="No"></label>

                                            <!-- <div class="form-check mt-1">
                                                <input type="checkbox" class="form-check-input" id="docIllimite">
                                                <label class="form-check-label" for="docIllimite">Durée illimitée (À vie)</label>
                                            </div> -->
                                        </div>

                                        <div class="modal-footer border-0">
                                           <a class="btn btn-light fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Close</a>
                                           <button type="submit" id="docSubmit" class="btn btn-primary fw-semibold" style="border-radius:12px">Enregistrer</button>
                                       </div>
                                    </form>
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

    <script src="{{ asset('ajax/gridDocs.js') }}"></script>

</body>

</html>
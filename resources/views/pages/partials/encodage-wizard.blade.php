<div class="encodage-wizard">
    <div class="row mt-1 mb-3">
        <div class="col-12">
            <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                <div class="flex-grow-1">
                    <h4 class="fs-18 fw-semibold m-0 d-flex align-items-center gap-2">
                        <iconify-icon icon="solar:scanner-bold-duotone" class="text-primary" style="font-size:1.5em"></iconify-icon>
                        Encodage de documents
                    </h4>
                    <p class="encodage-wizard__subtitle mb-0 mt-1">Scanner intelligent avec détection automatique A4</p>
                </div>
                <div class="mt-3 mt-sm-0">
                    <a href="{{ url('/dashboard') }}" class="btn btn-light" style="border-radius:15px;">
                        <iconify-icon icon="solar:arrow-left-bold-duotone"></iconify-icon>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ url('/encodage-document') }}" class="btn btn-primary ms-2" style="border-radius:15px;">
                        <iconify-icon icon="solar:add-square-bold-duotone"></iconify-icon>
                        <span>Nouvel encodage</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="stepper-header">
        <div class="step-item active" data-step="1">
            <div class="step-number">1</div>
            <div class="step-title">Scanner</div>
        </div>
        <div class="step-item" data-step="2">
            <div class="step-number">2</div>
            <div class="step-title">OCR</div>
        </div>
        <div class="step-item" data-step="3">
            <div class="step-number">3</div>
            <div class="step-title">Client</div>
        </div>
        <div class="step-item" data-step="4">
            <div class="step-number">4</div>
            <div class="step-title">Document</div>
        </div>
        <div class="step-item" data-step="5">
            <div class="step-number">5</div>
            <div class="step-title">Finaliser</div>
        </div>
    </div>

    <div id="step1" class="step active">
        <h4><iconify-icon icon="solar:camera-bold-duotone"></iconify-icon> Scannez votre document</h4>
        <div class="scanner-help">
            <iconify-icon icon="solar:info-circle-bold-duotone"></iconify-icon>
            <strong>Documents à une page ou plusieurs pages</strong> — Attestation, permis, actes en 1 page : capturez, validez, puis <em>Suivant</em>.
            Documents multipages (livret, dossier…) : validez chaque page avec <em>Ajouter une autre page</em> avant de continuer.
            <br><span class="text-muted small">Placez le document à plat, bon éclairage ; détection automatique des contours A4.</span>
        </div>
        <div id="scanPagesSummary" class="scan-pages-summary" aria-live="polite">
            <span id="scanPagesCount" class="scan-pages-summary__count">Aucune page enregistrée</span>
            <ul id="scanPagesList" class="scan-pages-summary__list list-unstyled mb-0"></ul>
        </div>
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="enc-scan-camera">
                    <div class="enc-scan-camera__head">
                        <h5 class="enc-scan-camera__title mb-0"><iconify-icon icon="solar:videocamera-record-bold-duotone"></iconify-icon> Caméra</h5>
                        <button type="button" id="btnExpandScanCamera" class="btn btn-sm btn-light fw-semibold" style="border-radius:12px" title="Ouvrir le scan en grand (portrait)">
                            <iconify-icon icon="solar:full-screen-bold-duotone"></iconify-icon>
                            <span>Agrandir</span>
                        </button>
                    </div>
                    <div class="enc-scan-camera__preview">
                        <video id="video" autoplay playsinline muted></video>
                    </div>
                    <button id="captureButton" type="button" class="btn btn-primary mt-3 w-100 fw-semibold" style="border-radius:12px">
                        <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon>
                        Capturer le document
                    </button>
                </div>
            </div>
            <div class="col-lg-6">
                <div id="previewContainer">
                    <h5><iconify-icon icon="solar:crop-bold-duotone"></iconify-icon> Aperçu et ajustement</h5>
                    <p class="text-muted small mb-2">
                        <iconify-icon icon="solar:cursor-bold-duotone"></iconify-icon>
                        Glissez les poignées pour ajuster les bordures
                    </p>
                    <canvas id="canvas"></canvas>
                    <div class="crop-controls">
                        <button id="recaptureButton" type="button" class="btn btn-warning">
                            <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon>
                            Recapturer
                        </button>
                        <button id="nextStep1" type="button" class="btn btn-success" title="Document 1 page : valide la capture en cours puis continue">
                            <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
                            Suivant
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="step2" class="step">
        <h4><iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon> Extraction du texte (OCR)</h4>
        <div id="ocrProgress" class="alert" style="display:none;">
            <div class="d-flex align-items-center">
                <div class="loading-spinner me-3"></div>
                <div>Extraction en cours… <strong id="ocrProgressText">0%</strong></div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Texte extrait</label>
            <textarea id="ocrText" class="form-control" rows="15" placeholder="Le texte extrait du document apparaîtra ici…"></textarea>
            <small class="text-muted d-block mt-1">
                <iconify-icon icon="solar:pen-2-bold-duotone"></iconify-icon>
                Vous pouvez modifier le texte si nécessaire
            </small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button id="prevStep2" type="button" class="btn btn-secondary">
                <iconify-icon icon="solar:arrow-left-bold-duotone"></iconify-icon> Précédent
            </button>
            <button id="nextStep2" type="button" class="btn btn-success">
                Suivant <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
            </button>
        </div>
    </div>

    <div id="step3" class="step">
        <h4><iconify-icon icon="solar:user-bold-duotone"></iconify-icon> Informations du client</h4>
        <div class="mb-4">
            <label class="form-label">Type de client</label>
            <select id="clientType" class="form-select">
                <option value="new">Nouveau client</option>
                <option value="existing">Client existant</option>
            </select>
        </div>
        <div id="existingClientDiv" style="display:none;">
            @if(config('authentiq.rekognition_enabled'))
            <div class="enc-face-module encodage-face-recognition-row position-relative mb-3">
                <div id="faceRecognitionRowLoader" class="enc-face-module__loader d-none" aria-live="polite" aria-busy="true">
                    <div class="enc-face-module__loader-ring" role="status"></div>
                    <p class="enc-face-module__loader-title">Identification en cours</p>
                    <p class="enc-face-module__loader-hint">Reconnaissance faciale…</p>
                </div>
                <div class="enc-face-module__grid">
                    <section class="enc-face-scan" aria-label="Reconnaissance faciale">
                        <header class="enc-face-scan__header">
                            <span class="enc-face-scan__header-icon" aria-hidden="true">
                                <iconify-icon icon="solar:face-scan-circle-bold-duotone"></iconify-icon>
                            </span>
                            <div>
                                <h6 class="enc-face-scan__title">Reconnaissance faciale</h6>
                                <p class="enc-face-scan__subtitle">Cadrez le visage puis lancez l'identification</p>
                            </div>
                        </header>
                        <div class="enc-face-scan__viewport">
                            <div class="enc-face-camera enc-face-scan__frame">
                                <video id="faceVideo" autoplay playsinline muted></video>
                                <canvas id="faceCanvas" class="d-none" aria-hidden="true"></canvas>
                                <img id="facePreview" class="enc-face-camera__preview d-none" alt="Aperçu capture">
                                <span class="enc-face-scan__corners" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="enc-face-scan__actions" role="group" aria-label="Actions caméra">
                            <button type="button" id="btnCaptureFace" class="btn btn-sm btn-light fw-semibold" style="border-radius:12px">
                                <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon>
                                <span>Capturer</span>
                            </button>
                            <button type="button" id="btnSearchClientByPhoto" class="btn btn-sm btn-primary fw-semibold" style="border-radius:12px">
                                <iconify-icon icon="solar:scan-face-bold-duotone"></iconify-icon>
                                <span>Identifier</span>
                            </button>
                            <button type="button" id="btnRetakeFace" class="btn btn-sm btn-light fw-semibold d-none" style="border-radius:12px">
                                <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon>
                                <span>Reprendre</span>
                            </button>
                        </div>
                    </section>
                    <aside id="recognizedClientCard" class="enc-face-result" aria-label="Client reconnu">
                        <header class="enc-face-result__header">
                            <span class="enc-face-result__header-icon" aria-hidden="true">
                                <iconify-icon icon="solar:user-check-rounded-bold-duotone"></iconify-icon>
                            </span>
                            <h6 class="enc-face-result__title">Client reconnu</h6>
                        </header>
                        <div id="recognizedClientEmpty" class="enc-face-result__empty">
                            <div class="enc-face-result__empty-visual" aria-hidden="true">
                                <iconify-icon icon="solar:user-circle-bold-duotone"></iconify-icon>
                            </div>
                            <p class="enc-face-result__empty-title">Aucun résultat</p>
                            <p class="enc-face-result__empty-hint">Les informations s'afficheront après identification</p>
                        </div>
                        <div id="recognizedClientContent" class="enc-face-result__body encodage-recognized-client-card__body" hidden></div>
                    </aside>
                </div>
            </div>
            @endif
            <div class="mb-3">
                <label class="form-label">
                    <iconify-icon icon="solar:magnifer-bold-duotone"></iconify-icon> Rechercher un client
                </label>
                <input type="text" id="searchClient" class="form-control" placeholder="Nom, téléphone ou email…">
            </div>
            <div class="mb-3">
                <select id="clientSelect" class="form-select d-none" aria-hidden="true" tabindex="-1">
                    <option value="">Tapez pour rechercher…</option>
                </select>
                <ul id="clientSearchResults" class="client-search-results list-unstyled mb-0">
                    <li class="client-search-results__hint text-muted">Tapez au moins 2 caractères…</li>
                </ul>
            </div>
        </div>
        <div id="newClientDiv">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" id="clientNom" class="form-control" placeholder="Ex: Jean Dupont">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                    <input type="text" id="clientTel" class="form-control" placeholder="Ex: 0812345678">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" id="clientEmail" class="form-control" placeholder="email@exemple.com">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Type de pièce d'identité</label>
                    <select id="clientTypePiece" class="form-select">
                        <option value="">Sélectionner…</option>
                        <option value="CNI">Carte Nationale d'Identité</option>
                        <option value="Passeport">Passeport</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Numéro de pièce</label>
                    <input type="text" id="clientNumeroPiece" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Photo du client <span class="text-danger">*</span></label>
                <div class="row align-items-start g-2">
                    <div class="col-md-7">
                        <div class="encodage-face-camera encodage-new-client-camera">
                            <video id="newClientCameraVideo" autoplay playsinline muted></video>
                            <canvas id="newClientCameraCanvas" class="d-none" aria-hidden="true"></canvas>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" id="btnNewClientCapturePhoto" class="btn btn-sm btn-primary">
                                <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon> Capturer
                            </button>
                            <button type="button" id="btnNewClientStartCamera" class="btn btn-sm btn-outline-secondary">
                                <iconify-icon icon="solar:videocamera-record-bold-duotone"></iconify-icon> Caméra
                            </button>
                        </div>
                    </div>
                    <div class="col-md-5 text-center">
                        <img id="newClientPhotoPreview" src="assets/images/user.jpg" alt="Aperçu" class="encodage-new-client-preview">
                    </div>
                </div>
                <small class="text-muted d-block mt-1">Photo obligatoire via la caméra (reconnaissance faciale après OTP).</small>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button id="prevStep3" type="button" class="btn btn-secondary">
                <iconify-icon icon="solar:arrow-left-bold-duotone"></iconify-icon> Précédent
            </button>
            <button id="saveStep3" type="button" class="btn btn-primary">
                <iconify-icon icon="solar:diskette-bold-duotone"></iconify-icon> Sauvegarder
            </button>
            <button id="nextStep3" type="button" class="btn btn-success">
                Suivant <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
            </button>
        </div>
    </div>

    <div id="step4" class="step">
        <h4><iconify-icon icon="solar:document-bold-duotone"></iconify-icon> Informations du document</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Type de document <span class="text-danger">*</span></label>
                <select id="docType" class="form-select" required>
                    <option value="">Sélectionner…</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Montant</label>
                <input type="number" id="docMontant" class="form-control" step="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date d'émission</label>
                <input type="text" id="docDateEmission" class="form-control encodage-datepicker" placeholder="Sélectionnez un type de document" autocomplete="off">
                <small id="docDateEmissionHint" class="form-text text-muted">Choisissez d'abord le type de document.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date d'expiration</label>
                <input type="text" id="docDateExpiration" class="form-control encodage-datepicker" placeholder="—" autocomplete="off">
                <small id="docDateExpirationHint" class="form-text text-muted">Calculée automatiquement selon la durée du document.</small>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button id="prevStep4" type="button" class="btn btn-secondary">
                <iconify-icon icon="solar:arrow-left-bold-duotone"></iconify-icon> Précédent
            </button>
            <button id="saveStep4" type="button" class="btn btn-primary">
                <iconify-icon icon="solar:diskette-bold-duotone"></iconify-icon> Sauvegarder
            </button>
            <button id="nextStep4" type="button" class="btn btn-success">
                Suivant <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
            </button>
        </div>
    </div>

    <div id="step5" class="step enc-recap-step">
        <header class="enc-recap-step__header">
            <div class="enc-recap-step__header-icon" aria-hidden="true">
                <iconify-icon icon="solar:clipboard-check-bold-duotone"></iconify-icon>
            </div>
            <div>
                <h4 class="enc-recap-step__title">Récapitulatif et finalisation</h4>
                <p class="enc-recap-step__subtitle">Vérifiez les informations ci-dessous avant de valider définitivement l'encodage.</p>
            </div>
        </header>
        <div id="recapitulatif" class="enc-recap" aria-live="polite">
            <div class="enc-recap__loading">
                <div class="enc-recap__loading-spinner" role="status"></div>
                <p class="enc-recap__loading-text">Chargement du récapitulatif…</p>
            </div>
        </div>
        <footer class="enc-recap-step__footer">
            <button id="prevStep5" type="button" class="btn btn-light fw-semibold btn-lg" style="border-radius:12px">
                <iconify-icon icon="solar:arrow-left-bold-duotone"></iconify-icon>
                <span>Précédent</span>
            </button>
            <button id="submitBtn" type="button" class="btn btn-primary fw-semibold btn-lg" style="border-radius:12px" disabled>
                <iconify-icon icon="solar:verified-check-bold-duotone" class="js-finalize-icon"></iconify-icon>
                <span class="js-finalize-label">Finaliser l'encodage</span>
            </button>
        </footer>
    </div>
</div>

<input type="hidden" id="encodageId" value="">
<input type="hidden" id="clientId" value="">

@include('pages.partials.client-otp-modal')
@include('pages.partials.encodage-pages-viewer-modal')
@include('pages.partials.encodage-scan-camera-modal')
@include('pages.partials.client-duplicate-modal')

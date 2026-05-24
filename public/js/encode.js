const ENCODAGE_API = window.ENCODAGE_API_BASE || '/api/encodage-workflow';
const ENCODAGE_DASHBOARD_URL = window.ENCODAGE_DASHBOARD_URL || '/dashboard';

function encodeApiHeaders(includeJson) {
    const headers = {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'Accept': 'application/json',
    };
    if (includeJson) {
        headers['Content-Type'] = 'application/json';
    }
    return headers;
}

// Variables globales
let currentStep = 1;
/** Étape « Informations client » dans le wizard (après Document). */
const ENCODAGE_CLIENT_STEP = 4;
let capturedImageBlob = null;
let encodageId = null;
let clientId = null;
/** @type {{ id_client: number, nom_complet: string, tel?: string, email?: string, is_primary?: boolean }[]} */
let associatedClients = [];
let videoStream = null;
let faceVideoStream = null;
let faceCaptureBlob = null;

// Variables pour le scan amélioré
let cropCorners = null;
let isDragging = false;
let dragCornerIndex = -1;
let originalImageData = null;
let canvasScale = 1;

// Multi-pages
let scannedPages = [];
let currentPageIndex = 0;

// OpenCV / OCR — chargés à la demande (évite ~10 Mo au premier affichage)
const OPENCV_JS_URL = 'https://docs.opencv.org/4.5.0/opencv.js';
const TESSERACT_JS_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
const PDFJS_JS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const PDFJS_WORKER_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const MAX_PDF_FILE_BYTES = 50 * 1024 * 1024;
const MAX_PDF_PAGES_TOTAL = 100;
let opencvReady = false;
let opencvLoadPromise = null;
let tesseractLoadPromise = null;
let pdfJsLoadPromise = null;
let pdfImportInProgress = false;
/** @type {{ id: string, name: string, pdfDoc: object, pageCount: number }[]} */
let importedPdfs = [];
let pdfViewerState = { pdfIndex: 0, pageNum: 1 };
let pdfViewerModalInstance = null;
let pdfViewerRenderToken = 0;
const PDF_VIEWER_PADDING = 16;
let activeGalleryPageIndex = -1;

// Durée du document sélectionné (pour calcul date expiration)
let currentDocDuree = 0;
let pendingResumePayload = null;
let clientAwaitingOtp = false;
let pendingOtpGoNext = false;
let otpClientIdForVerification = null;
let encodageStatus = 'incomplete';
/** Pages PDF modifiées localement sans repasser par OCR + sauvegarde serveur. */
let scanPagesDirty = false;
/** OCR + sauvegarde en cours depuis l'étape 1 (évite double clic Suivant). */
let ocrPipelineInProgress = false;

let newClientCameraStream = null;
let encNewClientCroppedBlob = null;
let encNewClientPreviewUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    loadDocumentTypes();
    renderAssociatedClientsChips();
    setupEventListeners();
    setupClientPhotoSearch();
    setupEncodageNewClientPhoto();
    initEncodageResume();
});

function initEncodageResume() {
    const continueId = sessionStorage.getItem('continueEncodageId');
    if (continueId) {
        sessionStorage.removeItem('continueEncodageId');
        resumeEncodage(parseInt(continueId, 10));
        return;
    }
}

function renderEncDataLoader() {
    return `
        <div class="enc-data-loader" role="status" aria-live="polite" aria-busy="true">
            <div class="enc-data-loader__spinner" aria-hidden="true"></div>
            <p class="enc-data-loader__title">Veuillez patienter</p>
            <p class="enc-data-loader__hint">Nous chargeons vos données pour l'affichage</p>
        </div>`;
}

function showEncPagesLoader(hostId) {
    const host = document.getElementById(hostId);
    if (!host) {
        return;
    }

    host.classList.add('is-loading');
    host.setAttribute('aria-busy', 'true');

    let overlay = host.querySelector(':scope > .enc-data-loader-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'enc-data-loader-overlay';
        overlay.innerHTML = renderEncDataLoader();
        host.appendChild(overlay);
    }

    overlay.hidden = false;
}

function hideEncPagesLoader(hostId) {
    const host = document.getElementById(hostId);
    if (!host) {
        return;
    }

    host.classList.remove('is-loading');
    host.removeAttribute('aria-busy');

    const overlay = host.querySelector(':scope > .enc-data-loader-overlay');
    if (overlay) {
        overlay.hidden = true;
    }
}

function resumeEncodage(id) {
    showEncPagesLoader('scanPagesSummary');
    fetch(`${ENCODAGE_API}/${id}/resume`, { headers: encodeApiHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data.status !== 'success' || !data.encodage) {
                iziToast.error({ message: data.message || 'Impossible de reprendre cet encodage.' });
                return;
            }
            pendingResumePayload = data;
            tryApplyPendingResume();
        })
        .catch(() => iziToast.error({ message: 'Erreur lors de la reprise de l\'encodage.' }))
        .finally(() => hideEncPagesLoader('scanPagesSummary'));
}

function tryApplyPendingResume() {
    if (!pendingResumePayload) return;
    const select = document.getElementById('docType');
    if (!select || select.options.length <= 1) return;
    applyResumedEncodage(pendingResumePayload);
    pendingResumePayload = null;
}

function isEncodageAdmin() {
    return window.AUTHENTIQ_USER_ROLE === 'admin';
}

function isEncodageEditable() {
    if (isEncodageAdmin()) {
        return true;
    }

    return encodageStatus === 'incomplete';
}

function setEncodageStatus(status) {
    encodageStatus = status || 'incomplete';
    syncWizardEditability();
}

function syncWizardEditability() {
    const editable = isEncodageEditable();
    const adminOnComplete = isEncodageAdmin() && encodageStatus === 'complete';
    const hint = document.getElementById('encodageEditHint');

    document.querySelectorAll('.encodage-wizard .stepper-header .step-item[data-step]').forEach((item) => {
        const stepNum = parseInt(item.dataset.step, 10);
        const stepEditable = editable || (adminOnComplete && stepNum <= 5);
        item.classList.toggle('is-editable', stepEditable);
        item.disabled = !stepEditable && stepNum < 5;
    });

    if (hint) {
        if (adminOnComplete) {
            hint.hidden = false;
            hint.textContent = 'Mode administrateur : vous pouvez modifier ou supprimer cet encodage finalisé.';
        } else if (editable && encodageStatus === 'incomplete') {
            hint.hidden = false;
            hint.textContent = 'Tant que l\'encodage n\'est pas finalisé, vous pouvez revenir modifier les pages, le client ou le document via les étapes ci-dessus ou les boutons du récapitulatif.';
        } else {
            hint.hidden = true;
        }
    }
}

function markScanPagesDirty() {
    scanPagesDirty = true;
}

function clearScanPagesDirty() {
    scanPagesDirty = false;
}

function getStepBlockedMessage(step) {
    if (step === 2) {
        if (scannedPages.length === 0) {
            return 'Joignez au moins un PDF avant l\'étape OCR.';
        }

        return 'Complétez les étapes précédentes avant de continuer.';
    }
    if (!encodageId) {
        return 'Enregistrez d\'abord les pages via l\'étape 1 (Suivant) avant de continuer.';
    }
    if (scanPagesDirty) {
        return 'Les pages importées ont été modifiées. À l\'étape 1, cliquez « Suivant » pour relancer l\'OCR et enregistrer les pages.';
    }
    if (step === 4 && !document.getElementById('docType')?.value) {
        return 'Sélectionnez et enregistrez d\'abord le type de document (étape 3).';
    }
    if (step === 5 && getSelectedDocOwnership() === 'multiple' && associatedClients.length < 2) {
        return 'Propriété multiple : associez au moins deux clients (étape 4).';
    }

    return 'Complétez les étapes précédentes avant de continuer.';
}

function canNavigateToStep(step) {
    if (encodageStatus === 'complete' && !isEncodageAdmin()) {
        return step === 5;
    }
    if (step === 1) {
        return true;
    }
    if (step === 2) {
        return scannedPages.length > 0;
    }
    if (!encodageId) {
        return false;
    }
    if (scanPagesDirty) {
        return false;
    }
    if (step === 4 && !document.getElementById('docType')?.value) {
        return false;
    }
    if (step === 5 && getSelectedDocOwnership() === 'multiple' && associatedClients.length < 2) {
        return false;
    }

    return true;
}

function setScanStepProcessing(processing) {
    ocrPipelineInProgress = processing;
    document.querySelectorAll('#nextStep1, #nextStep1Bar').forEach((btn) => {
        btn.disabled = processing;
    });
}

function bindStepperNavigation() {
    document.querySelectorAll('.encodage-wizard .stepper-header .step-item[data-step]').forEach((item) => {
        item.addEventListener('click', () => {
            const step = parseInt(item.dataset.step, 10);
            if (Number.isNaN(step)) {
                return;
            }

            if (!isEncodageEditable() && step < 5) {
                iziToast.info({
                    message: isEncodageAdmin()
                        ? 'Accès refusé à cette étape.'
                        : 'Encodage finalisé : les modifications ne sont plus possibles.',
                });

                return;
            }

            if (!canNavigateToStep(step)) {
                iziToast.warning({ message: getStepBlockedMessage(step) });

                return;
            }

            if (step === 5 && encodageId) {
                loadRecapitulatif();
            }

            goToStep(step);
        });
    });
}

function getSelectedDocOwnership() {
    const select = document.getElementById('docType');
    if (!select?.value) return null;
    const option = select.options[select.selectedIndex];
    return option?.dataset?.ownership || 'single';
}

function setAssociatedClientsFromServer(list) {
    associatedClients = (list || []).map((c) => ({
        id_client: Number(c.id_client),
        nom_complet: c.nom_complet || `Client #${c.id_client}`,
        tel: c.tel || '',
        email: c.email || '',
        is_primary: Boolean(c.is_primary),
    }));
    const primary = associatedClients.find((c) => c.is_primary) || associatedClients[0];
    clientId = primary?.id_client || null;
    const clientIdEl = document.getElementById('clientId');
    if (clientIdEl) clientIdEl.value = clientId || '';
    renderAssociatedClientsChips();
    updateAssociatedClientsHint();
}

function renderAssociatedClientsChips() {
    const host = document.getElementById('associatedClientsChips');
    if (!host) return;

    if (associatedClients.length === 0) {
        host.innerHTML = '<span class="text-muted small">Aucun client associé pour le moment.</span>';
        return;
    }

    host.innerHTML = associatedClients.map((c) => `
        <span class="badge bg-secondary d-inline-flex align-items-center gap-1 py-2 px-3" style="border-radius:12px;font-size:0.85rem;">
            <span>${escapeHtml(c.nom_complet)}${c.is_primary ? ' <em class="opacity-75">(principal)</em>' : ''}</span>
            <button type="button" class="btn-close btn-close-white btn-sm" style="font-size:0.55rem;"
                data-remove-associated-client="${c.id_client}"
                aria-label="Retirer ${escapeAttr(c.nom_complet)}"></button>
        </span>`).join('');

    host.querySelectorAll('[data-remove-associated-client]').forEach((btn) => {
        btn.addEventListener('click', () => {
            removeAssociatedClient(parseInt(btn.getAttribute('data-remove-associated-client'), 10));
        });
    });
}

function updateAssociatedClientsHint() {
    const hint = document.getElementById('associatedClientsHint');
    if (!hint) return;

    const ownership = getSelectedDocOwnership();
    if (ownership === 'multiple') {
        const n = associatedClients.length;
        if (n >= 2) {
            hint.textContent = `${n} clients associés. Vous pouvez enregistrer et continuer.`;
        } else if (n === 1) {
            hint.textContent = 'Propriété multiple : ajoutez au moins un second client avant de continuer.';
        } else {
            hint.textContent = 'Propriété multiple : associez au moins deux clients à ce document (un seul encodage).';
        }
    } else if (ownership === 'single') {
        hint.textContent = 'Propriété single : un seul client peut être associé à ce document.';
    } else {
        hint.textContent = 'Sélectionnez d\'abord le type de document (étape 3) pour connaître la propriété single ou multiple.';
    }
}

function projectedAssociatedClientCount(clientType) {
    if (clientType === 'existing') {
        return associatedClients.length;
    }

    return associatedClients.length + 1;
}

function validateClientStepComplete(andGoNext, { clientType = 'existing', clientCount = null } = {}) {
    if (!andGoNext) {
        return true;
    }

    if (getSelectedDocOwnership() !== 'multiple') {
        return true;
    }

    const count = clientCount ?? projectedAssociatedClientCount(clientType);
    if (count < 2) {
        iziToast.warning({
            message: 'Propriété multiple : associez au moins deux clients avant de continuer.',
        });
        return false;
    }

    return true;
}

function canAddMoreClients() {
    const ownership = getSelectedDocOwnership();
    if (ownership === 'single' && associatedClients.length >= 1) {
        iziToast.warning({ message: 'Ce type de document n\'accepte qu\'un seul client (ownership single).' });
        return false;
    }
    return true;
}

function addAssociatedClient(client) {
    if (!client?.id_client) return;
    const id = Number(client.id_client);
    if (associatedClients.some((c) => c.id_client === id)) {
        iziToast.info({ message: 'Ce client est déjà dans la liste.' });
        return;
    }
    if (!canAddMoreClients()) return;

    associatedClients.push({
        id_client: id,
        nom_complet: client.nom_complet || `Client #${id}`,
        tel: client.tel || '',
        email: client.email || '',
        is_primary: associatedClients.length === 0,
    });

    if (associatedClients.length === 1) {
        clientId = id;
        document.getElementById('clientId').value = String(id);
    }

    renderAssociatedClientsChips();
    updateAssociatedClientsHint();
}

function removeAssociatedClient(id) {
    associatedClients = associatedClients.filter((c) => c.id_client !== id);
    if (associatedClients.length > 0) {
        associatedClients[0].is_primary = true;
        clientId = associatedClients[0].id_client;
        document.getElementById('clientId').value = String(clientId);
    } else {
        clientId = null;
        document.getElementById('clientId').value = '';
    }
    renderAssociatedClientsChips();
    updateAssociatedClientsHint();
}

function applyResumedEncodage(data) {
    const enc = data.encodage;
    const pages = data.pages || [];

    setEncodageStatus(enc.status);

    encodageId = enc.id_encodage;
    document.getElementById('encodageId').value = encodageId;

    if (data.associated_clients?.length) {
        setAssociatedClientsFromServer(data.associated_clients);
    } else if (enc.id_client) {
        setAssociatedClientsFromServer([{
            id_client: enc.id_client,
            nom_complet: 'Client',
            is_primary: true,
        }]);
    } else {
        associatedClients = [];
        clientId = null;
        renderAssociatedClientsChips();
    }

    if (pages.length > 0) {
        scannedPages = pages.map((p) => ({
            id_page: p.id_page ?? null,
            page_number: p.page_number ?? null,
            image: p.file_path,
            blob: null,
            ocrText: p.ocr_text || '',
        }));
        updateScanPagesUI();
        const ocrArea = document.getElementById('ocrText');
        if (ocrArea) {
            ocrArea.value = scannedPages.map((p, i) => `--- Page ${i + 1} ---\n${p.ocrText || ''}`).join('\n\n');
        }
    }

    if (enc.id_doc) {
        document.getElementById('docType').value = enc.id_doc;
        loadDocumentInfo();
        document.getElementById('docMontant').value = enc.montant || '';
        if (enc.date_emission) setEncodageDate('docDateEmission', String(enc.date_emission).substring(0, 10));
        if (enc.date_expiration && currentDocDuree === 0) {
            setEncodageDate('docDateExpiration', String(enc.date_expiration).substring(0, 10));
        }
        updateDateFieldsState();
        calculateExpirationDate();
    }

    if (!enc.id_doc) goToStep(3);
    else if (!enc.id_client) goToStep(4);
    else {
        loadRecapitulatif();
        goToStep(5);
    }

    iziToast.info({ message: 'Encodage repris. Vous pouvez continuer où vous vous êtes arrêté.', position: 'topRight' });
}

function loadOpenCvOnce() {
    if (opencvReady && typeof cv !== 'undefined') {
        return Promise.resolve();
    }
    if (opencvLoadPromise) {
        return opencvLoadPromise;
    }

    opencvLoadPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-enc-opencv]');
        if (existing) {
            const poll = setInterval(() => {
                if (typeof cv !== 'undefined') {
                    clearInterval(poll);
                    opencvReady = true;
                    resolve();
                }
            }, 80);
            setTimeout(() => {
                clearInterval(poll);
                reject(new Error('OpenCV timeout'));
            }, 120000);
            return;
        }

        const script = document.createElement('script');
        script.src = OPENCV_JS_URL;
        script.async = true;
        script.dataset.encOpencv = '1';
        script.onload = () => {
            opencvReady = typeof cv !== 'undefined';
            resolve();
        };
        script.onerror = () => reject(new Error('Échec chargement OpenCV'));
        document.head.appendChild(script);
    });

    return opencvLoadPromise.catch((err) => {
        opencvLoadPromise = null;
        throw err;
    });
}

function loadTesseractOnce() {
    if (typeof Tesseract !== 'undefined') {
        return Promise.resolve();
    }
    if (tesseractLoadPromise) {
        return tesseractLoadPromise;
    }

    tesseractLoadPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = TESSERACT_JS_URL;
        script.async = true;
        script.dataset.encTesseract = '1';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Échec chargement Tesseract'));
        document.head.appendChild(script);
    });

    return tesseractLoadPromise.catch((err) => {
        tesseractLoadPromise = null;
        throw err;
    });
}

function attachScanStream(stream) {
    videoStream = stream;
    const mainVideo = document.getElementById('video');
    const modalVideo = document.getElementById('videoScanModal');
    if (mainVideo) mainVideo.srcObject = stream;
    if (modalVideo) modalVideo.srcObject = stream;
}

function getActiveScanVideo() {
    const modalEl = document.getElementById('encScanCameraModal');
    if (modalEl?.classList.contains('show')) {
        return document.getElementById('videoScanModal') || document.getElementById('video');
    }
    return document.getElementById('video');
}

function closeScanCameraModal() {
    const modalEl = document.getElementById('encScanCameraModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const instance = bootstrap.Modal.getInstance(modalEl);
    instance?.hide();
}

let encScanCameraModalInstance = null;

function getScanCameraModal() {
    const modalEl = document.getElementById('encScanCameraModal');
    if (!modalEl || typeof bootstrap === 'undefined') return null;
    if (!encScanCameraModalInstance) {
        encScanCameraModalInstance = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('shown.bs.modal', () => {
            if (videoStream) {
                attachScanStream(videoStream);
            }
        });
    }
    return encScanCameraModalInstance;
}

function openScanCameraModal() {
    const open = () => getScanCameraModal()?.show();
    if (videoStream) {
        open();
        return;
    }
    initializeCamera().then(open);
}

function setupScanCameraModal() {
    document.getElementById('btnExpandScanCamera')?.addEventListener('click', (e) => {
        e.preventDefault();
        openScanCameraModal();
    });
    document.getElementById('captureButtonModal')?.addEventListener('click', () => {
        captureImageWithDetection();
    });
}

// Initialiser la caméra
function initializeCamera() {
    if (videoStream) {
        attachScanStream(videoStream);
        return Promise.resolve(videoStream);
    }

    return navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1920 },
            height: { ideal: 1080 },
        },
    })
        .then((stream) => {
            attachScanStream(stream);
            return stream;
        })
        .catch((err) => {
            console.error("Erreur d'accès à la caméra:", err);
            iziToast.error({ title: 'Erreur', message: 'Impossible d\'accéder à la caméra.' });
            throw err;
        });
}

function loadPdfJsOnce() {
    if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
        return Promise.resolve(window.pdfjsLib);
    }
    if (pdfJsLoadPromise) {
        return pdfJsLoadPromise;
    }

    pdfJsLoadPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-enc-pdfjs]');
        if (existing) {
            const poll = setInterval(() => {
                if (window.pdfjsLib) {
                    clearInterval(poll);
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
                    resolve(window.pdfjsLib);
                }
            }, 50);
            setTimeout(() => {
                clearInterval(poll);
                if (!window.pdfjsLib) {
                    reject(new Error('pdf.js timeout'));
                }
            }, 15000);
            return;
        }

        const script = document.createElement('script');
        script.src = PDFJS_JS_URL;
        script.dataset.encPdfjs = '1';
        script.onload = () => {
            if (!window.pdfjsLib) {
                reject(new Error('pdf.js indisponible'));
                return;
            }
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
            resolve(window.pdfjsLib);
        };
        script.onerror = () => reject(new Error('Impossible de charger pdf.js'));
        document.head.appendChild(script);
    });

    return pdfJsLoadPromise;
}

function setPdfImportProgress(message, visible = true) {
    const el = document.getElementById('pdfImportProgress');
    if (!el) return;
    el.hidden = !visible;
    el.textContent = message || '';
}

function isPdfFile(file) {
    if (!file) return false;
    const name = (file.name || '').toLowerCase();
    return file.type === 'application/pdf' || name.endsWith('.pdf');
}

async function canvasToJpegBlob(canvas, quality = 0.92) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
    });
}

async function loadPdfDocumentFromFile(file) {
    const arrayBuffer = await file.arrayBuffer();
    const pdfjsLib = await loadPdfJsOnce();
    const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer) }).promise;

    return { pdf, name: file.name };
}

async function rasterizePdfDocument(pdf, fileName, existingCount = 0) {
    const scale = 2;
    const pagesAdded = [];

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
        if (existingCount + pagesAdded.length >= MAX_PDF_PAGES_TOTAL) {
            throw new Error(`Limite de ${MAX_PDF_PAGES_TOTAL} pages par encodage atteinte.`);
        }

        const page = await pdf.getPage(pageNum);
        const viewport = page.getViewport({ scale });
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport,
        }).promise;

        const blob = await canvasToJpegBlob(canvas);
        if (!blob) {
            throw new Error(`Impossible de convertir la page ${pageNum} du PDF.`);
        }

        pagesAdded.push({
            id_page: null,
            page_number: null,
            blob,
            image: canvas.toDataURL('image/jpeg', 0.92),
            ocrText: '',
            _isNewCapture: true,
            _sourcePdf: fileName,
            _sourcePdfPage: pageNum,
        });
    }

    return pagesAdded;
}

function getActiveImportedPdf() {
    return importedPdfs[pdfViewerState.pdfIndex] || null;
}

function findImportedPdfIndexByName(name) {
    return importedPdfs.findIndex((p) => p.name === name);
}

async function renderPdfPageToCanvas(canvas, pdfDoc, pageNum, container) {
    if (!canvas || !pdfDoc || !container) return;

    const page = await pdfDoc.getPage(pageNum);
    const baseViewport = page.getViewport({ scale: 1 });
    const pad = PDF_VIEWER_PADDING;
    const maxW = Math.max(80, container.clientWidth - pad);
    const maxH = Math.max(80, container.clientHeight - pad);
    const fitScale = Math.min(maxW / baseViewport.width, maxH / baseViewport.height);
    const pixelRatio = window.devicePixelRatio || 1;
    const renderScale = fitScale * pixelRatio;
    const viewport = page.getViewport({ scale: renderScale });
    const context = canvas.getContext('2d');

    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.style.width = `${Math.floor(viewport.width / pixelRatio)}px`;
    canvas.style.height = `${Math.floor(viewport.height / pixelRatio)}px`;

    await page.render({ canvasContext: context, viewport }).promise;
}

function shouldUseServerPdfRasterizer(file) {
    return Boolean(window.AUTHENTIQ_PDF_SERVICE_ENABLED)
        && file.size >= (window.AUTHENTIQ_PDF_SERVICE_MIN_BYTES || 0);
}

async function renderRasterizedPageToCanvas(canvas, imageSrc, container) {
    if (!canvas || !imageSrc || !container) return;

    await new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const pad = PDF_VIEWER_PADDING;
            const maxW = Math.max(80, container.clientWidth - pad);
            const maxH = Math.max(80, container.clientHeight - pad);
            const fitScale = Math.min(maxW / img.width, maxH / img.height, 1);
            const pixelRatio = window.devicePixelRatio || 1;
            const drawW = Math.floor(img.width * fitScale * pixelRatio);
            const drawH = Math.floor(img.height * fitScale * pixelRatio);
            const context = canvas.getContext('2d');

            canvas.width = drawW;
            canvas.height = drawH;
            canvas.style.width = `${Math.floor(drawW / pixelRatio)}px`;
            canvas.style.height = `${Math.floor(drawH / pixelRatio)}px`;
            context.drawImage(img, 0, 0, drawW, drawH);
            resolve();
        };
        img.onerror = () => reject(new Error('Aperçu page indisponible'));
        img.src = imageSrc;
    });
}

async function importPdfViaServer(file) {
    const formData = new FormData();
    formData.append('file', file, file.name);

    const response = await fetch(`${ENCODAGE_API}/rasterize-pdf`, {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || data.status !== 'success') {
        throw new Error(data.message || 'Rasterisation PDF serveur échouée.');
    }

    const pages = await Promise.all((data.pages || []).map(async (page) => {
        const image = page.image || '';
        const blobRes = await fetch(image);
        const blob = await blobRes.blob();

        return {
            id_page: null,
            page_number: null,
            blob,
            image,
            ocrText: '',
            _sourcePdf: file.name,
            _sourcePdfPage: page.page_number,
            _serverRendered: true,
        };
    }));

    return {
        entry: {
            id: `pdf-${Date.now()}`,
            name: file.name,
            pdfDoc: null,
            pageCount: pages.length,
            serverRendered: true,
        },
        pages,
    };
}

async function renderPdfViewerPanel() {
    const entry = getActiveImportedPdf();
    const canvas = document.getElementById('pdfViewerCanvas');
    const empty = document.getElementById('pdfViewerEmpty');
    const expandBtn = document.getElementById('pdfViewerExpand');
    const fileNameEl = document.getElementById('pdfViewerFileName');
    const controls = document.getElementById('pdfViewerControls');

    if (!entry || !canvas) {
        if (canvas) canvas.hidden = true;
        if (empty) empty.hidden = false;
        if (expandBtn) expandBtn.disabled = true;
        if (fileNameEl) fileNameEl.textContent = 'Aperçu';
        if (controls) controls.hidden = true;
        return;
    }

    pdfViewerState.pageNum = Math.min(Math.max(1, pdfViewerState.pageNum), entry.pageCount);
    const token = ++pdfViewerRenderToken;

    if (empty) empty.hidden = true;
    canvas.hidden = false;
    if (expandBtn) expandBtn.disabled = false;
    if (fileNameEl) fileNameEl.textContent = entry.name;
    if (controls) controls.hidden = false;

    if (entry.serverRendered || !entry.pdfDoc) {
        const page = scannedPages[pdfViewerState.pageNum - 1];
        if (page?.image) {
            await renderRasterizedPageToCanvas(canvas, page.image, document.getElementById('pdfViewerBody'));
        }
    } else {
        await renderPdfPageToCanvas(canvas, entry.pdfDoc, pdfViewerState.pageNum, document.getElementById('pdfViewerBody'));
    }
    if (token !== pdfViewerRenderToken) return;

    updatePdfViewerControls();
}

async function renderPdfViewerModalCanvas() {
    const entry = getActiveImportedPdf();
    const canvas = document.getElementById('pdfViewerModalCanvas');
    const container = document.getElementById('pdfViewerModalBody');
    const title = document.getElementById('pdfViewerModalTitle');
    if (!entry || !canvas || !container) return;

    if (title) title.textContent = entry.name;

    if (entry.serverRendered || !entry.pdfDoc) {
        const page = scannedPages[pdfViewerState.pageNum - 1];
        if (page?.image) {
            await renderRasterizedPageToCanvas(canvas, page.image, container);
        }
    } else {
        await renderPdfPageToCanvas(canvas, entry.pdfDoc, pdfViewerState.pageNum, container);
    }
    updatePdfViewerModalPager();
}

function updatePdfViewerControls() {
    const entry = getActiveImportedPdf();
    const pageLabel = document.getElementById('pdfViewerPageLabel');
    const select = document.getElementById('pdfViewerFileSelect');
    const prevBtn = document.getElementById('pdfViewerPrev');
    const nextBtn = document.getElementById('pdfViewerNext');

    if (!entry) return;

    if (pageLabel) {
        pageLabel.textContent = `${pdfViewerState.pageNum} / ${entry.pageCount}`;
    }
    if (prevBtn) prevBtn.disabled = pdfViewerState.pageNum <= 1;
    if (nextBtn) nextBtn.disabled = pdfViewerState.pageNum >= entry.pageCount;

    if (select) {
        select.hidden = importedPdfs.length <= 1;
        const currentValue = String(pdfViewerState.pdfIndex);
        if (select.options.length !== importedPdfs.length) {
            select.innerHTML = importedPdfs.map((p, i) =>
                `<option value="${i}">${escapeHtml(p.name)}</option>`,
            ).join('');
        }
        select.value = currentValue;
    }
}

function updatePdfViewerModalPager() {
    const entry = getActiveImportedPdf();
    const label = document.getElementById('pdfViewerModalPageLabel');
    const prevBtn = document.getElementById('pdfViewerModalPrev');
    const nextBtn = document.getElementById('pdfViewerModalNext');
    if (!entry) return;

    if (label) label.textContent = `${pdfViewerState.pageNum} / ${entry.pageCount}`;
    if (prevBtn) prevBtn.disabled = pdfViewerState.pageNum <= 1;
    if (nextBtn) nextBtn.disabled = pdfViewerState.pageNum >= entry.pageCount;
}

function showPdfInViewerByPageIndex(pageIndex) {
    const page = scannedPages[pageIndex];
    if (!page?._sourcePdf) return;

    const pdfIndex = findImportedPdfIndexByName(page._sourcePdf);
    if (pdfIndex < 0) return;

    pdfViewerState.pdfIndex = pdfIndex;
    pdfViewerState.pageNum = page._sourcePdfPage || 1;
    renderPdfViewerPanel();
    highlightPageGalleryThumb(pageIndex);
}

function highlightPageGalleryThumb(pageIndex) {
    activeGalleryPageIndex = pageIndex;
    document.querySelectorAll('.enc-pages-gallery__thumb').forEach((el) => {
        const idx = parseInt(el.getAttribute('data-view-page-index'), 10);
        el.classList.toggle('is-active', idx === pageIndex);
    });
}

function findScannedPageIndexForViewer() {
    const entry = getActiveImportedPdf();
    if (!entry) return -1;

    return scannedPages.findIndex(
        (p) => p._sourcePdf === entry.name && (p._sourcePdfPage || 1) === pdfViewerState.pageNum,
    );
}

function changePdfViewerPage(delta) {
    const entry = getActiveImportedPdf();
    if (!entry) return;

    pdfViewerState.pageNum = Math.min(
        entry.pageCount,
        Math.max(1, pdfViewerState.pageNum + delta),
    );
    renderPdfViewerPanel();
    const idx = findScannedPageIndexForViewer();
    if (idx >= 0) highlightPageGalleryThumb(idx);
    if (pdfViewerModalInstance) {
        renderPdfViewerModalCanvas();
    }
}

function changePdfViewerFile(index) {
    if (!importedPdfs[index]) return;
    pdfViewerState.pdfIndex = index;
    pdfViewerState.pageNum = 1;
    renderPdfViewerPanel();
    const idx = findScannedPageIndexForViewer();
    if (idx >= 0) highlightPageGalleryThumb(idx);
    if (pdfViewerModalInstance) {
        renderPdfViewerModalCanvas();
    }
}

function openPdfViewerModal() {
    const entry = getActiveImportedPdf();
    if (!entry || typeof bootstrap === 'undefined') return;

    const modalEl = document.getElementById('pdfViewerModal');
    if (!modalEl) return;

    if (!pdfViewerModalInstance) {
        pdfViewerModalInstance = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('shown.bs.modal', () => {
            renderPdfViewerModalCanvas();
        });
    }

    pdfViewerModalInstance.show();
}

function setupPdfViewer() {
    document.getElementById('pdfViewerExpand')?.addEventListener('click', (e) => {
        e.preventDefault();
        openPdfViewerModal();
    });

    document.getElementById('pdfViewerPrev')?.addEventListener('click', () => changePdfViewerPage(-1));
    document.getElementById('pdfViewerNext')?.addEventListener('click', () => changePdfViewerPage(1));
    document.getElementById('pdfViewerModalPrev')?.addEventListener('click', () => changePdfViewerPage(-1));
    document.getElementById('pdfViewerModalNext')?.addEventListener('click', () => changePdfViewerPage(1));

    document.getElementById('pdfViewerFileSelect')?.addEventListener('change', (e) => {
        changePdfViewerFile(parseInt(e.target.value, 10) || 0);
    });

    let pdfModalResizeTimer = null;
    window.addEventListener('resize', () => {
        if (!pdfViewerModalInstance || !document.getElementById('pdfViewerModal')?.classList.contains('show')) {
            return;
        }
        clearTimeout(pdfModalResizeTimer);
        pdfModalResizeTimer = setTimeout(() => renderPdfViewerModalCanvas(), 150);
    });

    document.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('[data-view-page-index]');
        if (!viewBtn) return;
        const index = parseInt(viewBtn.getAttribute('data-view-page-index'), 10);
        if (!Number.isNaN(index)) {
            e.preventDefault();
            showPdfInViewerByPageIndex(index);
        }
    });
}

async function importPdfFile(file) {
    if (shouldUseServerPdfRasterizer(file)) {
        return importPdfViaServer(file);
    }

    const { pdf, name } = await loadPdfDocumentFromFile(file);
    const entry = {
        id: `pdf-${Date.now()}`,
        name,
        pdfDoc: pdf,
        pageCount: pdf.numPages,
    };
    const pages = await rasterizePdfDocument(pdf, name, 0);

    return { entry, pages };
}

async function handlePdfFiles(fileList) {
    if (pdfImportInProgress) {
        iziToast.info({ message: 'Import PDF déjà en cours…' });
        return;
    }
    if (ocrPipelineInProgress) {
        iziToast.info({ message: 'Patientez la fin du traitement OCR en cours…' });
        return;
    }

    const files = Array.from(fileList || []).filter(isPdfFile);
    if (files.length === 0) {
        iziToast.warning({ message: 'Sélectionnez un fichier PDF.' });
        return;
    }

    if (files.length > 1) {
        iziToast.info({ message: 'Un seul document par encodage : seul le premier PDF sera importé.' });
    }

    const file = files[0];
    if (file.size > MAX_PDF_FILE_BYTES) {
        iziToast.error({ message: `"${file.name}" dépasse la taille max. de 50 Mo.` });
        return;
    }

    const hadPreviousDocument = scannedPages.length > 0 || importedPdfs.length > 0;

    pdfImportInProgress = true;
    showEncPagesLoader('scanPagesSummary');
    setPdfImportProgress(hadPreviousDocument ? 'Remplacement du document…' : 'Import du PDF en cours…');

    try {
        setPdfImportProgress(`Lecture de ${file.name}…`);
        if (shouldUseServerPdfRasterizer(file)) {
            setPdfImportProgress(`Rasterisation serveur de ${file.name}…`);
        }
        const { entry, pages } = await importPdfFile(file);

        if (pages.length === 0) {
            iziToast.warning({ message: 'Aucune page extraite du PDF.' });
            return;
        }

        importedPdfs = [entry];
        scannedPages = pages;
        pdfViewerState = { pdfIndex: 0, pageNum: 1 };
        pdfViewerRenderToken += 1;

        const ocrArea = document.getElementById('ocrText');
        if (ocrArea) {
            ocrArea.value = '';
        }

        const ocrProgress = document.getElementById('ocrProgress');
        if (ocrProgress) {
            ocrProgress.style.display = 'none';
        }

        await renderPdfViewerPanel();

        markScanPagesDirty();
        updateScanPagesUI();
        highlightPageGalleryThumb(0);

        const pageLabel = pages.length === 1 ? '1 page importée' : `${pages.length} pages importées`;
        iziToast.success({
            message: hadPreviousDocument
                ? `Document remplacé : ${pageLabel}.`
                : `${pageLabel}.`,
            timeout: 3000,
        });
    } catch (err) {
        console.error('PDF import:', err);
        iziToast.error({ message: err.message || 'Erreur lors de l\'import PDF.' });
    } finally {
        pdfImportInProgress = false;
        hideEncPagesLoader('scanPagesSummary');
        setPdfImportProgress('', false);
        const input = document.getElementById('pdfFileInput');
        if (input) input.value = '';
    }
}

function setupPdfUpload() {
    const input = document.getElementById('pdfFileInput');
    const dropZone = document.getElementById('pdfDropZone');
    const btnSelect = document.getElementById('btnSelectPdf');

    btnSelect?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        input?.click();
    });

    dropZone?.addEventListener('click', (e) => {
        if (e.target.closest('#btnSelectPdf')) return;
        input?.click();
    });

    dropZone?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input?.click();
        }
    });

    input?.addEventListener('change', () => {
        if (input.files?.length) {
            handlePdfFiles(input.files);
        }
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropZone?.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropZone?.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('is-dragover');
        });
    });

    dropZone?.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files?.length) {
            handlePdfFiles(files);
        }
    });

    setupPdfViewer();
}

function setupEventListeners() {
    setupPdfUpload();
    document.getElementById('nextStep1')?.addEventListener('click', () => proceedFromScanStep());
    document.getElementById('nextStep1Bar')?.addEventListener('click', () => proceedFromScanStep());
    updateScanPagesUI();

    document.getElementById('nextStep2').addEventListener('click', () => goToStep(3));

    document.getElementById('clientType').addEventListener('change', toggleClientType);
    toggleClientType();
    setupClientSearch();
    document.getElementById('saveStep3').addEventListener('click', saveDocumentInfo);
    document.getElementById('nextStep3').addEventListener('click', () => saveDocumentInfo(true));

    document.getElementById('docType').addEventListener('change', loadDocumentInfo);
    try {
        initEncodageDatePickers();
    } catch (err) {
        console.error('Flatpickr encodage:', err);
    }

    document.getElementById('saveStep4').addEventListener('click', saveClientInfo);
    document.getElementById('nextStep4').addEventListener('click', () => saveClientInfo(true));

    document.getElementById('submitBtn')?.addEventListener('click', finalizeEncodage);

    bindWizardNavigation();
    bindStepperNavigation();
    syncWizardEditability();
    setupEncodageOtp();
}

/** Clic sur Précédent / icônes enfants (délégation) */
function bindWizardNavigation() {
    const wizard = document.querySelector('.encodage-wizard');
    if (!wizard) return;

    const prevMap = {
        prevStep2: 1,
        prevStep3: 2,
        prevStep4: 3,
        prevStep5: 4,
    };

    wizard.addEventListener('click', (e) => {
        const btn = e.target.closest('button[id^="prevStep"]');
        if (!btn || !prevMap[btn.id]) return;
        e.preventDefault();

        const targetStep = prevMap[btn.id];
        if (!isEncodageEditable() && targetStep < 5) {
            iziToast.info({
                message: 'Encodage finalisé : les modifications ne sont plus possibles.',
            });

            return;
        }

        if (targetStep === 5 && encodageId) {
            loadRecapitulatif();
        }

        goToStep(targetStep);
    });
}

// Capturer l'image avec détection
async function captureImageWithDetection() {
    const video = getActiveScanVideo();
    const canvas = document.getElementById('canvas');
    if (!video || !canvas || !video.videoWidth) {
        iziToast.warning({ message: 'Caméra non prête. Patientez ou autorisez l\'accès.' });
        return;
    }

    const context = canvas.getContext('2d');

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    originalImageData = context.getImageData(0, 0, canvas.width, canvas.height);

    closeScanCameraModal();

    iziToast.info({ message: 'Détection en cours...', timeout: 2000 });

    try {
        await loadOpenCvOnce();
    } catch (_) {
        /* repli détection simple */
    }

    if (opencvReady && typeof cv !== 'undefined') {
        detectWithOpenCV(canvas);
    } else {
        detectDocumentCorners(canvas);
    }
    showCropInterface();
}

// Détection avec OpenCV (plus précise)
function detectWithOpenCV(canvas) {
    if (typeof cv === 'undefined') {
        detectDocumentCorners(canvas);
        return;
    }
    
    try {
        const src = cv.imread(canvas);
        const gray = new cv.Mat();
        const blurred = new cv.Mat();
        const edges = new cv.Mat();
        const contours = new cv.MatVector();
        const hierarchy = new cv.Mat();
        
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0);
        cv.Canny(blurred, edges, 50, 150);
        
        const kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(3, 3));
        cv.dilate(edges, edges, kernel);
        
        cv.findContours(edges, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);
        
        let maxArea = 0;
        let bestApprox = null;
        const minArea = (canvas.width * canvas.height) * 0.1;
        
        for (let i = 0; i < contours.size(); i++) {
            const cnt = contours.get(i);
            const area = cv.contourArea(cnt);
            
            if (area < minArea) continue;
            
            const peri = cv.arcLength(cnt, true);
            const approx = new cv.Mat();
            cv.approxPolyDP(cnt, approx, 0.02 * peri, true);
            
            if (approx.rows === 4 && area > maxArea) {
                maxArea = area;
                if (bestApprox) bestApprox.delete();
                bestApprox = approx.clone();
            }
            approx.delete();
        }
        
        if (bestApprox && bestApprox.rows === 4) {
            cropCorners = [];
            for (let i = 0; i < 4; i++) {
                cropCorners.push({
                    x: bestApprox.data32S[i * 2],
                    y: bestApprox.data32S[i * 2 + 1]
                });
            }
            cropCorners = orderCorners(cropCorners);
            iziToast.success({ message: 'Document détecté avec OpenCV!' });
        } else {
            detectDocumentCorners(canvas);
        }
        
        src.delete(); gray.delete(); blurred.delete(); edges.delete();
        kernel.delete(); contours.delete(); hierarchy.delete();
        if (bestApprox) bestApprox.delete();
        
    } catch (e) {
        console.error('Erreur OpenCV:', e);
        detectDocumentCorners(canvas);
    }
}

// Ordonner les coins
function orderCorners(corners) {
    const center = {
        x: corners.reduce((sum, p) => sum + p.x, 0) / 4,
        y: corners.reduce((sum, p) => sum + p.y, 0) / 4
    };
    
    const sorted = [null, null, null, null];
    corners.forEach(corner => {
        if (corner.x < center.x && corner.y < center.y) sorted[0] = corner;
        else if (corner.x > center.x && corner.y < center.y) sorted[1] = corner;
        else if (corner.x > center.x && corner.y > center.y) sorted[2] = corner;
        else sorted[3] = corner;
    });
    
    return sorted.every(c => c !== null) ? sorted : corners;
}

// Détection basique
function detectDocumentCorners(canvas) {
    const context = canvas.getContext('2d');
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const grayData = convertToGrayscale(imageData);
    const edges = detectEdges(grayData, canvas.width, canvas.height);
    const contours = findLargestContour(edges, canvas.width, canvas.height);
    
    if (contours && contours.length >= 4) {
        cropCorners = approximateQuadrilateral(contours, canvas.width, canvas.height);
        iziToast.success({ message: 'Document détecté!' });
    } else {
        const margin = Math.min(canvas.width, canvas.height) * 0.1;
        cropCorners = [
            { x: margin, y: margin },
            { x: canvas.width - margin, y: margin },
            { x: canvas.width - margin, y: canvas.height - margin },
            { x: margin, y: canvas.height - margin }
        ];
        iziToast.warning({ message: 'Ajustez manuellement les coins.' });
    }
}

function convertToGrayscale(imageData) {
    const data = imageData.data;
    const grayData = new Uint8ClampedArray(data.length / 4);
    for (let i = 0; i < data.length; i += 4) {
        grayData[i / 4] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    return grayData;
}

function detectEdges(grayData, width, height) {
    const edges = new Uint8ClampedArray(width * height);
    const sobelX = [-1, 0, 1, -2, 0, 2, -1, 0, 1];
    const sobelY = [-1, -2, -1, 0, 0, 0, 1, 2, 1];
    
    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            let gx = 0, gy = 0;
            for (let ky = -1; ky <= 1; ky++) {
                for (let kx = -1; kx <= 1; kx++) {
                    const idx = (y + ky) * width + (x + kx);
                    const kernelIdx = (ky + 1) * 3 + (kx + 1);
                    gx += grayData[idx] * sobelX[kernelIdx];
                    gy += grayData[idx] * sobelY[kernelIdx];
                }
            }
            edges[y * width + x] = Math.sqrt(gx * gx + gy * gy) > 100 ? 255 : 0;
        }
    }
    return edges;
}

function findLargestContour(edges, width, height) {
    const points = [];
    for (let y = 0; y < height; y += 4) {
        for (let x = 0; x < width; x += 4) {
            if (edges[y * width + x] > 0) points.push({ x, y });
        }
    }
    return points;
}

function approximateQuadrilateral(points, width, height) {
    if (points.length < 4) return null;
    
    let topLeft = points[0], topRight = points[0], bottomLeft = points[0], bottomRight = points[0];
    
    points.forEach(p => {
        if (p.x + p.y < topLeft.x + topLeft.y) topLeft = p;
        if (p.x - p.y > topRight.x - topRight.y) topRight = p;
        if (p.y - p.x > bottomLeft.y - bottomLeft.x) bottomLeft = p;
        if (p.x + p.y > bottomRight.x + bottomRight.y) bottomRight = p;
    });
    
    const corners = [topLeft, topRight, bottomRight, bottomLeft];
    const minDistance = Math.min(width, height) * 0.2;
    
    for (let i = 0; i < corners.length; i++) {
        for (let j = i + 1; j < corners.length; j++) {
            const dist = Math.sqrt(Math.pow(corners[i].x - corners[j].x, 2) + Math.pow(corners[i].y - corners[j].y, 2));
            if (dist < minDistance) {
                const margin = Math.min(width, height) * 0.1;
                return [
                    { x: margin, y: margin },
                    { x: width - margin, y: margin },
                    { x: width - margin, y: height - margin },
                    { x: margin, y: height - margin }
                ];
            }
        }
    }
    
    return corners;
}

function showCropInterface() {
    document.getElementById('previewContainer').style.display = 'block';
    syncScanStepNextButton();
    drawCropOverlay();
    
    const canvas = document.getElementById('canvas');
    canvas.addEventListener('mousedown', onCropMouseDown);
    canvas.addEventListener('mousemove', onCropMouseMove);
    canvas.addEventListener('mouseup', onCropMouseUp);
    canvas.addEventListener('mouseleave', onCropMouseUp);
    canvas.addEventListener('touchstart', onCropTouchStart, { passive: false });
    canvas.addEventListener('touchmove', onCropTouchMove, { passive: false });
    canvas.addEventListener('touchend', onCropTouchEnd);
    
    addCropControls();
}

function addCropControls() {
    const controls = document.querySelector('.crop-controls');
    
    if (!document.getElementById('resetDetection')) {
        const resetBtn = document.createElement('button');
        resetBtn.id = 'resetDetection';
        resetBtn.className = 'btn btn-warning me-2';
        resetBtn.innerHTML = '<i class="fas fa-redo"></i> Redétecter';
        resetBtn.onclick = () => {
            const canvas = document.getElementById('canvas');
            if (opencvReady) detectWithOpenCV(canvas);
            else detectDocumentCorners(canvas);
            drawCropOverlay();
        };
        controls.insertBefore(resetBtn, controls.firstChild);
    }
    
    if (!document.getElementById('applyPerspective')) {
        const applyBtn = document.createElement('button');
        applyBtn.id = 'applyPerspective';
        applyBtn.className = 'btn btn-success me-2';
        applyBtn.innerHTML = '<i class="fas fa-check"></i> Appliquer';
        applyBtn.onclick = applyPerspectiveCorrectionFast;
        controls.insertBefore(applyBtn, document.getElementById('nextStep1'));
    }
    
    if (!document.getElementById('enhanceImage')) {
        const enhanceBtn = document.createElement('button');
        enhanceBtn.id = 'enhanceImage';
        enhanceBtn.className = 'btn btn-info me-2';
        enhanceBtn.innerHTML = '<i class="fas fa-magic"></i> Améliorer';
        enhanceBtn.onclick = enhanceImage;
        enhanceBtn.style.display = 'none';
        controls.appendChild(enhanceBtn);
    }
    
    if (!document.getElementById('validatePage')) {
        const validateBtn = document.createElement('button');
        validateBtn.id = 'validatePage';
        validateBtn.type = 'button';
        validateBtn.className = 'btn btn-success me-2';
        validateBtn.innerHTML = '<iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon> Valider cette page';
        validateBtn.onclick = () => commitCurrentCaptureToScan();
        validateBtn.style.display = 'none';
        controls.insertBefore(validateBtn, document.getElementById('nextStep1'));
    }

    if (!document.getElementById('addPage')) {
        const addPageBtn = document.createElement('button');
        addPageBtn.id = 'addPage';
        addPageBtn.type = 'button';
        addPageBtn.className = 'btn btn-primary me-2';
        addPageBtn.innerHTML = '<iconify-icon icon="solar:add-circle-bold-duotone"></iconify-icon> Ajouter une autre page';
        addPageBtn.onclick = addPageToScan;
        addPageBtn.style.display = 'none';
        controls.insertBefore(addPageBtn, document.getElementById('nextStep1'));
    }
}

function hasPendingCapture() {
    const canvas = document.getElementById('canvas');
    return Boolean(
        capturedImageBlob ||
        (originalImageData && canvas && canvas.width > 0 && canvas.height > 0)
    );
}

function renderScanPagesGalleryHtml() {
    if (scannedPages.length === 0) {
        return '';
    }

    return scannedPages
        .map((page, i) => {
            const num = page.page_number || i + 1;
            const active = i === activeGalleryPageIndex ? ' is-active' : '';
            const thumbSrc = page.image || '';
            const thumbInner = thumbSrc
                ? `<img src="${escapeAttr(thumbSrc)}" alt="" loading="lazy">`
                : `<span class="enc-pages-gallery__placeholder" aria-hidden="true"><iconify-icon icon="solar:document-bold-duotone"></iconify-icon></span>`;

            return `<button type="button" class="enc-pages-gallery__thumb${active}" data-view-page-index="${i}" role="listitem" aria-label="Page ${num}" title="Page ${num}">
                ${thumbInner}
                <span class="enc-pages-gallery__badge">${num}</span>
            </button>`;
        })
        .join('');
}

function getScanPagesCountLabel(n) {
    if (n === 0) {
        return '';
    }
    if (n === 1) {
        return '1 page importée';
    }

    return `${n} pages importées`;
}

function syncOcrTextFromPages() {
    const ocrArea = document.getElementById('ocrText');
    if (!ocrArea) {
        return;
    }

    ocrArea.value = scannedPages
        .map((p, i) => `--- Page ${i + 1} ---\n${p.ocrText || ''}`)
        .join('\n\n');
}

function applyServerPagesToScanned(pages) {
    if (!Array.isArray(pages) || pages.length === 0) {
        scannedPages = [];
        syncOcrTextFromPages();
        updateScanPagesUI();

        return;
    }

    scannedPages = pages.map((p) => ({
        id_page: p.id_page ?? null,
        page_number: p.page_number ?? null,
        image: p.file_path || '',
        blob: null,
        ocrText: p.ocr_text || '',
        _isNewCapture: false,
    }));
    clearScanPagesDirty();
    syncOcrTextFromPages();
    updateScanPagesUI();
}

function mergeServerPagesIntoScanned(serverPages) {
    if (!Array.isArray(serverPages) || serverPages.length === 0) {
        return;
    }

    serverPages.forEach((p, idx) => {
        const pageNumber = p.page_number ?? idx + 1;
        const i = pageNumber - 1;
        const local = scannedPages[i];
        if (!local) {
            return;
        }

        local.id_page = p.id_page ?? local.id_page;
        local.page_number = pageNumber;
        if (p.file_path) {
            local.image = p.file_path;
        }
        if (p.ocr_text !== undefined) {
            local.ocrText = p.ocr_text || '';
        }
        local._isNewCapture = false;
        if (local.id_page && !pageNeedsFileUpload(local)) {
            local.blob = null;
        }
    });
    syncOcrTextFromPages();
    updateScanPagesUI();
}

const OCR_SAVE_BATCH_SIZE = 8;
const SKIP_CLIENT_OCR_PAGE_COUNT = 10;

async function parseSaveOcrResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    let data = null;

    if (contentType.includes('application/json')) {
        try {
            data = await response.json();
        } catch (_) {
            data = null;
        }
    }

    if (!response.ok) {
        const fallback = response.status === 413
            ? 'Fichier trop volumineux pour le serveur (limite PHP/nginx). Réessayez avec un PDF plus léger.'
            : `Erreur serveur (${response.status}).`;
        const message = data?.message || fallback;
        throw new Error(message);
    }

    if (!data) {
        throw new Error('Réponse serveur invalide.');
    }

    if (data.status !== 'success') {
        throw new Error(data.message || 'Échec de la sauvegarde.');
    }

    return data;
}

function buildSaveOcrFormDataForBatch(startIndex, endIndex, completePages) {
    const formData = new FormData();

    scannedPages.forEach((page, index) => {
        if (page.id_page) {
            formData.append(`pageId_${index}`, String(page.id_page));
        }
        const inBatch = index >= startIndex && index < endIndex;
        if (inBatch && pageNeedsFileUpload(page) && page.blob instanceof Blob && page.blob.size > 0) {
            formData.append(`file_${index}`, page.blob, `document_page_${index + 1}.jpg`);
        }
        formData.append(`ocr_${index}`, page.ocrText || '');
    });

    formData.append('ocrText', document.getElementById('ocrText').value);
    formData.append('pageCount', scannedPages.length);
    if (encodageId) {
        formData.append('encodageId', encodageId);
    }
    if (!completePages) {
        formData.append('partialSave', '1');
    }

    return formData;
}

async function postSaveOcrBatch(startIndex, endIndex, completePages) {
    const formData = buildSaveOcrFormDataForBatch(startIndex, endIndex, completePages);
    const response = await fetch(`${ENCODAGE_API}/save-image-ocr`, {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    });

    return parseSaveOcrResponse(response);
}

function syncScanStepNextButton() {
    const actions = document.getElementById('scanStepActions');
    const n = scannedPages.length;
    if (!actions) {
        return;
    }

    actions.hidden = n === 0;
}

function updateScanPagesUI() {
    const countEl = document.getElementById('scanPagesCount');
    const listEl = document.getElementById('scanPagesList');
    const ocrCountEl = document.getElementById('ocrPagesCount');
    const ocrListEl = document.getElementById('scanPagesListOcr');
    const ocrToolbar = document.getElementById('ocrPagesToolbar');
    const summaryEl = document.getElementById('scanPagesSummary');
    const addBtn = document.getElementById('addPage');
    const validateBtn = document.getElementById('validatePage');
    const n = scannedPages.length;
    const countLabel = getScanPagesCountLabel(n);
    const galleryHtml = renderScanPagesGalleryHtml();

    if (countEl) countEl.textContent = countLabel;
    if (ocrCountEl) ocrCountEl.textContent = countLabel;

    if (summaryEl) summaryEl.hidden = n === 0;
    if (ocrToolbar) ocrToolbar.hidden = n === 0;

    if (listEl) listEl.innerHTML = galleryHtml;
    if (ocrListEl) ocrListEl.innerHTML = galleryHtml;

    if (addBtn) {
        addBtn.title = n === 0
            ? 'Après la première page, pour scanner la page suivante'
            : 'Capturer et enregistrer une page supplémentaire';
    }

    if (validateBtn && !hasPendingCapture()) {
        validateBtn.style.display = 'none';
    }

    syncScanStepNextButton();
}

function commitCurrentCaptureToScan(silent = false) {
    return new Promise((resolve) => {
        if (!hasPendingCapture()) {
            resolve(false);
            return;
        }

        const canvas = document.getElementById('canvas');
        canvas.toBlob((blob) => {
            if (!blob) {
                resolve(false);
                return;
            }

            scannedPages.push({
                id_page: null,
                page_number: null,
                blob,
                image: canvas.toDataURL('image/jpeg', 0.95),
                ocrText: '',
                _isNewCapture: true,
            });
            markScanPagesDirty();
            capturedImageBlob = null;
            updateScanPagesUI();

            if (!silent) {
                const n = scannedPages.length;
                const message = n === 1
                    ? 'Page enregistrée. Document à une page : cliquez « Suivant », ou « Ajouter une autre page » si le document en compte plusieurs.'
                    : `Page ${n} enregistrée. Scannez la suivante ou cliquez « Suivant ».`;
                iziToast.success({ message, timeout: 4000 });
            }

            document.getElementById('validatePage')?.style.setProperty('display', 'none');
            document.getElementById('addPage')?.style.setProperty('display', 'none');
            document.getElementById('enhanceImage')?.style.setProperty('display', 'none');
            recaptureImage();
            resolve(true);
        }, 'image/jpeg', 0.95);
    });
}

function proceedFromScanStep() {
    if (pdfImportInProgress) {
        iziToast.info({ message: 'Patientez pendant l\'import du PDF…' });
        return;
    }
    if (ocrPipelineInProgress) {
        return;
    }
    if (scannedPages.length === 0) {
        iziToast.warning({
            message: 'Joignez au moins un PDF scanné (imprimante-scanner) avant de continuer.',
        });
        return;
    }
    if (!goToStep(2)) {
        return;
    }
    processAllPagesOCR();
}

function showScanActionButtons() {
    const validateBtn = document.getElementById('validatePage');
    const addBtn = document.getElementById('addPage');
    const enhanceBtn = document.getElementById('enhanceImage');
    if (validateBtn) validateBtn.style.display = 'inline-block';
    if (addBtn) addBtn.style.display = 'inline-block';
    if (enhanceBtn) enhanceBtn.style.display = 'inline-block';
}

function drawCropOverlay() {
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');
    
    if (originalImageData) context.putImageData(originalImageData, 0, 0);
    
    context.fillStyle = 'rgba(0, 0, 0, 0.5)';
    context.fillRect(0, 0, canvas.width, canvas.height);
    
    context.save();
    context.globalCompositeOperation = 'destination-out';
    context.beginPath();
    context.moveTo(cropCorners[0].x, cropCorners[0].y);
    for (let i = 1; i < cropCorners.length; i++) {
        context.lineTo(cropCorners[i].x, cropCorners[i].y);
    }
    context.closePath();
    context.fill();
    context.restore();
    
    context.strokeStyle = '#00ff00';
    context.lineWidth = 3;
    context.setLineDash([10, 5]);
    context.beginPath();
    context.moveTo(cropCorners[0].x, cropCorners[0].y);
    for (let i = 1; i < cropCorners.length; i++) {
        context.lineTo(cropCorners[i].x, cropCorners[i].y);
    }
    context.closePath();
    context.stroke();
    context.setLineDash([]);
    
    cropCorners.forEach((corner, index) => {
        context.fillStyle = 'rgba(0, 0, 0, 0.3)';
        context.beginPath();
        context.arc(corner.x + 2, corner.y + 2, 12, 0, 2 * Math.PI);
        context.fill();
        
        context.fillStyle = '#00ff00';
        context.beginPath();
        context.arc(corner.x, corner.y, 12, 0, 2 * Math.PI);
        context.fill();
        
        context.strokeStyle = '#ffffff';
        context.lineWidth = 3;
        context.stroke();
        
        context.fillStyle = '#000';
        context.font = 'bold 12px Arial';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(index + 1, corner.x, corner.y);
    });
}

function getCanvasCoordinates(e, canvas) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: (e.clientX - rect.left) * (canvas.width / rect.width),
        y: (e.clientY - rect.top) * (canvas.height / rect.height)
    };
}

function onCropMouseDown(e) {
    const canvas = e.target;
    const coords = getCanvasCoordinates(e, canvas);
    cropCorners.forEach((corner, index) => {
        const distance = Math.sqrt(Math.pow(coords.x - corner.x, 2) + Math.pow(coords.y - corner.y, 2));
        if (distance < 20) {
            isDragging = true;
            dragCornerIndex = index;
            canvas.style.cursor = 'grabbing';
        }
    });
}

function onCropMouseMove(e) {
    const canvas = e.target;
    const coords = getCanvasCoordinates(e, canvas);
    
    if (isDragging && dragCornerIndex !== -1) {
        cropCorners[dragCornerIndex] = {
            x: Math.max(0, Math.min(canvas.width, coords.x)),
            y: Math.max(0, Math.min(canvas.height, coords.y))
        };
        drawCropOverlay();
    } else {
        let overCorner = false;
        cropCorners.forEach(corner => {
            const distance = Math.sqrt(Math.pow(coords.x - corner.x, 2) + Math.pow(coords.y - corner.y, 2));
            if (distance < 20) overCorner = true;
        });
        canvas.style.cursor = overCorner ? 'grab' : 'default';
    }
}

function onCropMouseUp(e) {
    isDragging = false;
    dragCornerIndex = -1;
    e.target.style.cursor = 'default';
}

function getTouchCoordinates(e, canvas) {
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    return {
        x: (touch.clientX - rect.left) * (canvas.width / rect.width),
        y: (touch.clientY - rect.top) * (canvas.height / rect.height)
    };
}

function onCropTouchStart(e) {
    e.preventDefault();
    const canvas = e.target;
    const coords = getTouchCoordinates(e, canvas);
    cropCorners.forEach((corner, index) => {
        const distance = Math.sqrt(Math.pow(coords.x - corner.x, 2) + Math.pow(coords.y - corner.y, 2));
        if (distance < 30) {
            isDragging = true;
            dragCornerIndex = index;
        }
    });
}

function onCropTouchMove(e) {
    if (!isDragging) return;
    e.preventDefault();
    const canvas = e.target;
    const coords = getTouchCoordinates(e, canvas);
    cropCorners[dragCornerIndex] = {
        x: Math.max(0, Math.min(canvas.width, coords.x)),
        y: Math.max(0, Math.min(canvas.height, coords.y))
    };
    drawCropOverlay();
}

function onCropTouchEnd(e) {
    isDragging = false;
    dragCornerIndex = -1;
}

// CORRECTION PERSPECTIVE RAPIDE - SANS OVERLAY
function applyPerspectiveCorrectionFast() {
    if (opencvReady && typeof cv !== 'undefined') {
        applyPerspectiveWithOpenCV();
        return;
    }
    
    // Créer un canvas temporaire PROPRE avec l'image originale
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImageData.width;
    tempCanvas.height = originalImageData.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(originalImageData, 0, 0);
    
    const srcCorners = cropCorners;
    const width1 = Math.sqrt(Math.pow(srcCorners[1].x - srcCorners[0].x, 2) + Math.pow(srcCorners[1].y - srcCorners[0].y, 2));
    const width2 = Math.sqrt(Math.pow(srcCorners[2].x - srcCorners[3].x, 2) + Math.pow(srcCorners[2].y - srcCorners[3].y, 2));
    const height1 = Math.sqrt(Math.pow(srcCorners[3].x - srcCorners[0].x, 2) + Math.pow(srcCorners[3].y - srcCorners[0].y, 2));
    const height2 = Math.sqrt(Math.pow(srcCorners[2].x - srcCorners[1].x, 2) + Math.pow(srcCorners[2].y - srcCorners[1].y, 2));
    
    const avgWidth = (width1 + width2) / 2;
    const avgHeight = (height1 + height2) / 2;
    
    let destWidth, destHeight;
    const ratio = avgWidth / avgHeight;
    const a4Ratio = 1 / 1.41;
    
    if (ratio > a4Ratio) {
        destWidth = avgWidth;
        destHeight = avgWidth * 1.41;
    } else {
        destHeight = avgHeight;
        destWidth = avgHeight / 1.41;
    }
    
    const minX = Math.min(...srcCorners.map(c => c.x));
    const maxX = Math.max(...srcCorners.map(c => c.x));
    const minY = Math.min(...srcCorners.map(c => c.y));
    const maxY = Math.max(...srcCorners.map(c => c.y));
    
    // Canvas de destination PROPRE
    const canvas = document.getElementById('canvas');
    canvas.width = Math.round(destWidth);
    canvas.height = Math.round(destHeight);
    const ctx = canvas.getContext('2d');
    
    // Dessiner l'image SANS overlay
    ctx.drawImage(
        tempCanvas,
        minX, minY, maxX - minX, maxY - minY,
        0, 0, destWidth, destHeight
    );
    
    originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    
    cleanupCropInterface();
    showScanActionButtons();
    canvas.toBlob(blob => { capturedImageBlob = blob; }, 'image/jpeg', 0.95);
    iziToast.success({
        message: 'Perspective corrigée. « Valider cette page » ou « Suivant » (1 page) ; « Ajouter une autre page » si multipages.',
        timeout: 4000,
    });
}

// Correction avec OpenCV - SANS OVERLAY
function applyPerspectiveWithOpenCV() {
    try {
        // Créer un canvas temporaire PROPRE
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = originalImageData.width;
        tempCanvas.height = originalImageData.height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(originalImageData, 0, 0);
        
        const src = cv.imread(tempCanvas);
        
        const srcCorners = cropCorners;
        const width1 = Math.sqrt(Math.pow(srcCorners[1].x - srcCorners[0].x, 2) + Math.pow(srcCorners[1].y - srcCorners[0].y, 2));
        const width2 = Math.sqrt(Math.pow(srcCorners[2].x - srcCorners[3].x, 2) + Math.pow(srcCorners[2].y - srcCorners[3].y, 2));
        const height1 = Math.sqrt(Math.pow(srcCorners[3].x - srcCorners[0].x, 2) + Math.pow(srcCorners[3].y - srcCorners[0].y, 2));
        const height2 = Math.sqrt(Math.pow(srcCorners[2].x - srcCorners[1].x, 2) + Math.pow(srcCorners[2].y - srcCorners[1].y, 2));
        
        const destWidth = Math.max(width1, width2);
        const destHeight = Math.max(height1, height2);
        
        const srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            srcCorners[0].x, srcCorners[0].y,
            srcCorners[1].x, srcCorners[1].y,
            srcCorners[2].x, srcCorners[2].y,
            srcCorners[3].x, srcCorners[3].y
        ]);
        
        const dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            0, 0, destWidth, 0, destWidth, destHeight, 0, destHeight
        ]);
        
        const M = cv.getPerspectiveTransform(srcTri, dstTri);
        const dst = new cv.Mat();
        cv.warpPerspective(src, dst, M, new cv.Size(destWidth, destHeight));
        
        const canvas = document.getElementById('canvas');
        canvas.width = destWidth;
        canvas.height = destHeight;
        cv.imshow(canvas, dst);
        
        src.delete(); dst.delete(); M.delete(); srcTri.delete(); dstTri.delete();
        
        originalImageData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
        cleanupCropInterface();
        showScanActionButtons();
        canvas.toBlob(blob => {
            capturedImageBlob = blob;
            iziToast.success({
                message: 'Correction terminée. « Valider cette page » ou « Suivant » pour un document d\'une page.',
                timeout: 4000,
            });
        }, 'image/jpeg', 0.95);
    } catch (e) {
        console.error('Erreur OpenCV perspective:', e);
        applyPerspectiveCorrectionFast();
    }
}

function enhanceImage() {
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;
    
    const contrast = 1.3;
    const factor = (259 * (contrast * 255 + 255)) / (255 * (259 - contrast * 255));
    
    for (let i = 0; i < data.length; i += 4) {
        data[i] = Math.min(255, Math.max(0, factor * (data[i] - 128) + 128));
        data[i + 1] = Math.min(255, Math.max(0, factor * (data[i + 1] - 128) + 128));
        data[i + 2] = Math.min(255, Math.max(0, factor * (data[i + 2] - 128) + 128));
    }
    
    context.putImageData(imageData, 0, 0);
    originalImageData = context.getImageData(0, 0, canvas.width, canvas.height);
    iziToast.success({ message: 'Image améliorée!' });
}

function addPageToScan() {
    if (!hasPendingCapture()) {
        iziToast.warning({ message: 'Capturez et corrigez d\'abord une page, puis validez-la.' });
        return;
    }
    commitCurrentCaptureToScan();
}

function cleanupCropInterface() {
    const canvas = document.getElementById('canvas');
    canvas.removeEventListener('mousedown', onCropMouseDown);
    canvas.removeEventListener('mousemove', onCropMouseMove);
    canvas.removeEventListener('mouseup', onCropMouseUp);
    canvas.removeEventListener('mouseleave', onCropMouseUp);
    canvas.removeEventListener('touchstart', onCropTouchStart);
    canvas.removeEventListener('touchmove', onCropTouchMove);
    canvas.removeEventListener('touchend', onCropTouchEnd);
    
    const resetBtn = document.getElementById('resetDetection');
    const applyBtn = document.getElementById('applyPerspective');
    if (resetBtn) resetBtn.remove();
    if (applyBtn) applyBtn.remove();
    
    canvas.toBlob(blob => {
        capturedImageBlob = blob;
        showScanActionButtons();
        iziToast.success({
            message: 'Image prête. Document 1 page : « Valider cette page » ou « Suivant ». Multipages : validez puis « Ajouter une autre page ».',
            timeout: 4000,
        });
    }, 'image/jpeg', 0.95);
}

function recaptureImage() {
    document.getElementById('previewContainer').style.display = 'none';
    syncScanStepNextButton();
    const canvas = document.getElementById('canvas');
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    capturedImageBlob = null;
    cropCorners = null;
    originalImageData = null;

    ['enhanceImage', 'addPage', 'validatePage'].forEach((id) => {
        const btn = document.getElementById(id);
        if (btn) btn.style.display = 'none';
    });
}

function pageNeedsOcr(page) {
    if (page._isNewCapture) {
        return true;
    }

    return !page.ocrText || !String(page.ocrText).trim();
}

async function processAllPagesOCR() {
    if (ocrPipelineInProgress) {
        return;
    }
    setScanStepProcessing(true);
    showEncPagesLoader('step2');

    const ocrProgress = document.getElementById('ocrProgress');
    const ocrProgressText = document.getElementById('ocrProgressText');
    const skipClientOcr = window.AUTHENTIQ_TEXTRACT_ENABLED
        && scannedPages.length > SKIP_CLIENT_OCR_PAGE_COUNT;

    if (skipClientOcr) {
        if (ocrProgress) {
            ocrProgress.style.display = 'block';
        }
        if (ocrProgressText) {
            ocrProgressText.textContent = 'Préparation…';
        }

        scannedPages.forEach((page) => {
            if (pageNeedsOcr(page)) {
                page.ocrText = page.ocrText || '';
                page._isNewCapture = false;
            }
        });
        syncOcrTextFromPages();

        if (ocrProgress) {
            ocrProgress.style.display = 'none';
        }

        iziToast.info({
            message: `${scannedPages.length} pages : OCR navigateur ignoré, Textract prendra le relais après sauvegarde.`,
            timeout: 5000,
        });

        try {
            await saveImageAndOCR();
        } catch (err) {
            console.error('saveImageAndOCR:', err);
            iziToast.error({ message: err.message || 'Erreur lors de la sauvegarde.' });
            hideEncPagesLoader('step2');
            setScanStepProcessing(false);
        }

        return;
    }

    try {
        await loadTesseractOnce();
    } catch (err) {
        console.error('Tesseract:', err);
        hideEncPagesLoader('step2');
        setScanStepProcessing(false);
        iziToast.error({ message: 'Impossible de charger le moteur OCR. Vérifiez votre connexion.' });
        return;
    }

    if (ocrProgress) {
        ocrProgress.style.display = 'block';
    }
    if (ocrProgressText) {
        ocrProgressText.textContent = '0%';
    }

    let processedCount = 0;
    const pagesNeedingOcr = scannedPages.filter(pageNeedsOcr).length;
    const ocrDenom = Math.max(pagesNeedingOcr, 1);

    for (let index = 0; index < scannedPages.length; index++) {
        const page = scannedPages[index];
        if (!pageNeedsOcr(page)) {
            continue;
        }

        try {
            const { data: { text } } = await Tesseract.recognize(page.image, 'fra+eng', {
                logger: (info) => {
                    if (info.status === 'recognizing text' && ocrProgressText) {
                        const pageProgress = (processedCount + info.progress) / ocrDenom;
                        ocrProgressText.textContent = `${Math.round(pageProgress * 100)}%`;
                    }
                },
            });
            scannedPages[index].ocrText = text;
            scannedPages[index]._isNewCapture = false;
        } catch (err) {
            console.error('Erreur OCR page', index + 1, err);
            scannedPages[index].ocrText = '';
            scannedPages[index]._isNewCapture = false;
        }

        processedCount++;
    }

    syncOcrTextFromPages();

    if (ocrProgress) {
        ocrProgress.style.display = 'none';
    }

    const msg = pagesNeedingOcr > 0
        ? `OCR terminé pour ${pagesNeedingOcr} page(s).`
        : 'Texte OCR existant conservé.';
    iziToast.success({ message: msg });

    try {
        await saveImageAndOCR();
    } catch (err) {
        console.error('saveImageAndOCR:', err);
        iziToast.error({ message: err.message || 'Erreur lors de la sauvegarde.' });
        hideEncPagesLoader('step2');
        setScanStepProcessing(false);
    }
}

async function blobFromPageImage(page) {
    if (page.blob instanceof Blob && page.blob.size > 0) {
        return page.blob;
    }

    const src = page.image;
    if (!src) {
        return null;
    }

    if (src.startsWith('data:')) {
        const res = await fetch(src);

        return res.ok ? res.blob() : null;
    }

    if (page.id_page && encodageId) {
        const url = `${ENCODAGE_API}/${encodageId}/pages/${page.id_page}/file`;
        const res = await fetch(url, { headers: encodeApiHeaders(), credentials: 'same-origin' });

        return res.ok ? res.blob() : null;
    }

    try {
        const res = await fetch(src, { credentials: 'same-origin' });

        return res.ok ? res.blob() : null;
    } catch (_) {
        return null;
    }
}

function pageNeedsFileUpload(page) {
    if (!page.id_page) {
        return true;
    }
    if (page._isNewCapture) {
        return true;
    }

    return page.blob instanceof Blob && page.blob.size > 0;
}

async function ensurePageBlobsForUpload() {
    for (const page of scannedPages) {
        if (!pageNeedsFileUpload(page)) {
            continue;
        }
        if (page.blob instanceof Blob && page.blob.size > 0) {
            continue;
        }
        const blob = await blobFromPageImage(page);
        if (blob) {
            page.blob = blob;
        }
    }
}

async function saveImageAndOCR() {
    showEncPagesLoader('step2');

    try {
        await ensurePageBlobsForUpload();
    } catch (err) {
        console.error('ensurePageBlobsForUpload:', err);
        hideEncPagesLoader('step2');
        setScanStepProcessing(false);
        throw new Error('Impossible de préparer les fichiers des pages pour l\'envoi.');
    }

    const missing = scannedPages.filter((p) => {
        if (!pageNeedsFileUpload(p)) {
            return false;
        }

        return !(p.blob instanceof Blob) || p.blob.size === 0;
    });
    if (missing.length > 0) {
        hideEncPagesLoader('step2');
        setScanStepProcessing(false);
        throw new Error(`${missing.length} page(s) sans fichier image. Repassez par l'étape scan (Suivant).`);
    }

    const total = scannedPages.length;
    const batchSize = OCR_SAVE_BATCH_SIZE;
    const ocrProgressText = document.getElementById('ocrProgressText');
    let lastData = null;

    syncOcrTextFromPages();

    for (let start = 0; start < total; start += batchSize) {
        const end = Math.min(start + batchSize, total);
        const completePages = end >= total;
        const batchNum = Math.floor(start / batchSize) + 1;
        const batchTotal = Math.ceil(total / batchSize);

        if (ocrProgressText) {
            ocrProgressText.textContent = `Sauvegarde ${batchNum}/${batchTotal}…`;
        }

        lastData = await postSaveOcrBatch(start, end, completePages);

        encodageId = lastData.encodageId;
        document.getElementById('encodageId').value = encodageId;

        if (Array.isArray(lastData.pages)) {
            mergeServerPagesIntoScanned(lastData.pages);
        }
    }

    setEncodageStatus('incomplete');
    clearScanPagesDirty();
    iziToast.success({
        message: lastData?.message || `${total} page(s) sauvegardée(s).`,
    });

    if (lastData?.textract_queued && window.AUTHENTIQ_TEXTRACT_ENABLED) {
        pollTextractOcr(encodageId);
    }

    hideEncPagesLoader('step2');
    setScanStepProcessing(false);
}

function loadDocumentTypes() {
    fetch(`${ENCODAGE_API}/doc-types`, { headers: encodeApiHeaders() })
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById('docType');
        const docs = Array.isArray(data) ? data : (data.data || []);
        docs.forEach(doc => {
            const option = document.createElement('option');
            option.value = doc.id_doc;
            option.textContent = `${doc.nom_doc} (${doc.type_doc})`;
            option.dataset.montant = doc.montant;
            option.dataset.typedoc = doc.type_doc;
            option.dataset.duree = doc.duree;
            option.dataset.ownership = doc.ownership || 'single';
            select.appendChild(option);
        });
        tryApplyPendingResume();
    });
}

function loadDocumentInfo() {
    const select = document.getElementById('docType');
    if (!select.value) {
        currentDocDuree = 0;
        document.getElementById('docMontant').value = '';
        setEncodageDate('docDateEmission', '');
        setEncodageDate('docDateExpiration', '');
        updateDateFieldsState();
        return;
    }

    const option = select.options[select.selectedIndex];
    if (option.dataset.montant) {
        document.getElementById('docMontant').value = option.dataset.montant;
    }
    currentDocDuree = parseInt(option.dataset.duree || '0', 10) || 0;
    updateDateFieldsState();
    calculateExpirationDate();
    updateAssociatedClientsHint();

    const ownership = option.dataset.ownership || 'single';
    if (ownership === 'single' && associatedClients.length > 1) {
        iziToast.warning({
            message: 'Ce type de document n\'accepte qu\'un client : seul le client principal est conservé.',
            timeout: 6000,
        });
        associatedClients = [associatedClients[0]];
        associatedClients[0].is_primary = true;
        clientId = associatedClients[0].id_client;
        document.getElementById('clientId').value = String(clientId);
        renderAssociatedClientsChips();
    }
}

function isDocTypeSelected() {
    const select = document.getElementById('docType');
    return Boolean(select?.value);
}

function setDateFieldHint(elementId, text) {
    const hint = document.getElementById(elementId === 'docDateEmission' ? 'docDateEmissionHint' : 'docDateExpirationHint');
    if (hint) hint.textContent = text;
}

function setEncodageDateFieldState(elementId, { enabled, placeholder, hint }) {
    const el = document.getElementById(elementId);
    const fp = getEncodageFlatpickr(elementId);
    if (!el) return;

    const visible = fp?.altInput || el;

    if (fp) {
        fp.set('clickOpens', enabled);
        fp.set('allowInput', enabled);
        fp.input.disabled = !enabled;
        if (fp.altInput) {
            fp.altInput.disabled = !enabled;
            if (enabled) {
                fp.altInput.removeAttribute('disabled');
            } else {
                fp.altInput.setAttribute('disabled', 'disabled');
            }
        }
        if (!enabled) {
            fp.close();
        }
    } else {
        el.disabled = !enabled;
    }

    if (placeholder !== undefined) visible.placeholder = placeholder;

    if (hint !== undefined) setDateFieldHint(elementId, hint);
}

function updateDateFieldsState() {
    if (!isDocTypeSelected()) {
        setEncodageDateFieldState('docDateEmission', {
            enabled: false,
            placeholder: 'Sélectionnez un type de document',
            hint: 'Choisissez d\'abord le type de document.',
        });
        setEncodageDateFieldState('docDateExpiration', {
            enabled: false,
            placeholder: '—',
            hint: 'Calculée automatiquement selon la durée du document.',
        });
        return;
    }

    setEncodageDateFieldState('docDateEmission', {
        enabled: true,
        placeholder: 'jj/mm/aaaa',
        hint: 'Date de délivrance du document.',
    });

    if (currentDocDuree === 0) {
        setEncodageDateFieldState('docDateExpiration', {
            enabled: false,
            placeholder: 'Durée illimitée',
            hint: 'Ce type de document n\'a pas de date d\'expiration.',
        });
    } else {
        const monthsLabel = currentDocDuree === 1 ? '1 mois' : `${currentDocDuree} mois`;
        setEncodageDateFieldState('docDateExpiration', {
            enabled: false,
            placeholder: 'Calcul automatique…',
            hint: `Expiration = date d'émission + ${monthsLabel} (non modifiable).`,
        });
    }
}

function getEncodageFlatpickr(elementId) {
    const el = document.getElementById(elementId);
    return el?._flatpickr || null;
}

function setEncodageDate(elementId, isoDate) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const fp = getEncodageFlatpickr(elementId);
    if (!isoDate) {
        if (fp) fp.clear();
        else el.value = '';
        return;
    }
    if (fp) fp.setDate(isoDate, false);
    else el.value = isoDate;
}

function initEncodageDatePickers() {
    if (typeof flatpickr === 'undefined') return;

    const emissionEl = document.getElementById('docDateEmission');
    const expirationEl = document.getElementById('docDateExpiration');
    if (!emissionEl || !expirationEl) return;

    if (emissionEl._flatpickr) emissionEl._flatpickr.destroy();
    if (expirationEl._flatpickr) expirationEl._flatpickr.destroy();

    const locale = (typeof flatpickr !== 'undefined' && flatpickr.l10ns?.fr) ? flatpickr.l10ns.fr : undefined;
    const commonOpts = {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        locale,
    };

    flatpickr(emissionEl, {
        ...commonOpts,
        onChange: calculateExpirationDate,
    });

    flatpickr(expirationEl, {
        ...commonOpts,
        clickOpens: false,
    });

    updateDateFieldsState();
}

// Calcul automatique de la date d'expiration (selon currentDocDuree du type sélectionné)
function calculateExpirationDate() {
    if (!isDocTypeSelected()) {
        updateDateFieldsState();
        return;
    }

    const dateEmission = document.getElementById('docDateEmission').value;

    if (!dateEmission) {
        setEncodageDate('docDateExpiration', '');
        updateDateFieldsState();
        return;
    }

    if (currentDocDuree === 0) {
        setEncodageDate('docDateExpiration', '');
    } else {
        const emission = new Date(dateEmission + 'T12:00:00');
        emission.setMonth(emission.getMonth() + currentDocDuree);

        const year = emission.getFullYear();
        const month = String(emission.getMonth() + 1).padStart(2, '0');
        const day = String(emission.getDate()).padStart(2, '0');
        setEncodageDate('docDateExpiration', `${year}-${month}-${day}`);
    }

    updateDateFieldsState();
}

function toggleClientType() {
    const type = document.getElementById('clientType').value;
    document.getElementById('newClientDiv').style.display = type === 'new' ? 'block' : 'none';
    document.getElementById('existingClientDiv').style.display = type === 'existing' ? 'block' : 'none';
    syncStep3Cameras();
}

function syncStep3Cameras() {
    const type = document.getElementById('clientType')?.value;
    if (currentStep !== ENCODAGE_CLIENT_STEP) {
        stopFaceCamera();
        stopNewClientCamera();
        return;
    }
    if (type === 'existing') {
        stopNewClientCamera();
        syncFaceCameraForStep3();
    } else {
        stopFaceCamera();
        startNewClientCamera();
    }
}

let clientSearchTimeout = null;

function setupClientSearch() {
    const searchInput = document.getElementById('searchClient');
    const select = document.getElementById('clientSelect');
    if (!searchInput || !select) return;

    searchInput.addEventListener('input', function () {
        clearTimeout(clientSearchTimeout);
        const query = this.value.trim();
        if (query.length < 2) {
            renderClientSearchResults([], 'Tapez au moins 2 caractères…');
            return;
        }
        renderClientSearchResults([], 'Recherche…');
        clientSearchTimeout = setTimeout(() => searchClients(query), 350);
    });
}

function renderClientSearchResults(clients, placeholder) {
    const select = document.getElementById('clientSelect');
    const list = document.getElementById('clientSearchResults');
    if (!select) return;

    select.innerHTML = '';
    if (list) list.innerHTML = '';

    if (placeholder) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.disabled = true;
        opt.selected = true;
        opt.textContent = placeholder;
        select.appendChild(opt);
        if (list) {
            list.innerHTML = `<li class="client-search-results__hint text-muted">${placeholder}</li>`;
        }
        return;
    }

    if (clients.length === 0) {
        renderClientSearchResults([], 'Aucun client trouvé');
        return;
    }

    clients.forEach(client => {
        const inactive = client.is_active === false || client.is_active === 0;
        const label = `${client.nom_complet} — ${client.tel || '—'}${inactive ? ' (inactif)' : ''}`;

        const option = document.createElement('option');
        option.value = client.id_client;
        option.textContent = label;
        select.appendChild(option);

        if (list) {
            const li = document.createElement('li');
            li.className = 'client-search-results__item';
            li.dataset.clientId = String(client.id_client);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'client-search-results__name';
            nameSpan.textContent = client.nom_complet;

            const metaSpan = document.createElement('span');
            metaSpan.className = 'client-search-results__meta';
            const metaParts = [client.tel, client.email].filter(Boolean);
            metaSpan.textContent = metaParts.join(' · ') + (inactive ? ' (inactif)' : '');

            li.append(nameSpan, metaSpan);
            li.addEventListener('click', () => {
                select.value = String(client.id_client);
                list.querySelectorAll('.client-search-results__item').forEach(el => el.classList.remove('is-selected'));
                li.classList.add('is-selected');
                addAssociatedClient(client);
            });
            list.appendChild(li);
        }
    });
}

function searchClients(query) {
    const q = (query ?? document.getElementById('searchClient')?.value ?? '').trim();
    if (q.length < 2) return;

    fetch(`${ENCODAGE_API}/clients/search?q=${encodeURIComponent(q)}`, {
        headers: encodeApiHeaders(),
        credentials: 'same-origin',
    })
        .then(async (response) => {
            const data = await response.json().catch(() => null);
            if (!response.ok) {
                const msg = data?.message || 'Erreur lors de la recherche.';
                iziToast.error({ message: msg });
                renderClientSearchResults([], 'Erreur de recherche');
                return;
            }
            const clients = Array.isArray(data) ? data : (data?.data || []);
            renderClientSearchResults(clients, null);
        })
        .catch(() => {
            iziToast.error({ message: 'Impossible de contacter le serveur.' });
            renderClientSearchResults([], 'Erreur de recherche');
        });
}

function setupEncodageOtp() {
    const otpModalEl = document.getElementById('otpModal');
    if (!otpModalEl) return;

    const otpModal = new bootstrap.Modal(otpModalEl, { backdrop: 'static', keyboard: false });
    const otpInputs = document.querySelectorAll('#otpModal .otp-input');
    const otpHiddenInput = document.getElementById('otpInput');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const cancelOtpBtn = document.getElementById('cancelOtpBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');

    const clearOtpInputs = () => {
        otpInputs.forEach((input) => { input.value = ''; });
        if (otpHiddenInput) otpHiddenInput.value = '';
        if (otpInputs[0]) otpInputs[0].focus();
    };

    const updateHiddenOtp = () => {
        if (otpHiddenInput) {
            otpHiddenInput.value = Array.from(otpInputs).map((i) => i.value).join('');
        }
    };

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const value = e.target.value.replace(/\D/g, '');
            e.target.value = value.slice(-1);
            if (value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
            updateHiddenOtp();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) otpInputs[index - 1].focus();
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').substring(0, 6);
            pasteData.split('').forEach((char, i) => {
                if (otpInputs[i]) otpInputs[i].value = char;
            });
            const lastIndex = Math.min(pasteData.length, otpInputs.length - 1);
            otpInputs[lastIndex].focus();
            updateHiddenOtp();
        });
    });

    window.showEncodageOtpModal = () => {
        clearOtpInputs();
        otpModal.show();
    };

    cancelOtpBtn?.addEventListener('click', () => {
        otpModal.hide();
        clearOtpInputs();
    });

    resendOtpBtn?.addEventListener('click', () => {
        if (!otpClientIdForVerification) return;
        resendOtpBtn.disabled = true;
        fetch('/api/clients/resend-otp', {
            method: 'POST',
            headers: encodeApiHeaders(true),
            body: JSON.stringify({ id_client: otpClientIdForVerification }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.status === 'success') {
                    iziToast.success({ message: data.message });
                    clearOtpInputs();
                } else {
                    iziToast.error({ message: data.message });
                }
            })
            .catch(() => iziToast.error({ message: 'Impossible de renvoyer le code OTP.' }))
            .finally(() => { resendOtpBtn.disabled = false; });
    });

    verifyOtpBtn?.addEventListener('click', () => {
        const otpCode = (otpHiddenInput?.value || '').trim();
        if (!otpCode || otpCode.length !== 6 || !otpClientIdForVerification) {
            iziToast.warning({ message: 'Veuillez saisir le code OTP à 6 chiffres.' });
            return;
        }

        verifyOtpBtn.disabled = true;
        const defaultLabel = verifyOtpBtn.innerHTML;
        verifyOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Vérification…';

        fetch('/api/clients/verify-otp', {
            method: 'POST',
            headers: encodeApiHeaders(true),
            body: JSON.stringify({ id_client: otpClientIdForVerification, code_otp: otpCode }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.status === 'success') {
                    clientAwaitingOtp = false;
                    clientId = otpClientIdForVerification;
                    document.getElementById('clientId').value = clientId;
                    otpModal.hide();
                    clearOtpInputs();
                    iziToast.success({ message: data.message });
                    if (pendingOtpGoNext) {
                        pendingOtpGoNext = false;
                        if (!validateClientStepComplete(true, { clientCount: associatedClients.length })) {
                            return;
                        }
                        loadRecapitulatif();
                        goToStep(5);
                    }
                } else {
                    iziToast.error({ message: data.message });
                    clearOtpInputs();
                }
            })
            .catch(() => iziToast.error({ message: 'Erreur lors de la vérification OTP.' }))
            .finally(() => {
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.innerHTML = defaultLabel;
            });
    });
}

function buildNewClientCheckFormData() {
    const fd = new FormData();
    fd.append('tel', document.getElementById('clientTel')?.value || '');
    fd.append('email', document.getElementById('clientEmail')?.value || '');
    fd.append('clientTypePiece', document.getElementById('clientTypePiece')?.value || '');
    fd.append('clientNumeroPiece', document.getElementById('clientNumeroPiece')?.value || '');
    if (encNewClientCroppedBlob) {
        fd.append('photo', encNewClientCroppedBlob, 'client-photo.jpg');
    }
    return fd;
}

function checkClientDuplicatesFormData(formData) {
    return fetch('/api/clients/check-duplicates', {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    }).then((r) => r.json());
}

function handleClientDuplicateResponse(data) {
    if (!data?.duplicate && !(data?.conflict && data?.client)) {
        return false;
    }
    if (data.duplicate || (data.conflict && data.client)) {
        window.AuthentiqClientDuplicate?.show(
            {
                allowed: false,
                conflict: data.conflict,
                client: data.client,
                message: data.message,
            },
            {
                context: 'encodage',
                onUseExisting: (client) => {
                    applyRecognizedClient(client);
                    clientAwaitingOtp = false;
                    pendingOtpGoNext = false;
                    iziToast.info({
                        message: 'Client existant sélectionné. Enregistrez à nouveau pour continuer.',
                        timeout: 4000,
                    });
                },
            },
        );
        return true;
    }
    return false;
}

function performSaveClientInfo(formData, andGoNext = false) {
    formData.append('completeStep', andGoNext ? '1' : '0');

    fetch(`${ENCODAGE_API}/save-client`, {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    })
        .then((response) => response.json().then((data) => ({ data, status: response.status })))
        .then(({ data, status }) => {
            if (handleClientDuplicateResponse(data)) {
                return;
            }
            if (data.status === 'success') {
                if (data.associated_clients?.length) {
                    setAssociatedClientsFromServer(data.associated_clients);
                } else if (data.clientId) {
                    clientId = data.clientId;
                    document.getElementById('clientId').value = clientId;
                }

                if (data.requires_otp) {
                    clientAwaitingOtp = true;
                    otpClientIdForVerification = data.clientId;
                    pendingOtpGoNext = andGoNext;
                    iziToast.info({ message: data.message, timeout: 5000 });
                    if (typeof window.showEncodageOtpModal === 'function') {
                        window.showEncodageOtpModal();
                    }
                    return;
                }

                clientAwaitingOtp = false;
                pendingOtpGoNext = false;
                iziToast.success({ message: data.message || 'Informations client sauvegardées.' });
                if (andGoNext) {
                    const savedCount = Array.isArray(data.associated_clients)
                        ? data.associated_clients.length
                        : (Array.isArray(data.clientIds) ? data.clientIds.length : associatedClients.length);
                    if (!validateClientStepComplete(true, { clientCount: savedCount })) {
                        return;
                    }
                    loadRecapitulatif();
                    goToStep(5);
                } else if (currentStep === 5 && encodageId) {
                    loadRecapitulatif();
                }
            } else {
                iziToast.error({ message: data.message || 'Erreur lors de l\'enregistrement.' });
            }
        })
        .catch(() => iziToast.error({ message: 'Erreur lors de l\'enregistrement du client.' }));
}

function saveClientInfo(andGoNext = false) {
    if (!encodageId) {
        iziToast.warning({ message: 'Importez d\'abord les pages PDF (étape 1).' });
        return;
    }

    if (!document.getElementById('docType')?.value) {
        iziToast.warning({ message: 'Sélectionnez d\'abord le type de document (étape 3).' });
        goToStep(3);
        return;
    }

    if (clientAwaitingOtp && andGoNext) {
        iziToast.warning({ message: 'Validez le code OTP pour activer le client avant de continuer.' });
        if (typeof window.showEncodageOtpModal === 'function') window.showEncodageOtpModal();
        return;
    }

    const formData = new FormData();
    formData.append('encodageId', encodageId);

    const clientType = document.getElementById('clientType').value;
    if (clientType === 'existing') {
        const clientSelect = document.getElementById('clientSelect');
        if (!associatedClients.length && clientSelect.value) {
            addAssociatedClient({
                id_client: parseInt(clientSelect.value, 10),
                nom_complet: clientSelect.options[clientSelect.selectedIndex]?.textContent || '',
            });
        }
        if (!associatedClients.length) {
            iziToast.warning({ message: 'Ajoutez au moins un client à la liste.' });
            return;
        }
        if (!validateClientStepComplete(andGoNext, { clientType: 'existing' })) {
            return;
        }
        formData.append('clientIds', JSON.stringify(associatedClients.map((c) => c.id_client)));
        performSaveClientInfo(formData, andGoNext);
        return;
    }

    formData.append('clientNom', document.getElementById('clientNom').value);
    formData.append('clientTel', document.getElementById('clientTel').value);
    formData.append('clientEmail', document.getElementById('clientEmail').value);
    formData.append('clientTypePiece', document.getElementById('clientTypePiece').value);
    formData.append('clientNumeroPiece', document.getElementById('clientNumeroPiece').value);

    const nom = document.getElementById('clientNom').value.trim();
    const tel = document.getElementById('clientTel').value.trim();
    if (!nom || !tel) {
        iziToast.warning({ message: 'Nom et téléphone requis.' });
        return;
    }
    if (!encNewClientCroppedBlob) {
        iziToast.warning({ message: 'La photo du client est obligatoire. Utilisez la caméra pour capturer le visage.' });
        return;
    }
    if (!validateClientStepComplete(andGoNext, { clientType: 'new' })) {
        return;
    }
    formData.append('clientPhoto', encNewClientCroppedBlob, 'client-photo.jpg');
    if (associatedClients.length > 0) {
        formData.append('clientIds', JSON.stringify(associatedClients.map((c) => c.id_client)));
    }

    const checkFd = buildNewClientCheckFormData();
    checkClientDuplicatesFormData(checkFd)
        .then((check) => {
            if (!check.allowed) {
                handleClientDuplicateResponse({
                    duplicate: true,
                    conflict: check.conflict,
                    client: check.client,
                    message: check.message || check.conflict?.message,
                });
                return;
            }
            performSaveClientInfo(formData, andGoNext);
        })
        .catch(() => iziToast.error({ message: 'Impossible de vérifier les doublons client.' }));
}

function saveDocumentInfo(andGoNext = false) {
    if (!encodageId) {
        iziToast.warning({ message: 'Encodage non initialisé.' });
        return;
    }
    if (!document.getElementById('docType')?.value) {
        iziToast.warning({ message: 'Sélectionnez un type de document.' });
        return;
    }
    const formData = new FormData();
    formData.append('encodageId', encodageId);
    formData.append('docType', document.getElementById('docType').value);
    formData.append('docMontant', document.getElementById('docMontant').value);
    formData.append('docDateEmission', document.getElementById('docDateEmission').value);
    formData.append('docDateExpiration', document.getElementById('docDateExpiration').value);
    
    fetch(`${ENCODAGE_API}/save-document`, {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    })
    .then((response) => response.json().then((data) => ({ data, status: response.status })))
    .then(({ data, status }) => {
        if (status === 423) {
            iziToast.error({ message: data.message || 'Modification impossible : encodage finalisé.' });
            setEncodageStatus('complete');

            return;
        }
        if (data.status === 'success') {
            iziToast.success({
                message: andGoNext
                    ? 'Document enregistré.'
                    : 'Document enregistré. Vous pouvez encore modifier avant la finalisation.',
            });
            if (andGoNext) {
                goToStep(4);
            } else if (currentStep === 5 && encodageId) {
                loadRecapitulatif();
            }
        } else {
            iziToast.error({ message: data.message });
        }
    });
}

let textractPollTimer = null;

function pollTextractOcr(id) {
    if (textractPollTimer) clearInterval(textractPollTimer);

    const ocrArea = document.getElementById('ocrText');
    iziToast.info({ message: 'OCR AWS Textract en cours…', timeout: 3000 });

    const check = () => {
        fetch(`${ENCODAGE_API}/${id}/ocr-status`, { headers: encodeApiHeaders() })
            .then((r) => r.json())
            .then((data) => {
                if (data.status !== 'success') return;

                if (data.combined_ocr && ocrArea) {
                    ocrArea.value = data.combined_ocr;
                }

                if (data.all_done) {
                    clearInterval(textractPollTimer);
                    textractPollTimer = null;
                    iziToast.success({ message: 'OCR AWS Textract terminé.' });
                }
            })
            .catch(() => {});
    };

    check();
    textractPollTimer = setInterval(check, 2500);
}

function shouldUseFaceCamera() {
    return window.AUTHENTIQ_REKOGNITION_ENABLED
        && currentStep === ENCODAGE_CLIENT_STEP
        && document.getElementById('clientType')?.value === 'existing'
        && document.getElementById('existingClientDiv')?.style.display !== 'none';
}

function syncFaceCameraForStep3() {
    if (shouldUseFaceCamera()) {
        startFaceCamera();
    } else {
        stopFaceCamera();
    }
}

function startFaceCamera() {
    if (!window.AUTHENTIQ_REKOGNITION_ENABLED) return;

    const video = document.getElementById('faceVideo');
    if (!video || faceVideoStream) return;

    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'user',
            width: { ideal: 1280 },
            height: { ideal: 720 },
        },
        audio: false,
    })
        .then((stream) => {
            faceVideoStream = stream;
            video.srcObject = stream;
            video.classList.remove('d-none');
            const preview = document.getElementById('facePreview');
            if (preview) preview.classList.add('d-none');
        })
        .catch((err) => {
            console.error('Caméra faciale:', err);
            iziToast.error({ message: 'Impossible d\'accéder à la caméra (autorisez l\'accès).' });
        });
}

function stopFaceCamera() {
    if (faceVideoStream) {
        faceVideoStream.getTracks().forEach((t) => t.stop());
        faceVideoStream = null;
    }
    const video = document.getElementById('faceVideo');
    if (video) {
        video.srcObject = null;
    }
    faceCaptureBlob = null;
}

function captureFaceFromCamera() {
    const video = document.getElementById('faceVideo');
    const canvas = document.getElementById('faceCanvas');
    const preview = document.getElementById('facePreview');
    if (!video || !canvas) return Promise.resolve(null);

    const w = video.videoWidth;
    const h = video.videoHeight;
    if (!w || !h) {
        iziToast.warning({ message: 'La caméra n\'est pas prête. Patientez un instant.' });
        return Promise.resolve(null);
    }

    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);

    return new Promise((resolve) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                iziToast.error({ message: 'Échec de la capture.' });
                resolve(null);
                return;
            }
            faceCaptureBlob = blob;
            if (preview) {
                preview.src = URL.createObjectURL(blob);
                preview.classList.remove('d-none');
                video.classList.add('d-none');
            }
            document.getElementById('btnRetakeFace')?.classList.remove('d-none');
            iziToast.success({ message: 'Photo capturée. Cliquez sur Identifier.' });
            resolve(blob);
        }, 'image/jpeg', 0.92);
    });
}

function retakeFacePhoto() {
    faceCaptureBlob = null;
    clearRecognizedClientCard();
    const preview = document.getElementById('facePreview');
    const video = document.getElementById('faceVideo');
    if (preview?.src) {
        URL.revokeObjectURL(preview.src);
        preview.src = '';
        preview.classList.add('d-none');
    }
    if (video) video.classList.remove('d-none');
    document.getElementById('btnRetakeFace')?.classList.add('d-none');
    if (!faceVideoStream) startFaceCamera();
}

function applyRecognizedClient(c) {
    document.getElementById('clientType').value = 'existing';
    document.getElementById('existingClientDiv').style.display = 'block';
    document.getElementById('newClientDiv').style.display = 'none';

    const select = document.getElementById('clientSelect');
    let opt = select.querySelector(`option[value="${c.id_client}"]`);
    if (!opt) {
        opt = document.createElement('option');
        opt.value = c.id_client;
        opt.textContent = `${c.nom_complet} — ${c.tel || ''}`;
        select.appendChild(opt);
    }
    select.value = String(c.id_client);
    addAssociatedClient(c);
}

function setFaceRecognitionLoading(loading) {
    const rowLoader = document.getElementById('faceRecognitionRowLoader');
    const cardEmpty = document.getElementById('recognizedClientEmpty');
    const cardContent = document.getElementById('recognizedClientContent');
    const btnSearch = document.getElementById('btnSearchClientByPhoto');
    const btnCapture = document.getElementById('btnCaptureFace');
    const btnRetake = document.getElementById('btnRetakeFace');

    if (rowLoader) {
        rowLoader.classList.toggle('d-none', !loading);
        rowLoader.classList.toggle('is-active', loading);
        rowLoader.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    if (loading) {
        if (cardEmpty) cardEmpty.style.display = 'none';
        if (cardContent) {
            cardContent.hidden = true;
            cardContent.innerHTML = '';
        }
    }

    [btnSearch, btnCapture, btnRetake].forEach((btn) => {
        if (btn) btn.disabled = loading;
    });
}

function clearRecognizedClientCard() {
    const card = document.getElementById('recognizedClientCard');
    const empty = document.getElementById('recognizedClientEmpty');
    const content = document.getElementById('recognizedClientContent');

    card?.classList.remove('is-success', 'is-error');
    if (content) {
        content.hidden = true;
        content.innerHTML = '';
    }
    if (empty) {
        empty.style.display = '';
        empty.innerHTML = `
            <div class="enc-face-result__empty-visual" aria-hidden="true">
                <iconify-icon icon="solar:user-circle-bold-duotone"></iconify-icon>
            </div>
            <p class="enc-face-result__empty-title">Aucun résultat</p>
            <p class="enc-face-result__empty-hint">Les informations s'afficheront après identification</p>
        `;
    }
}

function escapeAttr(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

function renderRecognizedClientCard(client, similarity, message) {
    const card = document.getElementById('recognizedClientCard');
    const empty = document.getElementById('recognizedClientEmpty');
    const content = document.getElementById('recognizedClientContent');

    if (!content) {
        console.error('recognizedClientContent introuvable — rechargez la page (Ctrl+F5).');
        return;
    }

    card?.classList.remove('is-error');
    card?.classList.add('is-success');

    const statusTag = client.is_active
        ? '<span class="enc-face-client__tag enc-face-client__tag--active">Actif</span>'
        : '<span class="enc-face-client__tag enc-face-client__tag--pending">OTP requis</span>';

    const pieceLabel = client.type_piece_identite || '—';
    const pieceNum = client.type_piece_identite === 'CNI'
        ? (client.numero_national || '—')
        : client.type_piece_identite === 'Passeport'
            ? (client.numero_passeport || '—')
            : (client.numero_national || client.numero_passeport || '—');

    const location = [client.nom_ville, client.nom_province].filter(Boolean).join(', ');
    const pct = similarity != null ? Math.round(similarity) : null;
    const photoUrl = client.photo_url || 'assets/images/user.jpg';
    const matchTag = pct != null
        ? `<span class="enc-face-client__tag enc-face-client__tag--match">${pct} %</span>`
        : '';

    const chips = [
        ['Tél.', client.tel],
        ['Email', client.email],
        ['Pièce', pieceLabel !== '—' ? `${pieceLabel} — ${pieceNum}` : pieceNum],
    ];
    if (location) chips.push(['Lieu', location]);
    if (client.adresse) chips.push(['Adr.', client.adresse]);

    const chipsHtml = chips
        .map(([label, val]) => `
            <div class="enc-face-chip">
                <span class="enc-face-chip__label">${escapeHtml(label)}</span>
                <span class="enc-face-chip__value">${escapeHtml(val || '—')}</span>
            </div>`)
        .join('');

    content.innerHTML = `
        <div class="enc-face-client">
            <img src="${escapeAttr(photoUrl)}" alt="" class="enc-face-client__photo">
            <div class="enc-face-client__meta">
                <p class="enc-face-client__name">${escapeHtml(client.nom_complet || '—')}</p>
                <div class="enc-face-client__tags">
                    ${statusTag}
                    ${matchTag}
                </div>
            </div>
        </div>
        <div class="enc-face-client__chips">${chipsHtml}</div>
    `;

    if (empty) empty.style.display = 'none';
    content.hidden = false;
    content.removeAttribute('hidden');

    card?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    if (message) {
        iziToast.success({ message, timeout: 3000 });
    }
}

function showRecognizedClientError(message) {
    const card = document.getElementById('recognizedClientCard');
    const empty = document.getElementById('recognizedClientEmpty');
    const content = document.getElementById('recognizedClientContent');

    if (content) {
        content.hidden = true;
        content.innerHTML = '';
    }
    card?.classList.remove('is-success');
    card?.classList.add('is-error');

    if (empty) {
        empty.style.display = '';
        empty.innerHTML = `
            <div class="enc-face-result__empty-visual" aria-hidden="true" style="color:rgba(255,193,7,0.7);background:rgba(255,193,7,0.1);">
                <iconify-icon icon="solar:danger-circle-bold-duotone"></iconify-icon>
            </div>
            <p class="enc-face-result__empty-title" style="color:#fcd34d;">Aucun client identifié</p>
            <p class="enc-face-result__empty-hint">${escapeHtml(message || 'Aucun client reconnu.')}</p>
        `;
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function searchClientByFaceBlob(blob) {
    const btn = document.getElementById('btnSearchClientByPhoto');
    const fd = new FormData();
    fd.append('photo', blob, 'face-capture.jpg');

    setFaceRecognitionLoading(true);
    if (btn) btn.disabled = true;

    return fetch('/api/clients/search-by-photo', {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: fd,
    })
        .then((r) => r.json())
        .then((data) => {
            setFaceRecognitionLoading(false);
            if (btn) btn.disabled = false;

            if (data.status !== 'success' || !data.found || !data.client) {
                showRecognizedClientError(data.message || 'Aucun client reconnu.');
                iziToast.warning({ message: data.message || 'Aucun client reconnu.', timeout: 4000 });
                return;
            }
            applyRecognizedClient(data.client);
            const pct = data.similarity != null ? Math.round(data.similarity) : null;
            const msg = pct != null
                ? `Client identifié (${pct} % de correspondance)`
                : 'Client identifié';
            renderRecognizedClientCard(data.client, data.similarity, msg);
        })
        .catch(() => {
            setFaceRecognitionLoading(false);
            if (btn) btn.disabled = false;
            showRecognizedClientError('Erreur lors de la reconnaissance faciale.');
            iziToast.error({ message: 'Erreur reconnaissance faciale.' });
        });
}

function stopNewClientCamera() {
    if (newClientCameraStream) {
        newClientCameraStream.getTracks().forEach((t) => t.stop());
        newClientCameraStream = null;
    }
    const video = document.getElementById('newClientCameraVideo');
    if (video) video.srcObject = null;
}

function startNewClientCamera() {
    const video = document.getElementById('newClientCameraVideo');
    if (!video || newClientCameraStream) return;

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
    })
        .then((stream) => {
            newClientCameraStream = stream;
            video.srcObject = stream;
            video.classList.remove('d-none');
        })
        .catch(() => {
            iziToast.error({ message: 'Impossible d\'accéder à la caméra pour la photo client.' });
        });
}

function resetEncNewClientPhoto() {
    encNewClientCroppedBlob = null;
    if (encNewClientPreviewUrl) {
        URL.revokeObjectURL(encNewClientPreviewUrl);
        encNewClientPreviewUrl = null;
    }
    const preview = document.getElementById('newClientPhotoPreview');
    if (preview) preview.src = 'assets/images/user.jpg';
}

function setupEncodageNewClientPhoto() {
    const cropperModalEl = document.getElementById('encNewClientCropperModal');
    const cropperImage = document.getElementById('encNewClientCropperImage');
    const cropperValidate = document.getElementById('encNewClientCropperValidate');
    const cropperPreviewEl = cropperModalEl?.querySelector('.cropper-preview-circle');
    const cropperModal = cropperModalEl ? new bootstrap.Modal(cropperModalEl) : null;

    let cropper = null;
    let cropperObjectUrl = null;

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function revokeCropperUrl() {
        if (cropperObjectUrl) {
            URL.revokeObjectURL(cropperObjectUrl);
            cropperObjectUrl = null;
        }
    }

    function openCropper(blob) {
        if (!cropperImage || !cropperModal) return;
        revokeCropperUrl();
        cropperObjectUrl = URL.createObjectURL(blob);
        cropperImage.src = cropperObjectUrl;
        cropperModal.show();
        cropperModalEl.addEventListener('shown.bs.modal', function onShown() {
            destroyCropper();
            cropper = new Cropper(cropperImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.92,
                responsive: true,
                background: false,
                preview: cropperPreviewEl || undefined,
            });
        }, { once: true });
    }

    function captureNewClientPhoto() {
        const video = document.getElementById('newClientCameraVideo');
        const canvas = document.getElementById('newClientCameraCanvas');
        if (!video || !canvas) return;

        const w = video.videoWidth;
        const h = video.videoHeight;
        if (!w || !h) {
            iziToast.warning({ message: 'La caméra n\'est pas prête.' });
            return;
        }
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(video, 0, 0, w, h);
        canvas.toBlob((blob) => {
            if (!blob) {
                iziToast.error({ message: 'Échec de la capture.' });
                return;
            }
            openCropper(blob);
        }, 'image/jpeg', 0.92);
    }

    document.getElementById('btnNewClientCapturePhoto')?.addEventListener('click', captureNewClientPhoto);
    document.getElementById('btnNewClientStartCamera')?.addEventListener('click', () => {
        stopNewClientCamera();
        startNewClientCamera();
    });

    cropperModalEl?.querySelectorAll('[data-crop-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!cropper) return;
            const action = btn.getAttribute('data-crop-action');
            if (action === 'zoom-in') cropper.zoom(0.1);
            if (action === 'zoom-out') cropper.zoom(-0.1);
            if (action === 'rotate-left') cropper.rotate(-90);
            if (action === 'rotate-right') cropper.rotate(90);
            if (action === 'reset') cropper.reset();
        });
    });

    cropperModalEl?.addEventListener('hidden.bs.modal', () => {
        destroyCropper();
        revokeCropperUrl();
        if (cropperImage) cropperImage.removeAttribute('src');
    });

    cropperValidate?.addEventListener('click', () => {
        if (!cropper) return;
        cropper.getCroppedCanvas({ width: 400, height: 400 }).toBlob((blob) => {
            if (!blob) {
                iziToast.error({ message: 'Impossible de rogner l\'image.' });
                return;
            }
            encNewClientCroppedBlob = blob;
            if (encNewClientPreviewUrl) URL.revokeObjectURL(encNewClientPreviewUrl);
            encNewClientPreviewUrl = URL.createObjectURL(blob);
            const preview = document.getElementById('newClientPhotoPreview');
            if (preview) preview.src = encNewClientPreviewUrl;
            cropperModal.hide();
            iziToast.success({ message: 'Photo client enregistrée pour cet encodage.' });
        }, 'image/jpeg', 0.92);
    });

    document.getElementById('clientType')?.addEventListener('change', () => {
        if (document.getElementById('clientType').value === 'new') {
            resetEncNewClientPhoto();
        }
    });
}

function setupClientPhotoSearch() {
    if (!window.AUTHENTIQ_REKOGNITION_ENABLED) return;

    const btnSearch = document.getElementById('btnSearchClientByPhoto');
    const btnCapture = document.getElementById('btnCaptureFace');
    const btnRetake = document.getElementById('btnRetakeFace');
    if (!btnSearch) return;

    btnCapture?.addEventListener('click', () => captureFaceFromCamera());

    btnRetake?.addEventListener('click', () => retakeFacePhoto());

    btnSearch.addEventListener('click', async () => {
        let blob = faceCaptureBlob;
        if (!blob) {
            blob = await captureFaceFromCamera();
        }
        if (!blob) return;
        searchClientByFaceBlob(blob);
    });
}

function renderRecapitulatifLoading() {
    const recap = document.getElementById('recapitulatif');
    if (!recap) return;
    recap.innerHTML = `<div class="enc-recap__loading">${renderEncDataLoader()}</div>`;
}

function formatEncDate(val) {
    if (!val) return '—';
    const raw = String(val).substring(0, 10);
    const parts = raw.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const d = new Date(val);
    return Number.isNaN(d.getTime()) ? raw : d.toLocaleDateString('fr-FR');
}

function formatEncMontant(val) {
    if (val == null || val === '') return '—';
    const n = Number(val);
    if (Number.isNaN(n)) return String(val);
    return `${n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} FC`;
}

function renderRecapitulatifError(message) {
    const recap = document.getElementById('recapitulatif');
    if (!recap) return;
    recap.innerHTML = `
        <div class="enc-recap-error">
            <iconify-icon icon="solar:danger-circle-bold-duotone" style="font-size:2rem;"></iconify-icon>
            <p class="mt-2 mb-0">${escapeHtml(message || 'Impossible de charger le récapitulatif.')}</p>
        </div>`;
}

function recapInfoItem(icon, label, value) {
    return `
        <li class="enc-recap-menu__item">
            <span class="enc-recap-menu__icon"><iconify-icon icon="${icon}"></iconify-icon></span>
            <span class="enc-recap-menu__text">
                <span class="enc-recap-menu__label">${escapeHtml(label)}</span>
                <span class="enc-recap-menu__value">${escapeHtml(value || '—')}</span>
            </span>
        </li>`;
}

function recapMenuItem(icon, text) {
    return `
        <li class="enc-recap-menu__item">
            <span class="enc-recap-menu__icon"><iconify-icon icon="${icon}"></iconify-icon></span>
            <span class="enc-recap-menu__text">${escapeHtml(text)}</span>
        </li>`;
}

function renderRecapitulatifHtml(data) {
    const client = data.client || {};
    const doc = data.document || {};
    const enc = data.encodage || {};
    const qr = data.qr || {};
    const pages = Array.isArray(data.pages) ? data.pages : [];
    const pageCount = data.pageCount ?? pages.length ?? scannedPages.length ?? 0;
    const isComplete = enc.status === 'complete';
    const docMissing = !enc.id_doc && !doc.nom_doc;
    const docMissingBanner = docMissing && !isComplete
        ? `<div class="alert alert-warning enc-recap-doc-missing mb-3" role="alert">
            <iconify-icon icon="solar:document-bold-duotone"></iconify-icon>
            Type de document non enregistré — complétez l\'étape « Document » avant de finaliser.
           </div>`
        : '';
    const associated = Array.isArray(data.associated_clients) ? data.associated_clients : [];
    const multiClients = associated.length > 1;
    const photoUrl = client.photo_url || 'assets/images/user.jpg';
    const tel = client.tel || '';
    const email = client.email || '';
    const refNum = qr.numero || enc.numero || '—';
    const clientsListHtml = multiClients
        ? `<ul class="enc-recap-clients-list list-unstyled mb-0 mt-2">
            ${associated.map((c) => `<li class="small">${escapeHtml(c.nom_complet || '—')}${c.is_primary ? ' <span class="text-muted">(principal)</span>' : ''}</li>`).join('')}
           </ul>`
        : '';

    const telBtn = tel
        ? `<a href="tel:${escapeAttr(tel.replace(/\s/g, ''))}" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px"><iconify-icon icon="solar:phone-bold"></iconify-icon><span>Appeler</span></a>`
        : `<button type="button" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px" disabled><iconify-icon icon="solar:phone-bold"></iconify-icon><span>Appeler</span></button>`;
    const mailBtn = email
        ? `<a href="mailto:${escapeAttr(email)}" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px"><iconify-icon icon="solar:letter-bold"></iconify-icon><span>Email</span></a>`
        : `<button type="button" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px" disabled><iconify-icon icon="solar:letter-bold"></iconify-icon><span>Email</span></button>`;

    const sortedPages = pages.slice().sort((a, b) => (a.page_number ?? 0) - (b.page_number ?? 0));
    const galleryCells = sortedPages.slice(0, 6).map((p, i) => `
        <div class="enc-recap-gallery__cell-wrap">
            <button type="button" class="enc-recap-gallery__cell" data-page-index="${i}" title="Voir la page ${p.page_number}">
                <img src="${escapeAttr(p.file_path)}" alt="Page ${p.page_number}" loading="lazy">
            </button>
        </div>`).join('');
    const moreCell = sortedPages.length > 6
        ? `<div class="enc-recap-gallery__cell-wrap">
            <button type="button" class="enc-recap-gallery__cell enc-recap-gallery__cell--more" data-page-index="6" title="Voir les autres pages">
                <span>+${sortedPages.length - 6}</span>
            </button>
           </div>`
        : '';
    const emptyGallery = pageCount === 0
        ? '<div class="enc-recap-gallery__empty">Aucune page importée</div>'
        : '';

    const editActions = isComplete
        ? ''
        : `
            <div class="enc-recap-edit-actions" role="group" aria-label="Modifier l'encodage">
                <span class="enc-recap-edit-actions__label">Modifier avant validation :</span>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="1">
                    <iconify-icon icon="solar:document-add-bold-duotone"></iconify-icon> Pages PDF
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="3">
                    <iconify-icon icon="solar:document-bold-duotone"></iconify-icon> Document
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="4">
                    <iconify-icon icon="solar:user-bold-duotone"></iconify-icon> Client
                </button>
            </div>`;

    const verifyBody = qr.qr_url
        ? `
            <p class="enc-recap-verify__ref-label">Référence</p>
            <p class="enc-recap-verify__ref">${escapeHtml(refNum)}</p>
            <div class="enc-recap-verify__qr-wrap">
                <img src="${escapeAttr(qr.qr_url)}" alt="QR code" class="enc-recap-verify__qr-img">
            </div>
            ${qr.verify_url ? `<a href="${escapeAttr(qr.verify_url)}" class="enc-recap-verify__link" target="_blank" rel="noopener">Ouvrir la page de vérification</a>` : ''}
        `
        : `
            <div class="enc-recap-verify__pending">
                <iconify-icon icon="solar:qr-code-bold-duotone"></iconify-icon>
                <p>QR code et référence générés à la finalisation</p>
            </div>
        `;

    return `
        ${editActions}
        ${docMissingBanner}
        <div class="enc-recap-bento">
            <div class="enc-recap-bento__row enc-recap-bento__row--top">
                <article class="enc-recap-tile enc-recap-tile--profile">
                    <div class="enc-recap-profile">
                        <div class="enc-recap-profile__avatar-wrap">
                            <img src="${escapeAttr(photoUrl)}" alt="" class="enc-recap-profile__avatar">
                            <span class="enc-recap-profile__badge" title="Client identifié">
                                <iconify-icon icon="solar:verified-check-bold"></iconify-icon>
                            </span>
                        </div>
                        <p class="enc-recap-profile__name">${escapeHtml(multiClients ? `${associated.length} clients associés` : (client.nom_complet || '—'))}</p>
                        ${clientsListHtml}
                        <div class="enc-recap-profile__actions">
                            ${telBtn}
                            ${mailBtn}
                        </div>
                    </div>
                </article>
                <article class="enc-recap-tile enc-recap-tile--info">
                    <header class="enc-recap-tile__head enc-recap-tile__head--tabs">
                        <h5 class="enc-recap-tile__title">Informations</h5>
                        <div class="enc-recap-tile__head-end">
                            <span class="enc-recap-tile__pill ${isComplete ? 'enc-recap-tile__pill--ok' : 'enc-recap-tile__pill--wait'}">${isComplete ? 'Finalisé' : 'En attente'}</span>
                            <div class="enc-recap-tabs" role="tablist">
                                <button type="button" class="enc-recap-tab is-active" data-recap-tab="info-client" role="tab">Client</button>
                                <button type="button" class="enc-recap-tab" data-recap-tab="info-document" role="tab">Document</button>
                            </div>
                        </div>
                    </header>
                    <div class="enc-recap-menu-panels">
                        <ul class="enc-recap-menu enc-recap-menu-panel is-active" data-recap-panel="info-client">
                            ${recapInfoItem('solar:user-bold-duotone', 'Nom complet', client.nom_complet)}
                            ${recapInfoItem('solar:phone-bold-duotone', 'Téléphone', tel)}
                            ${recapInfoItem('solar:letter-bold-duotone', 'Email', email)}
                        </ul>
                        <ul class="enc-recap-menu enc-recap-menu-panel" data-recap-panel="info-document" hidden>
                            ${recapInfoItem('solar:document-text-bold-duotone', 'Type de document', doc.nom_doc)}
                            ${recapInfoItem('solar:wallet-money-bold-duotone', 'Montant', formatEncMontant(enc.montant))}
                            ${recapInfoItem('solar:calendar-bold-duotone', 'Date d\'émission', formatEncDate(enc.date_emission))}
                            ${recapInfoItem('solar:calendar-mark-bold-duotone', 'Date d\'expiration', formatEncDate(enc.date_expiration))}
                            ${recapInfoItem('solar:gallery-bold-duotone', 'Pages PDF', String(pageCount))}
                        </ul>
                    </div>
                </article>
            </div>
            <div class="enc-recap-bento__row enc-recap-bento__row--bottom">
                <article class="enc-recap-tile enc-recap-tile--verify">
                    <header class="enc-recap-tile__head">
                        <h5 class="enc-recap-tile__title">Vérification</h5>
                    </header>
                    <div class="enc-recap-verify__body">
                        ${verifyBody}
                    </div>
                    <ul class="enc-recap-verify__legend">
                        <li><span class="enc-recap-dot enc-recap-dot--ok"></span> Encodage finalisé</li>
                        <li><span class="enc-recap-dot enc-recap-dot--wait"></span> En attente</li>
                        <li><span class="enc-recap-dot enc-recap-dot--ref"></span> Référence active</li>
                    </ul>
                </article>
                <article class="enc-recap-tile enc-recap-tile--menu">
                    <header class="enc-recap-tile__head enc-recap-tile__head--tabs">
                        <h5 class="enc-recap-tile__title">Synthèse</h5>
                        <div class="enc-recap-tabs" role="tablist">
                            <button type="button" class="enc-recap-tab is-active" data-recap-tab="client" role="tab">Client</button>
                            <button type="button" class="enc-recap-tab" data-recap-tab="document" role="tab">Document</button>
                        </div>
                    </header>
                    <div class="enc-recap-menu-panels">
                        <ul class="enc-recap-menu enc-recap-menu-panel is-active" data-recap-panel="client">
                            ${recapMenuItem('solar:user-bold-duotone', client.nom_complet || '—')}
                            ${recapMenuItem('solar:phone-bold-duotone', tel || '—')}
                            ${recapMenuItem('solar:letter-bold-duotone', email || '—')}
                        </ul>
                        <ul class="enc-recap-menu enc-recap-menu-panel" data-recap-panel="document" hidden>
                            ${recapMenuItem('solar:document-text-bold-duotone', doc.nom_doc || '—')}
                            ${recapMenuItem('solar:wallet-money-bold-duotone', formatEncMontant(enc.montant))}
                            ${recapMenuItem('solar:calendar-bold-duotone', `Émission : ${formatEncDate(enc.date_emission)}`)}
                            ${recapMenuItem('solar:calendar-mark-bold-duotone', `Expiration : ${formatEncDate(enc.date_expiration)}`)}
                        </ul>
                    </div>
                </article>
                <article class="enc-recap-tile enc-recap-tile--gallery">
                    <header class="enc-recap-tile__head">
                        <h5 class="enc-recap-tile__title">Pages PDF</h5>
                        <span class="enc-recap-tile__count">${pageCount}</span>
                    </header>
                    <div class="enc-recap-gallery__grid">
                        ${galleryCells}${moreCell || emptyGallery}
                    </div>
                </article>
            </div>
        </div>`;
}

let recapScannedPages = [];
let recapPageViewerIndex = 0;
let encPagesViewerModalInstance = null;
let encPagesViewerKeyHandler = null;

function setRecapScannedPages(pages) {
    recapScannedPages = (pages || [])
        .slice()
        .sort((a, b) => (a.page_number ?? 0) - (b.page_number ?? 0));

    if (pages?.length) {
        applyServerPagesToScanned(pages);
    }
}

function getEncPagesViewerModal() {
    const el = document.getElementById('encPagesViewerModal');
    if (!el || typeof bootstrap === 'undefined') {
        return null;
    }
    if (!encPagesViewerModalInstance) {
        encPagesViewerModalInstance = new bootstrap.Modal(el);
        initEncPagesViewerModal(el);
    }
    return encPagesViewerModalInstance;
}

function initEncPagesViewerModal(modalEl) {
    if (modalEl.dataset.bound === '1') {
        return;
    }
    modalEl.dataset.bound = '1';

    document.getElementById('encPagesViewerPrev')?.addEventListener('click', () => {
        if (recapPageViewerIndex > 0) {
            recapPageViewerIndex -= 1;
            renderRecapPageViewer();
        }
    });

    document.getElementById('encPagesViewerNext')?.addEventListener('click', () => {
        if (recapPageViewerIndex < recapScannedPages.length - 1) {
            recapPageViewerIndex += 1;
            renderRecapPageViewer();
        }
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        encPagesViewerKeyHandler = (e) => {
            if (e.key === 'ArrowLeft' && recapPageViewerIndex > 0) {
                recapPageViewerIndex -= 1;
                renderRecapPageViewer();
            } else if (e.key === 'ArrowRight' && recapPageViewerIndex < recapScannedPages.length - 1) {
                recapPageViewerIndex += 1;
                renderRecapPageViewer();
            }
        };
        document.addEventListener('keydown', encPagesViewerKeyHandler);
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        if (encPagesViewerKeyHandler) {
            document.removeEventListener('keydown', encPagesViewerKeyHandler);
            encPagesViewerKeyHandler = null;
        }
    });
}

function renderRecapPageViewer() {
    const page = recapScannedPages[recapPageViewerIndex];
    const img = document.getElementById('encPagesViewerImg');
    const title = document.getElementById('encPagesViewerTitle');
    const counter = document.getElementById('encPagesViewerCounter');
    const prevBtn = document.getElementById('encPagesViewerPrev');
    const nextBtn = document.getElementById('encPagesViewerNext');

    if (!page || !img) {
        return;
    }

    img.src = page.file_path;
    img.alt = `Page ${page.page_number ?? recapPageViewerIndex + 1}`;

    const num = page.page_number ?? recapPageViewerIndex + 1;
    const total = recapScannedPages.length;
    if (title) {
        title.textContent = `Page ${num}`;
    }
    if (counter) {
        counter.textContent = `Page ${recapPageViewerIndex + 1} sur ${total}`;
    }

    const multi = total > 1;
    if (prevBtn) {
        prevBtn.disabled = recapPageViewerIndex <= 0;
        prevBtn.hidden = !multi;
    }
    if (nextBtn) {
        nextBtn.disabled = recapPageViewerIndex >= total - 1;
        nextBtn.hidden = !multi;
    }
}

function openRecapPageViewer(index = 0) {
    if (!recapScannedPages.length) {
        return;
    }
    recapPageViewerIndex = Math.max(0, Math.min(index, recapScannedPages.length - 1));
    renderRecapPageViewer();
    getEncPagesViewerModal()?.show();
}

function initRecapitulatifInteractions() {
    const recap = document.getElementById('recapitulatif');
    if (!recap) return;

    recap.querySelectorAll('[data-edit-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.getAttribute('data-edit-step'), 10);
            if (Number.isNaN(step) || !isEncodageEditable()) {
                return;
            }
            if (!canNavigateToStep(step)) {
                iziToast.warning({ message: getStepBlockedMessage(step) });

                return;
            }
            goToStep(step);
        });
    });

    recap.querySelectorAll('.enc-recap-gallery__cell[data-page-index]').forEach((cell) => {
        cell.addEventListener('click', () => {
            const idx = parseInt(cell.getAttribute('data-page-index'), 10);
            if (!Number.isNaN(idx)) {
                openRecapPageViewer(idx);
            }
        });
    });

    recap.querySelectorAll('.enc-recap-tile--info, .enc-recap-tile--menu').forEach((tile) => {
        tile.querySelectorAll('.enc-recap-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-recap-tab');
                tile.querySelectorAll('.enc-recap-tab').forEach((t) => t.classList.toggle('is-active', t === tab));
                tile.querySelectorAll('.enc-recap-menu-panel').forEach((panel) => {
                    const active = panel.getAttribute('data-recap-panel') === target;
                    panel.classList.toggle('is-active', active);
                    if (active) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', '');
                    }
                });
            });
        });
    });
}

function syncRecapFinalizeButton(data) {
    const submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) return;

    if (data?.encodage?.status) {
        setEncodageStatus(data.encodage.status);
    }

    const enc = data?.encodage || {};
    const isComplete = enc.status === 'complete';
    const pageCount = data?.pageCount ?? data?.pages?.length ?? 0;
    const associated = Array.isArray(data?.associated_clients) ? data.associated_clients : [];
    const clientCount = associated.length || (enc.id_client ? 1 : 0);
    const missingDoc = !enc.id_doc;
    const missingClient = clientCount < 1;
    const missingMultipleClients = data?.document?.ownership === 'multiple' && clientCount < 2;
    const cannotFinalize = !isComplete && (missingDoc || missingClient || missingMultipleClients || pageCount < 1 || scanPagesDirty);
    submitBtn.disabled = isComplete || cannotFinalize;
    if (cannotFinalize && !isComplete) {
        submitBtn.title = scanPagesDirty
            ? 'Pages modifiées : repassez par l\'étape scan (Suivant).'
            : missingDoc
                ? 'Enregistrez le type de document (étape 3).'
                : missingMultipleClients
                    ? 'Propriété multiple : associez au moins deux clients (étape 4).'
                : missingClient
                    ? 'Associez un client (étape 4).'
                    : 'Au moins une page PDF requise.';
    } else {
        submitBtn.removeAttribute('title');
    }
    const label = submitBtn.querySelector('.js-finalize-label');
    if (label) {
        label.textContent = isComplete ? 'Encodage finalisé' : 'Finaliser l\'encodage';
    }
}

function loadRecapitulatif() {
    renderRecapitulatifLoading();

    fetch(`${ENCODAGE_API}/${encodageId}/recap`, { headers: encodeApiHeaders() })
        .then(async (response) => {
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || data.status === 'error') {
                renderRecapitulatifError(data?.message || 'Encodage introuvable.');
                return;
            }
            const recap = document.getElementById('recapitulatif');
            if (recap) {
                recap.innerHTML = renderRecapitulatifHtml(data);
                setRecapScannedPages(data.pages);
                initRecapitulatifInteractions();
                syncRecapFinalizeButton(data);
            }
        })
        .catch(() => renderRecapitulatifError('Erreur réseau lors du chargement.'));
}

function setFinalizeLoading(loading) {
    const btn = document.getElementById('submitBtn');
    if (!btn) return;

    const label = btn.querySelector('.js-finalize-label');
    const alreadyDone = btn.disabled && (label?.textContent?.includes('finalisé') ?? false);

    if (alreadyDone) return;

    btn.disabled = loading;
    btn.classList.toggle('is-loading', loading);
    if (label) {
        label.textContent = loading ? 'Finalisation en cours…' : 'Finaliser l\'encodage';
    }
}

function finalizeEncodage() {
    const btn = document.getElementById('submitBtn');
    if (btn?.disabled) {
        return;
    }

    setFinalizeLoading(true);

    fetch(`${ENCODAGE_API}/${encodageId}/finalize`, {
        method: 'POST',
        headers: encodeApiHeaders(true),
        body: JSON.stringify({}),
    })
        .then((response) => response.json())
        .then((data) => {
            setFinalizeLoading(false);
            if (data.status === 'success') {
                setEncodageStatus('complete');
                let msg = 'Encodage finalisé avec succès !';
                if (data.numero) {
                    msg += ` Référence : ${data.numero}.`;
                }
                loadRecapitulatif();
                iziToast.success({
                    title: 'Succès',
                    message: msg,
                    timeout: data.qr_url ? 5000 : 3000,
                    onClosed: () => { window.location.href = ENCODAGE_DASHBOARD_URL; },
                });
            } else {
                iziToast.error({ message: data.message || 'Échec de la finalisation.' });
            }
        })
        .catch(() => {
            setFinalizeLoading(false);
            iziToast.error({ message: 'Erreur serveur lors de la finalisation.' });
        });
}

function goToStep(stepNumber) {
    const wizard = document.querySelector('.encodage-wizard');
    if (!wizard) return false;

    if (!canNavigateToStep(stepNumber)) {
        iziToast.warning({ message: getStepBlockedMessage(stepNumber) });

        return false;
    }

    if (!isEncodageEditable() && stepNumber < 5) {
        iziToast.info({
            message: 'Encodage finalisé : les modifications ne sont plus possibles.',
        });

        return false;
    }

    wizard.querySelectorAll('.step').forEach((step) => step.classList.remove('active'));

    const target = wizard.querySelector(`#step${stepNumber}`);
    if (!target) {
        console.error('Étape introuvable:', stepNumber);
        return false;
    }
    target.classList.add('active');

    wizard.querySelectorAll('.stepper-header .step-item').forEach((item, index) => {
        item.classList.remove('active');
        if (index + 1 < stepNumber) {
            item.classList.add('completed');
        } else if (index + 1 === stepNumber) {
            item.classList.add('active');
        } else {
            item.classList.remove('completed');
        }
    });

    currentStep = stepNumber;

    if (stepNumber === 1) {
        syncScanStepNextButton();
    }

    if (stepNumber === 3) {
        updateDateFieldsState();
        stopFaceCamera();
        stopNewClientCamera();
    } else if (stepNumber === ENCODAGE_CLIENT_STEP) {
        syncStep3Cameras();
        updateAssociatedClientsHint();
        renderAssociatedClientsChips();
    } else {
        stopFaceCamera();
        stopNewClientCamera();
    }

    if (stepNumber === 5 && encodageId) {
        loadRecapitulatif();
    }

    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    return true;
}
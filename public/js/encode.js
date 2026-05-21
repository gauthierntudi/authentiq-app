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
let capturedImageBlob = null;
let encodageId = null;
let clientId = null;
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

// OpenCV
let opencvReady = false;

// Durée du document sélectionné (pour calcul date expiration)
let currentDocDuree = 0;
let pendingResumePayload = null;
let clientAwaitingOtp = false;
let pendingOtpGoNext = false;
let otpClientIdForVerification = null;
let encodageStatus = 'incomplete';
/** Pages scannées modifiées localement sans repasser par OCR + sauvegarde serveur. */
let scanPagesDirty = false;

let newClientCameraStream = null;
let encNewClientCroppedBlob = null;
let encNewClientPreviewUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    initializeCamera();
    loadDocumentTypes();
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
    if (!encodageId) {
        return 'Enregistrez d\'abord au moins une page scannée (étape 1 → Suivant).';
    }
    if (step === 2 && scannedPages.length === 0) {
        return 'Scannez au moins une page avant l\'étape OCR.';
    }
    if (scanPagesDirty) {
        return 'Les pages scannées ont été modifiées. À l\'étape 1, cliquez « Suivant » pour relancer l\'OCR et enregistrer les pages.';
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
    if (!encodageId) {
        return false;
    }
    if (scanPagesDirty && step >= 2) {
        return false;
    }

    return true;
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

function applyResumedEncodage(data) {
    const enc = data.encodage;
    const pages = data.pages || [];

    setEncodageStatus(enc.status);

    encodageId = enc.id_encodage;
    clientId = enc.id_client || null;
    document.getElementById('encodageId').value = encodageId;
    if (clientId) document.getElementById('clientId').value = clientId;

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

    if (!enc.id_client) goToStep(3);
    else if (!enc.id_doc) goToStep(4);
    else {
        loadRecapitulatif();
        goToStep(5);
    }

    iziToast.info({ message: 'Encodage repris. Vous pouvez continuer où vous vous êtes arrêté.', position: 'topRight' });
}

// Callback OpenCV
function onOpenCvReady() {
    opencvReady = true;
    console.log('OpenCV.js chargé');
    iziToast.success({ title: 'OpenCV.js', message: 'Détection avancée activée', position: 'bottomRight' });
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

function setupEventListeners() {
    document.getElementById('captureButton').addEventListener('click', captureImageWithDetection);
    document.getElementById('recaptureButton').addEventListener('click', recaptureImage);
    document.getElementById('nextStep1')?.addEventListener('click', () => proceedFromScanStep());
    document.getElementById('nextStep1Bar')?.addEventListener('click', () => proceedFromScanStep());
    updateScanPagesUI();

    document.getElementById('nextStep2').addEventListener('click', () => goToStep(3));

    document.getElementById('clientType').addEventListener('change', toggleClientType);
    toggleClientType();
    setupClientSearch();
    document.getElementById('saveStep3').addEventListener('click', saveClientInfo);
    document.getElementById('nextStep3').addEventListener('click', () => saveClientInfo(true));

    document.getElementById('docType').addEventListener('change', loadDocumentInfo);
    try {
        initEncodageDatePickers();
    } catch (err) {
        console.error('Flatpickr encodage:', err);
    }

    document.getElementById('saveStep4').addEventListener('click', saveDocumentInfo);
    document.getElementById('nextStep4').addEventListener('click', () => saveDocumentInfo(true));

    document.getElementById('submitBtn')?.addEventListener('click', finalizeEncodage);

    setupScanCameraModal();

    bindWizardNavigation();
    bindStepperNavigation();
    bindScanPageRemoveHandlers();
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
function captureImageWithDetection() {
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

    iziToast.info({ message: 'Détection en cours...', timeout: 1000 });

    setTimeout(() => {
        if (opencvReady) {
            detectWithOpenCV(canvas);
        } else {
            detectDocumentCorners(canvas);
        }
        showCropInterface();
    }, 50);
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

function renderScanPagesListHtml() {
    if (scannedPages.length === 0) {
        return '';
    }

    const canDelete = isEncodageEditable();

    return scannedPages
        .map((page, i) => {
            const label = page.page_number ? `Page ${page.page_number}` : `Page ${i + 1}`;
            const deleteBtn = canDelete
                ? `<button type="button" class="btn btn-sm btn-icon rounded-circle scan-pages-summary__remove" data-remove-page-index="${i}" title="Supprimer ${label}" aria-label="Supprimer ${label}">
                    <iconify-icon icon="solar:trash-bin-trash-bold" aria-hidden="true"></iconify-icon>
                   </button>`
                : '';

            return `<li class="scan-pages-summary__item">
                <span class="scan-pages-summary__label"><iconify-icon icon="solar:document-bold-duotone"></iconify-icon> ${label}</span>
                ${deleteBtn}
            </li>`;
        })
        .join('');
}

function getScanPagesCountLabel(n) {
    if (n === 0) {
        return 'Aucune page enregistrée';
    }
    if (n === 1) {
        return '1 page — document à une page (vous pouvez continuer ou en ajouter une autre)';
    }

    return `${n} pages enregistrées — document multipages`;
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

function bindScanPageRemoveHandlers() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-page-index]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const index = parseInt(btn.getAttribute('data-remove-page-index'), 10);
        if (!Number.isNaN(index)) {
            removeScannedPage(index);
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-recap-page]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const idPage = parseInt(btn.getAttribute('data-remove-recap-page'), 10);
        if (Number.isNaN(idPage)) {
            return;
        }
        const index = scannedPages.findIndex((p) => p.id_page === idPage);
        if (index >= 0) {
            removeScannedPage(index);
        } else {
            removeScannedPageById(idPage);
        }
    });
}

async function removeScannedPage(index) {
    if (!isEncodageEditable()) {
        iziToast.info({ message: 'Encodage finalisé : suppression impossible.' });

        return;
    }

    const page = scannedPages[index];
    if (!page) {
        return;
    }

    if (scannedPages.length <= 1) {
        iziToast.warning({
            message: 'Impossible de supprimer la dernière page. L\'encodage doit contenir au moins une page scannée.',
        });

        return;
    }

    const label = page.page_number ? `page ${page.page_number}` : `page ${index + 1}`;
    if (!window.confirm(`Supprimer la ${label} ? Cette action est définitive.`)) {
        return;
    }

    if (encodageId && page.id_page) {
        const ok = await removeScannedPageOnServer(page.id_page);
        if (!ok) {
            return;
        }
    } else {
        scannedPages.splice(index, 1);
        markScanPagesDirty();
        syncOcrTextFromPages();
        updateScanPagesUI();
        iziToast.success({ message: 'Page retirée.' });
    }

    if (currentStep === 5 && encodageId) {
        loadRecapitulatif();
    }
}

async function removeScannedPageById(idPage) {
    if (!encodageId || !idPage) {
        return;
    }

    if (scannedPages.length <= 1 && scannedPages.some((p) => p.id_page === idPage)) {
        iziToast.warning({ message: 'Impossible de supprimer la dernière page.' });

        return;
    }

    if (!window.confirm('Supprimer cette page scannée ?')) {
        return;
    }

    await removeScannedPageOnServer(idPage);
    if (currentStep === 5 && encodageId) {
        loadRecapitulatif();
    }
}

async function removeScannedPageOnServer(idPage) {
    const formData = new FormData();
    formData.append('encodageId', encodageId);
    formData.append('pageId', idPage);

    showEncPagesLoader('scanPagesSummary');

    try {
        const response = await fetch(`${ENCODAGE_API}/delete-page`, {
            method: 'POST',
            headers: encodeApiHeaders(),
            body: formData,
        });
        const data = await response.json();

        if (response.status === 423 || data.status !== 'success') {
            iziToast.error({ message: data.message || 'Suppression impossible.' });
            if (response.status === 423) {
                setEncodageStatus('complete');
            }

            return false;
        }

        if (data.pages) {
            applyServerPagesToScanned(data.pages);
        } else {
            const idx = scannedPages.findIndex((p) => p.id_page === idPage);
            if (idx >= 0) {
                scannedPages.splice(idx, 1);
            }
            syncOcrTextFromPages();
            updateScanPagesUI();
        }

        iziToast.success({ message: data.message || 'Page supprimée.' });

        return true;
    } catch (err) {
        console.error('delete-page:', err);
        iziToast.error({ message: 'Erreur lors de la suppression de la page.' });

        return false;
    } finally {
        hideEncPagesLoader('scanPagesSummary');
    }
}

function isScanPreviewVisible() {
    const preview = document.getElementById('previewContainer');
    if (!preview) {
        return false;
    }

    return preview.style.display === 'block';
}

function syncScanStepNextButton() {
    const actions = document.getElementById('scanStepActions');
    const n = scannedPages.length;
    if (!actions) {
        return;
    }

    const showBar = n > 0 && !isScanPreviewVisible();
    actions.hidden = !showBar;
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
    const listHtml = renderScanPagesListHtml();

    if (countEl) {
        countEl.textContent = countLabel;
    }

    if (ocrCountEl) {
        ocrCountEl.textContent = countLabel;
    }

    if (summaryEl) {
        summaryEl.classList.toggle('is-single', n === 1);
    }

    if (ocrToolbar) {
        ocrToolbar.hidden = n === 0;
    }

    if (listEl) {
        listEl.innerHTML = listHtml;
    }

    if (ocrListEl) {
        ocrListEl.innerHTML = listHtml;
    }

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
    const run = async () => {
        if (hasPendingCapture()) {
            await commitCurrentCaptureToScan(true);
        }
        if (scannedPages.length === 0) {
            iziToast.warning({
                message: 'Scannez au moins une page (document une page ou plusieurs). Capturez puis « Valider cette page » ou « Suivant ».',
            });
            return;
        }
        goToStep(2);
        processAllPagesOCR();
    };
    run();
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

function processAllPagesOCR() {
    showEncPagesLoader('step2');

    const ocrProgress = document.getElementById('ocrProgress');
    const ocrProgressText = document.getElementById('ocrProgressText');
    const ocrTextArea = document.getElementById('ocrText');
    
    ocrProgress.style.display = 'block';
    ocrProgressText.textContent = '0%';
    
    let allText = '';
    let processedCount = 0;
    const pagesNeedingOcr = scannedPages.filter(pageNeedsOcr).length;
    const ocrDenom = Math.max(pagesNeedingOcr, 1);
    
    const processPage = (index) => {
        if (index >= scannedPages.length) {
            ocrTextArea.value = allText;
            ocrProgress.style.display = 'none';
            const msg = pagesNeedingOcr > 0
                ? `OCR terminé pour ${pagesNeedingOcr} page(s).`
                : 'Texte OCR existant conservé.';
            iziToast.success({ message: msg });
            saveImageAndOCR();
            return;
        }

        const page = scannedPages[index];
        if (!pageNeedsOcr(page)) {
            allText += `--- Page ${index + 1} ---\n${page.ocrText}\n\n`;
            processPage(index + 1);
            return;
        }
        
        Tesseract.recognize(page.image, 'fra+eng', {
            logger: info => {
                if (info.status === 'recognizing text') {
                    const pageProgress = (processedCount + info.progress) / ocrDenom;
                    ocrProgressText.textContent = Math.round(pageProgress * 100) + '%';
                }
            }
        }).then(({ data: { text } }) => {
            scannedPages[index].ocrText = text;
            scannedPages[index]._isNewCapture = false;
            allText += `--- Page ${index + 1} ---\n${text}\n\n`;
            processedCount++;
            processPage(index + 1);
        }).catch(err => {
            console.error('Erreur OCR page', index + 1, err);
            scannedPages[index].ocrText = '';
            scannedPages[index]._isNewCapture = false;
            processedCount++;
            processPage(index + 1);
        });
    };
    
    processPage(0);
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

async function ensurePageBlobsForUpload() {
    for (const page of scannedPages) {
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
        iziToast.error({ message: 'Impossible de préparer les fichiers des pages pour l\'envoi.' });
        hideEncPagesLoader('step2');

        return;
    }

    const missing = scannedPages.filter((p) => !(p.blob instanceof Blob) || p.blob.size === 0);
    if (missing.length > 0) {
        iziToast.error({
            message: `${missing.length} page(s) sans fichier image. Repassez par l\'étape scan (Suivant).`,
        });
        hideEncPagesLoader('step2');

        return;
    }

    const formData = new FormData();

    scannedPages.forEach((page, index) => {
        formData.append(`file_${index}`, page.blob, `document_page_${index + 1}.jpg`);
        formData.append(`ocr_${index}`, page.ocrText || '');
    });

    formData.append('ocrText', document.getElementById('ocrText').value);
    formData.append('pageCount', scannedPages.length);
    if (encodageId) formData.append('encodageId', encodageId);

    fetch(`${ENCODAGE_API}/save-image-ocr`, {
        method: 'POST',
        headers: encodeApiHeaders(),
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            encodageId = data.encodageId;
            document.getElementById('encodageId').value = encodageId;
            setEncodageStatus('incomplete');
            clearScanPagesDirty();
            if (Array.isArray(data.pages)) {
                data.pages.forEach((p, i) => {
                    if (scannedPages[i]) {
                        scannedPages[i].id_page = p.id_page ?? null;
                        scannedPages[i].page_number = p.page_number ?? i + 1;
                        scannedPages[i]._isNewCapture = false;
                        scannedPages[i].blob = null;
                        if (p.file_path) {
                            scannedPages[i].image = p.file_path;
                        }
                        if (p.ocr_text) {
                            scannedPages[i].ocrText = p.ocr_text;
                        }
                    }
                });
                syncOcrTextFromPages();
                updateScanPagesUI();
            }
            iziToast.success({ message: data.message || `${scannedPages.length} page(s) sauvegardée(s).` });
            if (data.textract_queued && window.AUTHENTIQ_TEXTRACT_ENABLED) {
                pollTextractOcr(encodageId);
            }
        } else {
            iziToast.error({ message: data.message });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        iziToast.error({ message: 'Erreur lors de la sauvegarde.' });
    })
    .finally(() => hideEncPagesLoader('step2'));
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
    if (currentStep !== 3) {
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
                        goToStep(4);
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
                clientId = data.clientId;
                document.getElementById('clientId').value = clientId;

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
                if (andGoNext) goToStep(4);
            } else {
                iziToast.error({ message: data.message || 'Erreur lors de l\'enregistrement.' });
            }
        })
        .catch(() => iziToast.error({ message: 'Erreur lors de l\'enregistrement du client.' }));
}

function saveClientInfo(andGoNext = false) {
    if (!encodageId) {
        iziToast.warning({ message: 'Enregistrez d\'abord les pages scannées (étape OCR).' });
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
        if (clientSelect.value) {
            formData.append('clientId', clientSelect.value);
        } else {
            iziToast.warning({ message: 'Veuillez sélectionner un client.' });
            return;
        }
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
    formData.append('clientPhoto', encNewClientCroppedBlob, 'client-photo.jpg');

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
    if (clientAwaitingOtp) {
        iziToast.warning({ message: 'Activez le client par OTP avant de renseigner le document.' });
        goToStep(3);
        if (typeof window.showEncodageOtpModal === 'function') window.showEncodageOtpModal();
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
                loadRecapitulatif();
                goToStep(5);
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
        && currentStep === 3
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
    clientId = c.id_client;
    document.getElementById('clientId').value = clientId;
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
    const photoUrl = client.photo_url || 'assets/images/user.jpg';
    const tel = client.tel || '';
    const email = client.email || '';
    const refNum = qr.numero || enc.numero || '—';

    const telBtn = tel
        ? `<a href="tel:${escapeAttr(tel.replace(/\s/g, ''))}" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px"><iconify-icon icon="solar:phone-bold"></iconify-icon><span>Appeler</span></a>`
        : `<button type="button" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px" disabled><iconify-icon icon="solar:phone-bold"></iconify-icon><span>Appeler</span></button>`;
    const mailBtn = email
        ? `<a href="mailto:${escapeAttr(email)}" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px"><iconify-icon icon="solar:letter-bold"></iconify-icon><span>Email</span></a>`
        : `<button type="button" class="btn btn-sm btn-light fw-semibold enc-recap-profile__btn" style="border-radius:12px" disabled><iconify-icon icon="solar:letter-bold"></iconify-icon><span>Email</span></button>`;

    const sortedPages = pages.slice().sort((a, b) => (a.page_number ?? 0) - (b.page_number ?? 0));
    const canDeletePages = !isComplete && isEncodageEditable();
    const galleryCells = sortedPages.slice(0, 6).map((p, i) => {
        const deleteBtn = canDeletePages && p.id_page
            ? `<button type="button" class="enc-recap-gallery__delete" data-remove-recap-page="${p.id_page}" title="Supprimer la page ${p.page_number}" aria-label="Supprimer la page">
                <iconify-icon icon="solar:trash-bin-trash-bold-duotone"></iconify-icon>
               </button>`
            : '';

        return `
        <div class="enc-recap-gallery__cell-wrap">
            <button type="button" class="enc-recap-gallery__cell" data-page-index="${i}" title="Voir la page ${p.page_number}">
                <img src="${escapeAttr(p.file_path)}" alt="Page ${p.page_number}" loading="lazy">
            </button>
            ${deleteBtn}
        </div>`;
    }).join('');
    const moreCell = sortedPages.length > 6
        ? `<div class="enc-recap-gallery__cell-wrap">
            <button type="button" class="enc-recap-gallery__cell enc-recap-gallery__cell--more" data-page-index="6" title="Voir les autres pages">
                <span>+${sortedPages.length - 6}</span>
            </button>
           </div>`
        : '';
    const emptyGallery = pageCount === 0
        ? '<div class="enc-recap-gallery__empty">Aucune page scannée</div>'
        : '';

    const editActions = isComplete
        ? ''
        : `
            <div class="enc-recap-edit-actions" role="group" aria-label="Modifier l'encodage">
                <span class="enc-recap-edit-actions__label">Modifier avant validation :</span>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="1">
                    <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon> Pages scannées
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="3">
                    <iconify-icon icon="solar:user-bold-duotone"></iconify-icon> Client
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-step="4">
                    <iconify-icon icon="solar:document-bold-duotone"></iconify-icon> Document
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
                        <p class="enc-recap-profile__name">${escapeHtml(client.nom_complet || '—')}</p>
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
                            ${recapInfoItem('solar:gallery-bold-duotone', 'Pages scannées', String(pageCount))}
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
                        <h5 class="enc-recap-tile__title">Pages scannées</h5>
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
        const byId = new Map(scannedPages.filter((p) => p.id_page).map((p) => [p.id_page, p]));
        pages.forEach((p) => {
            if (p.id_page && byId.has(p.id_page)) {
                const local = byId.get(p.id_page);
                local.page_number = p.page_number;
                if (p.file_path) {
                    local.image = p.file_path;
                }
                local.ocrText = p.ocr_text ?? local.ocrText;
            }
        });
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
    const missingDoc = !enc.id_doc;
    const missingClient = !enc.id_client;
    const cannotFinalize = !isComplete && (missingDoc || missingClient || pageCount < 1 || scanPagesDirty);
    submitBtn.disabled = isComplete || cannotFinalize;
    if (cannotFinalize && !isComplete) {
        submitBtn.title = scanPagesDirty
            ? 'Pages modifiées : repassez par l\'étape scan (Suivant).'
            : missingDoc
                ? 'Enregistrez le type de document (étape 4).'
                : missingClient
                    ? 'Associez un client (étape 3).'
                    : 'Au moins une page scannée requise.';
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
    if (!wizard) return;

    if (!canNavigateToStep(stepNumber)) {
        iziToast.warning({ message: getStepBlockedMessage(stepNumber) });

        return;
    }

    if (!isEncodageEditable() && stepNumber < 5) {
        iziToast.info({
            message: 'Encodage finalisé : les modifications ne sont plus possibles.',
        });

        return;
    }

    wizard.querySelectorAll('.step').forEach((step) => step.classList.remove('active'));

    const target = wizard.querySelector(`#step${stepNumber}`);
    if (!target) {
        console.error('Étape introuvable:', stepNumber);
        return;
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
        if (!document.getElementById('video')?.srcObject) {
            initializeCamera();
        }
    }

    if (stepNumber === 3) {
        syncStep3Cameras();
    } else {
        stopFaceCamera();
        stopNewClientCamera();
    }

    if (stepNumber === 4) {
        updateDateFieldsState();
    }

    if (stepNumber === 5 && encodageId) {
        loadRecapitulatif();
    }

    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}
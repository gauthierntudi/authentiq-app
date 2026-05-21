/**
 * Backend Integration pour Scanner de Documents
 * Connecte toutes les fonctionnalités frontend au backend PHP
 * Version: 1.2.0
 */

// Configuration de l'API
const API_URL = 'php/encodageOperation.php';

// Variables globales pour le tracking
//let encodageId = null;
let clientId = null;
let isSubmitting = false;

/**
 * Fonction utilitaire pour les requêtes API
 */
async function apiRequest(action, data, isFormData = false) {
    try {
        const url = `${API_URL}?action=${action}`;
        const options = {
            method: 'POST',
            body: isFormData ? data : new URLSearchParams(data)
        };

        if (!isFormData) {
            options.headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
            };
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (result.status === 'error') {
            throw new Error(result.message);
        }

        return result;
    } catch (error) {
        console.error(`Erreur API ${action}:`, error);
        throw error;
    }
}

/**
 * 1. CHARGER LES TYPES DE DOCUMENTS
 */
async function loadDocumentTypes() {
    try {
        const response = await fetch(`${API_URL}?action=getDocTypes`);
        const result = await response.json();

        if (result.status === 'success') {
            const select = document.getElementById('docType');
            select.innerHTML = '<option value="">Sélectionner...</option>';

            result.docs.forEach(doc => {
                const option = document.createElement('option');
                option.value = doc.id_doc;
                option.textContent = doc.nom_doc;
                option.dataset.type = doc.type_doc;
                option.dataset.montant = doc.montant || '';
                option.dataset.duree = doc.duree || '0';
                select.appendChild(option);
            });

            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.dataset.montant) {
                    document.getElementById('docMontant').value = selectedOption.dataset.montant;
                }
                calculateExpirationDate();
            });
        }
    } catch (error) {
        console.error('Erreur chargement types documents:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Impossible de charger les types de documents'
        });
    }
}

/**
 * 2. SAUVEGARDER LES IMAGES ET OCR
 */
async function saveImagesAndOCR(images, ocrTexts) {
    if (isSubmitting) {
        iziToast.warning({ message: 'Sauvegarde en cours...' });
        return null;
    }

    isSubmitting = true;

    try {
        // Créer FormData pour l'upload
        const formData = new FormData();
        formData.append('action', 'saveImages');
        formData.append('pageCount', images.length);

        if (encodageId) {
            formData.append('encodageId', encodageId);
        }

        // Convertir chaque image base64 en Blob
        for (let i = 0; i < images.length; i++) {
            const blob = await (await fetch(images[i])).blob();
            formData.append(`file_${i}`, blob, `page_${i + 1}.jpg`);
        }

        // Sauvegarder les images
        const result = await apiRequest('saveImages', formData, true);

        if (result.status === 'success') {
            encodageId = result.encodageId;

            iziToast.success({
                title: 'Succès',
                message: `${result.pageCount} page(s) sauvegardée(s)`
            });

            // Sauvegarder les textes OCR si présents
            if (ocrTexts && ocrTexts.length > 0) {
                await saveOCRTexts(encodageId, ocrTexts);
            }

            return encodageId;
        }
    } catch (error) {
        console.error('Erreur sauvegarde images:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Échec de la sauvegarde des images'
        });
        return null;
    } finally {
        isSubmitting = false;
    }
}

/**
 * 3. SAUVEGARDER LES TEXTES OCR
 */
async function saveOCRTexts(encId, texts) {
    try {
        const data = {
            action: 'saveOCR',
            encodageId: encId
        };

        texts.forEach((text, index) => {
            data[`ocrTexts[${index}]`] = text;
        });

        const result = await apiRequest('saveOCR', data);

        if (result.status === 'success') {
            iziToast.success({
                title: 'OCR',
                message: 'Textes OCR sauvegardés'
            });
        }
    } catch (error) {
        console.error('Erreur sauvegarde OCR:', error);
    }
}

/**
 * 4. RECHERCHER DES CLIENTS
 */
async function searchClients(query) {
    try {
        const response = await fetch(`${API_URL}?action=searchClients&q=${encodeURIComponent(query)}`);
        const result = await response.json();

        if (result.status === 'success') {
            return result.clients || [];
        }
        return [];
    } catch (error) {
        console.error('Erreur recherche clients:', error);
        return [];
    }
}

/**
 * 5. SAUVEGARDER LE CLIENT
 */
async function saveClientData(clientFormData, encId) {
    try {
        const data = {
            action: 'saveClient',
            encodageId: encId || encodageId
        };

        // Si c'est un client existant
        if (clientFormData.id_client) {
            data.clientId = clientFormData.id_client;
        } else {
            // Nouveau client
            data.nom_complet = clientFormData.nom_complet;
            data.tel = clientFormData.tel;
            data.email = clientFormData.email || '';
            data.type_piece_identite = clientFormData.type_piece_identite || '';
            data.numero_national = clientFormData.numero_national || '';
        }

        const result = await apiRequest('saveClient', data);

        if (result.status === 'success') {
            clientId = result.clientId;
            iziToast.success({
                title: 'Succès',
                message: 'Client enregistré'
            });
            return clientId;
        }
    } catch (error) {
        console.error('Erreur sauvegarde client:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Échec de l\'enregistrement du client'
        });
        return null;
    }
}

/**
 * 6. SAUVEGARDER LE DOCUMENT
 */
async function saveDocumentData(docFormData, encId) {
    try {
        const data = {
            action: 'saveDocument',
            encodageId: encId || encodageId,
            type: docFormData.type,
            montant: docFormData.montant || '',
            date_emission: docFormData.date_emission || '',
            date_expiration: docFormData.date_expiration || ''
        };

        const result = await apiRequest('saveDocument', data);

        if (result.status === 'success') {
            iziToast.success({
                title: 'Succès',
                message: 'Document enregistré'
            });
            return true;
        }
    } catch (error) {
        console.error('Erreur sauvegarde document:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Échec de l\'enregistrement du document'
        });
        return false;
    }
}

/**
 * 7. RÉCUPÉRER LE RÉCAPITULATIF
 */
async function getRecapitulatif(encId) {
    try {
        const response = await fetch(`${API_URL}?action=getRecap&encodageId=${encId || encodageId}`);
        const result = await response.json();

        if (result.status === 'success') {
            return result;
        }
        return null;
    } catch (error) {
        console.error('Erreur récapitulatif:', error);
        return null;
    }
}

/**
 * 8. FINALISER L'ENCODAGE
 */
async function finalizeEncodageBackend(encId) {
    if (isSubmitting) {
        iziToast.warning({ message: 'Finalisation en cours...' });
        return false;
    }

    isSubmitting = true;

    try {
        const data = {
            action: 'finalize',
            encodageId: encId || encodageId
        };

        const result = await apiRequest('finalize', data);

        if (result.status === 'success') {
            iziToast.success({
                title: 'Succès',
                message: 'Encodage finalisé avec succès!',
                timeout: 3000,
                onClosing: () => {
                    // Réinitialiser et rediriger vers l'accueil
                    encodageId = null;
                    clientId = null;
                    setTimeout(() => {
                        window.location.href = 'accueil';
                    }, 1000);
                }
            });
            return true;
        }
    } catch (error) {
        console.error('Erreur finalisation:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Échec de la finalisation'
        });
        return false;
    } finally {
        isSubmitting = false;
    }
}

/**
 * 9. VÉRIFIER LES ENCODAGES INCOMPLETS
 */
async function checkIncompleteEncodageBackend() {
    try {
        const response = await fetch(`${API_URL}?action=getIncomplete`);
        const result = await response.json();

        if (result.status === 'success' && result.encodage) {
            const enc = result.encodage;

            iziToast.question({
                timeout: false,
                close: false,
                overlay: true,
                displayMode: 'once',
                id: 'question',
                zindex: 999,
                title: 'Encodage Incomplet',
                message: `Vous avez un encodage non terminé (${enc.page_count} page(s)). Voulez-vous le reprendre ?`,
                position: 'center',
                buttons: [
                    ['<button><b>Reprendre</b></button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                        restoreIncompleteEncodage(result);
                    }, true],
                    ['<button>Nouveau</button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    }],
                ],
            });
        }
    } catch (error) {
        console.error('Erreur vérification encodage incomplet:', error);
    }
}

/**
 * 10. RESTAURER UN ENCODAGE INCOMPLET
 */
function restoreIncompleteEncodage(data) {
    const enc = data.encodage;
    const pages = data.pages || [];

    encodageId = enc.id_encodage;
    clientId = enc.id_client;

    // Restaurer les images
    if (pages.length > 0) {
        capturedImages = pages.map(p => p.file_path);
        ocrTexts = pages.map(p => p.ocr_text || '');
    }

    // Restaurer les données client si présentes
    if (enc.id_client) {
        // Charger les données client depuis la BD
        loadClientData(enc.id_client);
    }

    // Restaurer les données document si présentes
    if (enc.id_doc) {
        document.getElementById('docType').value = enc.id_doc;
        document.getElementById('docMontant').value = enc.montant || '';
        document.getElementById('docDateEmission').value = enc.date_emission || '';
        document.getElementById('docDateExpiration').value = enc.date_expiration || '';
    }

    // Aller à l'étape appropriée
    if (!enc.id_client) {
        goToStep(3); // Pas de client, aller à l'étape client
    } else if (!enc.id_doc) {
        goToStep(4); // Pas de document, aller à l'étape document
    } else {
        goToStep(5); // Tout est là, aller au récapitulatif
    }

    iziToast.info({
        message: 'Encodage restauré. Vous pouvez continuer où vous vous êtes arrêté.',
        position: 'topRight',
        timeout: 5000
    });
}

/**
 * 11. CHARGER LES DONNÉES D'UN CLIENT
 */
async function loadClientData(cId) {
    try {
        // Cette fonction devrait être appelée lors de la restauration
        // Pour l'instant, on va juste stocker l'ID
        clientId = cId;
    } catch (error) {
        console.error('Erreur chargement client:', error);
    }
}

/**
 * 12. SETUP DE LA RECHERCHE DE CLIENTS
 */
function setupClientSearch() {
    const searchInput = document.getElementById('searchClient');
    const clientSelect = document.getElementById('clientSelect');

    if (!searchInput || !clientSelect) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            clientSelect.innerHTML = '<option>Tapez au moins 2 caractères...</option>';
            return;
        }

        searchTimeout = setTimeout(async () => {
            const clients = await searchClients(query);

            clientSelect.innerHTML = '';

            if (clients.length === 0) {
                clientSelect.innerHTML = '<option>Aucun client trouvé</option>';
            } else {
                clients.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.id_client;
                    option.textContent = `${client.nom_complet} - ${client.tel}`;
                    option.dataset.client = JSON.stringify(client);
                    clientSelect.appendChild(option);
                });
            }
        }, 500);
    });

    clientSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.dataset.client) {
            const client = JSON.parse(option.dataset.client);
            // Remplir le formulaire avec les données du client
            selectExistingClient(client);
        }
    });
}

/**
 * 13. SÉLECTIONNER UN CLIENT EXISTANT
 */
function selectExistingClient(client) {
    document.getElementById('clientNom').value = client.nom_complet;
    document.getElementById('clientTel').value = client.tel;
    document.getElementById('clientEmail').value = client.email || '';
    document.getElementById('clientTypePiece').value = client.type_piece_identite || '';
    document.getElementById('clientNumeroPiece').value = client.numero_national || '';

    // Stocker l'ID du client
    clientId = client.id_client;

    iziToast.info({
        message: `Client "${client.nom_complet}" sélectionné`,
        position: 'topRight'
    });
}

/**
 * 14. STATISTIQUES EN TEMPS RÉEL
 */
function updateStatistics() {
    const stats = {
        totalPages: capturedImages.length,
        totalCharacters: ocrTexts.reduce((sum, text) => sum + text.length, 0),
        hasClient: !!clientData.nom_complet,
        hasDocument: !!documentData.type,
        progress: 0
    };

    // Calculer la progression
    let completed = 0;
    if (stats.totalPages > 0) completed += 20;
    if (stats.totalCharacters > 0) completed += 20;
    if (stats.hasClient) completed += 30;
    if (stats.hasDocument) completed += 30;
    stats.progress = completed;

    // Afficher les statistiques
    updateProgressBar(stats.progress);

    return stats;
}

/**
 * 15. METTRE À JOUR LA BARRE DE PROGRESSION
 */
function updateProgressBar(percentage) {
    const progressBar = document.getElementById('stepProgress');
    if (progressBar) {
        progressBar.style.width = percentage + '%';
    }
}

/**
 * 16. VALIDATION DES DONNÉES AVANT FINALISATION
 */
function validateBeforeFinalize() {
    const errors = [];

    if (capturedImages.length === 0) {
        errors.push('Aucune page scannée');
    }

    if (!clientData.nom_complet || !clientData.tel) {
        errors.push('Informations client incomplètes');
    }

    if (!documentData.type) {
        errors.push('Type de document non sélectionné');
    }

    if (errors.length > 0) {
        iziToast.warning({
            title: 'Validation',
            message: errors.join('<br>'),
            position: 'topCenter',
            timeout: 5000
        });
        return false;
    }

    return true;
}

/**
 * INITIALISATION AU CHARGEMENT
 */
document.addEventListener('DOMContentLoaded', function() {
    // Charger les types de documents
    loadDocumentTypes();

    // Setup recherche clients
    setupClientSearch();

    // Vérifier les encodages incomplets
    setTimeout(() => {
        checkIncompleteEncodageBackend();
    }, 1000);
});

// Exporter les fonctions pour utilisation globale
window.backendIntegration = {
    apiRequest,
    saveImagesAndOCR,
    saveOCRTexts,
    searchClients,
    saveClientData,
    saveDocumentData,
    getRecapitulatif,
    finalizeEncodageBackend,
    checkIncompleteEncodageBackend,
    validateBeforeFinalize,
    updateStatistics,
    getEncodageId: () => encodageId,
    getClientId: () => clientId
};
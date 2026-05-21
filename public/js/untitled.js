/**
 * Gestion de la liste des encodages
 * Affichage, filtrage, continuation et suppression
 */

const API_URL = 'php/encodageOperation.php';
let allEncodings = [];
let currentFilter = 'all';

// Charger les encodages au démarrage
document.addEventListener('DOMContentLoaded', function() {
    loadEncodings();
});

/**
 * Démarrer un nouveau scan
 */
function startNewScan() {
    window.location.href = 'encode-complete.html';
}

/**
 * Charger tous les encodages de l'utilisateur
 */
async function loadEncodings() {
    try {
        const response = await fetch(`${API_URL}?action=getUserEncodings`);
        const result = await response.json();

        if (result.status === 'success') {
            allEncodings = result.encodings;
            updateStats();
            displayEncodings(allEncodings);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Erreur chargement encodages:', error);
        document.getElementById('encodingsContainer').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Erreur de chargement</h3>
                <p>${error.message}</p>
            </div>
        `;
    }
}

/**
 * Mettre à jour les statistiques
 */
function updateStats() {
    const total = allEncodings.length;
    const incomplete = allEncodings.filter(e => e.status === 'incomplet').length;
    const complete = allEncodings.filter(e => e.status === 'complet').length;

    document.getElementById('statTotal').textContent = total;
    document.getElementById('statIncomplete').textContent = incomplete;
    document.getElementById('statComplete').textContent = complete;
}

/**
 * Filtrer les encodages
 */
function filterEncodings(filter) {
    currentFilter = filter;

    // Update button states
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // Filter encodings
    let filtered = allEncodings;
    if (filter === 'incomplete') {
        filtered = allEncodings.filter(e => e.status === 'incomplet');
    } else if (filter === 'complete') {
        filtered = allEncodings.filter(e => e.status === 'complet');
    }

    displayEncodings(filtered);
}

/**
 * Afficher les encodages
 */
function displayEncodings(encodings) {
    const container = document.getElementById('encodingsContainer');

    if (encodings.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucun encodage</h3>
                <p>${currentFilter === 'all' ? 'Démarrez un nouveau scan pour commencer' :
                    currentFilter === 'incomplete' ? 'Aucun encodage incomplet' :
                    'Aucun encodage terminé'}</p>
            </div>
        `;
        return;
    }

    container.innerHTML = '<div class="encodings-grid"></div>';
    const grid = container.querySelector('.encodings-grid');

    encodings.forEach(encoding => {
        const card = createEncodingCard(encoding);
        grid.appendChild(card);
    });
}

/**
 * Créer une carte d'encodage
 */
function createEncodingCard(encoding) {
    const div = document.createElement('div');
    div.className = 'encoding-card';

    const statusClass = encoding.status === 'complet' ? 'status-complete' : 'status-incomplete';
    const statusText = encoding.status === 'complet' ? 'Complet' : 'Incomplet';

    const dateCreation = new Date(encoding.date_creation).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    const dateModif = encoding.date_modification ?
        new Date(encoding.date_modification).toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : '-';

    div.innerHTML = `
        <div class="encoding-header">
            <div class="encoding-id">#${encoding.id_encodage}</div>
            <div class="encoding-status ${statusClass}">${statusText}</div>
        </div>
        <div class="encoding-info">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-calendar"></i> Créé le</span>
                <span class="info-value">${dateCreation}</span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-clock"></i> Modifié le</span>
                <span class="info-value">${dateModif}</span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-file-image"></i> Pages</span>
                <span class="info-value">${encoding.nb_pages || 0}</span>
            </div>
            ${encoding.client_nom ? `
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user"></i> Client</span>
                    <span class="info-value">${encoding.client_nom}</span>
                </div>
            ` : ''}
            ${encoding.type_doc ? `
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-file-alt"></i> Document</span>
                    <span class="info-value">${encoding.type_doc}</span>
                </div>
            ` : ''}
        </div>
        <div class="encoding-actions">
            ${encoding.status === 'incomplet' ? `
                <button class="action-btn continue-btn" onclick="continueEncoding(${encoding.id_encodage})">
                    <i class="fas fa-play"></i> Continuer
                </button>
            ` : `
                <button class="action-btn view-btn" onclick="viewEncoding(${encoding.id_encodage})">
                    <i class="fas fa-eye"></i> Voir
                </button>
            `}
            <button class="action-btn delete-btn" onclick="confirmDelete(${encoding.id_encodage})">
                <i class="fas fa-trash"></i> Supprimer
            </button>
        </div>
    `;

    return div;
}

/**
 * Continuer un encodage incomplet
 */
async function continueEncoding(encodageId) {
    try {
        const response = await fetch(`${API_URL}?action=getEncodageDetails&id=${encodageId}`);
        const result = await response.json();

        if (result.status === 'success') {
            // Stocker les données dans sessionStorage
            sessionStorage.setItem('continueEncoding', JSON.stringify({
                encodageId: encodageId,
                data: result.data
            }));

            // Rediriger vers la page d'encodage
            window.location.href = 'encode-complete.html';
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Erreur:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Impossible de charger l\'encodage'
        });
    }
}

/**
 * Voir les détails d'un encodage complet
 */
function viewEncoding(encodageId) {
    window.location.href = `view-encoding.html?id=${encodageId}`;
}

/**
 * Confirmer la suppression
 */
function confirmDelete(encodageId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet encodage ?\n\nCette action est irréversible.')) {
        deleteEncoding(encodageId);
    }
}

/**
 * Supprimer un encodage
 */
async function deleteEncoding(encodageId) {
    try {
        const response = await fetch(`${API_URL}?action=deleteEncoding`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `encodageId=${encodageId}`
        });

        const result = await response.json();

        if (result.status === 'success') {
            iziToast.success({
                title: 'Succès',
                message: 'Encodage supprimé avec succès'
            });

            // Recharger la liste
            loadEncodings();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Erreur suppression:', error);
        iziToast.error({
            title: 'Erreur',
            message: 'Impossible de supprimer l\'encodage'
        });
    }
}

/**
 * Gestion de la liste des encodages
 * Affichage, filtrage, continuation et suppression
 */

const API_URL = 'php/encodageOperation.php';
let allEncodings = [];
let dataTable = null;

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

            // Filtrer les encodages selon les règles
            const filteredEncodings = filterEncodingsByDate(allEncodings);

            // Mettre à jour les stats avec les encodages filtrés
            updateStats(allEncodings, filteredEncodings);
            displayEncodings(filteredEncodings);
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
 * Filtrer les encodages selon les règles de temps
 * - Complets : seulement les dernières 24h
 * - Incomplets : seulement les derniers 7 jours
 */
function filterEncodingsByDate(encodings) {
    const now = new Date();
    const last24Hours = new Date(now.getTime() - (24 * 60 * 60 * 1000));
    const last7Days = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000));

    return encodings.filter(encoding => {
        const dateModification = encoding.date_modification
            ? new Date(encoding.date_modification)
            : new Date(encoding.date_creation);

        // Normaliser le statut
        const status = (encoding.status || '').toLowerCase().trim();
        const isComplete = status === 'complet' || status === 'complete' || status === 'terminé' || status === 'termine';

        if (isComplete) {
            // Encodages complets : afficher seulement ceux des dernières 24h
            return dateModification >= last24Hours;
        } else {
            // Encodages incomplets : afficher seulement ceux des derniers 7 jours
            return dateModification >= last7Days;
        }
    });
}

/**
 * Mettre à jour les statistiques
 * Compte les incomplets et complets dans les encodages filtrés
 */
function updateStats(allEncodings, filteredEncodings) {
    // Compter dans les encodages filtrés affichés
    const total = filteredEncodings.length;

    // Normaliser le statut et compter (gérer différentes variantes)
    const incomplete = filteredEncodings.filter(e => {
        const status = (e.status || '').toLowerCase().trim();
        return status === 'incomplet' || status === 'incomplete' || status === 'en cours';
    }).length;

    const complete = filteredEncodings.filter(e => {
        const status = (e.status || '').toLowerCase().trim();
        return status === 'complet' || status === 'complete' || status === 'terminé' || status === 'termine';
    }).length;

    console.log('Stats Debug:', {
        total,
        incomplete,
        complete,
        filteredEncodings: filteredEncodings.map(e => ({
            id: e.id_encodage,
            status: e.status,
            statusLower: (e.status || '').toLowerCase().trim()
        }))
    });

    document.getElementById('statTotal').textContent = total;
    document.getElementById('statIncomplete').textContent = incomplete;
    document.getElementById('statComplete').textContent = complete;
}

/**
 * Afficher les encodages avec DataTable
 */
function displayEncodings(encodings) {
    const container = document.getElementById('encodingsContainer');

    if (encodings.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucun encodage</h3>
                <p>Démarrez un nouveau scan pour commencer</p>
            </div>
        `;
        return;
    }

    // Détruire l'ancienne instance de DataTable si elle existe
    if (dataTable) {
        dataTable.destroy();
    }

    // Créer le tableau HTML
    container.innerHTML = `
        <table id="encodingsTable" class="display responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Statut</th>
                    <th>Client</th>
                    <th>Type Document</th>
                    <th>Pages</th>
                    <th>Date Création</th>
                    <th>Date Modification</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    `;

    // Préparer les données pour DataTable
    const tableData = encodings.map(encoding => {
        return [
            encoding.id_encodage,
            formatStatus(encoding.status),
            encoding.client_nom || '-',
            encoding.type_doc || '-',
            encoding.nb_pages || 0,
            formatDate(encoding.date_creation),
            formatDate(encoding.date_modification),
            formatActions(encoding)
        ];
    });

    // Initialiser DataTable
    dataTable = $('#encodingsTable').DataTable({
        data: tableData,
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
        },
        order: [[6, 'desc']], // Trier par date de modification décroissante
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
        columnDefs: [
            {
                targets: 0, // ID
                width: '60px'
            },
            {
                targets: 1, // Statut
                width: '100px'
            },
            {
                targets: 4, // Pages
                width: '70px',
                className: 'dt-center'
            },
            {
                targets: 7, // Actions
                orderable: false,
                searchable: false,
                width: '130px',
                className: 'actions-cell'
            }
        ],
        drawCallback: function() {
            // Réattacher les événements après chaque redessin
            attachActionEvents();
        }
    });
}

/**
 * Formater le statut avec badge
 */
function formatStatus(status) {
    const normalizedStatus = (status || '').toLowerCase().trim();
    const isComplete = normalizedStatus === 'complet' || normalizedStatus === 'complete' || normalizedStatus === 'terminé' || normalizedStatus === 'termine';

    const statusClass = isComplete ? 'status-complete' : 'status-incomplete';
    const statusText = isComplete ? 'Complet' : 'Incomplet';
    return `<span class="status-badge ${statusClass}">${statusText}</span>`;
}

/**
 * Formater une date
 */
function formatDate(dateString) {
    if (!dateString) return '-';

    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formater les boutons d'actions (circulaires avec icônes)
 */
function formatActions(encoding) {
    const normalizedStatus = (encoding.status || '').toLowerCase().trim();
    const isComplete = normalizedStatus === 'complet' || normalizedStatus === 'complete' || normalizedStatus === 'terminé' || normalizedStatus === 'termine';

    // Les encodages complets n'ont pas de boutons d'actions
    if (isComplete) {
        return '<span class="text-muted">-</span>';
    }

    const buttons = [];

    // Encodages incomplets : boutons "Continuer" et "Supprimer"
    buttons.push(`
        <button class="action-btn continue"
                data-id="${encoding.id_encodage}"
                data-action="continue"
                title="Continuer l'encodage">
            <i class="fas fa-play"></i>
        </button>
    `);

    buttons.push(`
        <button class="action-btn delete"
                data-id="${encoding.id_encodage}"
                data-action="delete"
                title="Supprimer">
            <i class="fas fa-trash"></i>
        </button>
    `);

    return buttons.join('');
}

/**
 * Attacher les événements aux boutons d'actions
 */
function attachActionEvents() {
    // Boutons "Continuer"
    document.querySelectorAll('.action-btn.continue').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const encodageId = this.getAttribute('data-id');
            continueEncoding(encodageId);
        });
    });

    // Boutons "Supprimer"
    document.querySelectorAll('.action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const encodageId = this.getAttribute('data-id');
            confirmDelete(encodageId);
        });
    });
}

/**
 * Continuer un encodage (incomplet ou complet)
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
 * Confirmer la suppression
 */
function confirmDelete(encodageId) {
    iziToast.question({
        timeout: 20000,
        close: false,
        overlay: true,
        displayMode: 'once',
        id: 'question',
        zindex: 999,
        title: 'Confirmation',
        message: 'Êtes-vous sûr de vouloir supprimer cet encodage ? Cette action est irréversible.',
        position: 'center',
        buttons: [
            ['<button><b>Oui, supprimer</b></button>', function (instance, toast) {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                deleteEncoding(encodageId);
            }, true],
            ['<button>Annuler</button>', function (instance, toast) {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
            }]
        ]
    });
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
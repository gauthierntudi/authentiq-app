/**
 * Modal doublon client (photo Rekognition, tel, email, n° pièce).
 */
(function (global) {
    const FIELD_LABELS = {
        photo: 'Photo / visage',
        tel: 'Téléphone',
        email: 'E-mail',
        numero_piece: 'Numéro de pièce d\'identité',
    };

    let modalInstance = null;
    let useExistingHandler = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    function getModal() {
        const el = document.getElementById('clientDuplicateModal');
        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(el);
        }
        return modalInstance;
    }

    function renderClientCard(client, conflict) {
        if (!client) {
            return '<p class="text-muted mb-0">Aucun détail client disponible.</p>';
        }

        const field = conflict?.field || '';
        const fieldLabel = FIELD_LABELS[field] || 'Information';
        const similarity = client.similarity != null
            ? `<span class="client-duplicate-modal__similarity">${Math.round(client.similarity)} % similarité</span>`
            : '';

        const pieceLabel = client.type_piece_identite || '—';
        const pieceNum = client.type_piece_identite === 'CNI'
            ? (client.numero_national || '—')
            : client.type_piece_identite === 'Passeport'
                ? (client.numero_passeport || '—')
                : (client.numero_national || client.numero_passeport || '—');

        const statusTag = client.is_active
            ? '<span class="client-duplicate-modal__tag client-duplicate-modal__tag--active">Actif</span>'
            : '<span class="client-duplicate-modal__tag client-duplicate-modal__tag--pending">Inactif / OTP</span>';

        return `
            <div class="client-duplicate-modal__card">
                <div class="client-duplicate-modal__card-head">
                    <img src="${escapeAttr(client.photo_url || 'assets/images/user.jpg')}" alt="" class="client-duplicate-modal__photo" width="72" height="72" loading="lazy">
                    <div>
                        <p class="client-duplicate-modal__conflict-type">Doublon : ${escapeHtml(fieldLabel)} ${similarity}</p>
                        <h6 class="client-duplicate-modal__name">${escapeHtml(client.nom_complet || '—')}</h6>
                        ${statusTag}
                    </div>
                </div>
                <ul class="client-duplicate-modal__details list-unstyled mb-0">
                    <li><iconify-icon icon="solar:phone-bold-duotone"></iconify-icon><span>${escapeHtml(client.tel || '—')}</span></li>
                    <li><iconify-icon icon="solar:letter-bold-duotone"></iconify-icon><span>${escapeHtml(client.email || '—')}</span></li>
                    <li><iconify-icon icon="solar:card-bold-duotone"></iconify-icon><span>${escapeHtml(pieceLabel)} — ${escapeHtml(pieceNum)}</span></li>
                    <li><iconify-icon icon="solar:map-point-bold-duotone"></iconify-icon><span>${escapeHtml([client.nom_ville, client.nom_province].filter(Boolean).join(', ') || '—')}</span></li>
                </ul>
            </div>
        `;
    }

    /**
     * @param {{ allowed?: boolean, conflict?: object, client?: object, message?: string }} payload
     * @param {{ context?: 'encodage'|'admin', onUseExisting?: (client: object) => void }} options
     */
    function show(payload, options = {}) {
        const modal = getModal();
        if (!modal) {
            global.iziToast?.error?.({ title: 'Doublon', message: payload?.message || 'Client déjà enregistré.' });
            return;
        }

        const conflict = payload.conflict || {};
        const message = payload.message || conflict.message || 'Un client existant correspond déjà à ces informations.';
        const client = payload.client;

        const alertEl = document.getElementById('clientDuplicateAlert');
        const bodyEl = document.getElementById('clientDuplicateModalBody');
        const subtitleEl = document.getElementById('clientDuplicateModalSubtitle');
        const useBtn = document.getElementById('clientDuplicateUseExistingBtn');

        if (alertEl) {
            alertEl.textContent = message;
        }
        if (subtitleEl) {
            subtitleEl.textContent = 'La création d\'un nouveau client est refusée (politique anti-doublon).';
        }
        if (bodyEl) {
            bodyEl.innerHTML = renderClientCard(client, conflict);
        }

        useExistingHandler = null;
        if (useBtn) {
            const showUse = options.context === 'encodage' && typeof options.onUseExisting === 'function' && client;
            useBtn.classList.toggle('d-none', !showUse);
            if (showUse) {
                useExistingHandler = () => {
                    options.onUseExisting(client);
                    modal.hide();
                };
            }
        }

        global.iziToast?.warning?.({
            title: 'Doublon client',
            message,
            timeout: 5000,
        });

        modal.show();
    }

    function showFromApiResponse(data, options = {}) {
        if (!data || (data.allowed !== false && !data.duplicate)) {
            return false;
        }
        show({
            allowed: false,
            conflict: data.conflict,
            client: data.client,
            message: data.message,
        }, options);
        return true;
    }

    document.getElementById('clientDuplicateUseExistingBtn')?.addEventListener('click', () => {
        if (useExistingHandler) {
            useExistingHandler();
        }
    });

    global.AuthentiqClientDuplicate = {
        show,
        showFromApiResponse,
    };
})(typeof window !== 'undefined' ? window : globalThis);

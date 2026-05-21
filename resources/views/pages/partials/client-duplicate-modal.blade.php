{{-- Doublon client détecté (encodage + gestion clients) --}}
<div class="modal fade client-duplicate-modal" id="clientDuplicateModal" tabindex="-1" aria-labelledby="clientDuplicateModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content client-duplicate-modal__content">
            <div class="modal-header client-duplicate-modal__header">
                <div>
                    <h5 class="modal-title" id="clientDuplicateModalTitle">Client déjà enregistré</h5>
                    <p class="client-duplicate-modal__subtitle mb-0" id="clientDuplicateModalSubtitle">Création refusée — doublon détecté</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body client-duplicate-modal__body">
                <div class="alert client-duplicate-modal__alert mb-3" role="alert" id="clientDuplicateAlert"></div>
                <div id="clientDuplicateModalBody"></div>
            </div>
            <div class="modal-footer client-duplicate-modal__footer flex-wrap">
                <button type="button" class="btn btn-light fw-semibold" style="border-radius:12px" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary fw-semibold d-none" style="border-radius:12px" id="clientDuplicateUseExistingBtn">
                    <iconify-icon icon="solar:user-check-bold-duotone"></iconify-icon>
                    <span>Utiliser ce client existant</span>
                </button>
            </div>
        </div>
    </div>
</div>

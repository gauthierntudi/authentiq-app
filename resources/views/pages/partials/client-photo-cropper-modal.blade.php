{{-- Modal rogner photo client (encodage + gestion-clients) --}}
<div class="modal fade" id="{{ $modalId ?? 'clientCropperModal' }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h5 class="modal-title mb-0">
                    Rogner la photo
                    <br><span class="fw-normal" style="font-size:.8em;">Cadrage circulaire du profil client</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="cropper-profile-wrap">
                            <img id="{{ $imageId ?? 'clientCropperImage' }}" src="" alt="Image à rogner">
                        </div>
                        <div class="cropper-profile-toolbar">
                            <button type="button" class="btn-tool" data-crop-action="zoom-in">
                                <iconify-icon icon="solar:magnifer-zoom-in-bold-duotone"></iconify-icon> Zoom +
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="zoom-out">
                                <iconify-icon icon="solar:magnifer-zoom-out-bold-duotone"></iconify-icon> Zoom −
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="rotate-left">
                                <iconify-icon icon="solar:restart-bold-duotone"></iconify-icon> Gauche
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="rotate-right">
                                <iconify-icon icon="solar:restart-bold-duotone" style="transform:scaleX(-1)"></iconify-icon> Droite
                            </button>
                            <button type="button" class="btn-tool" data-crop-action="reset">
                                <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon> Réinitialiser
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
                <button type="button" id="{{ $validateId ?? 'clientCropperValidate' }}" class="btn btn-primary btn-lg fw-semibold" style="border-radius:12px">
                    <iconify-icon icon="solar:check-circle-bold-duotone" class="me-1"></iconify-icon>
                    Valider le rognage
                </button>
            </div>
        </div>
    </div>
</div>

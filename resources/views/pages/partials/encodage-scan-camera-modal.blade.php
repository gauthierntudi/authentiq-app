{{-- Modal scan document — vue portrait agrandie --}}
<div class="modal fade enc-scan-camera-modal" id="encScanCameraModal" tabindex="-1" aria-labelledby="encScanCameraModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down enc-scan-camera-modal__dialog">
        <div class="modal-content enc-scan-camera-modal__content">
            <div class="modal-header enc-scan-camera-modal__header">
                <div>
                    <h5 class="modal-title" id="encScanCameraModalTitle">Scan document</h5>
                    <p class="enc-scan-camera-modal__subtitle mb-0">Mode portrait — cadrez le document à plat</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body enc-scan-camera-modal__body">
                <div class="enc-scan-camera-modal__viewport" aria-label="Vue caméra document">
                    <video id="videoScanModal" autoplay playsinline muted></video>
                    <span class="enc-scan-camera-modal__frame" aria-hidden="true"></span>
                </div>
                <p class="enc-scan-camera-modal__hint">
                    <iconify-icon icon="solar:smartphone-rotate-angle-bold-duotone"></iconify-icon>
                    Tenez le téléphone en portrait, document bien éclairé et entier dans le cadre.
                </p>
            </div>
            <div class="modal-footer enc-scan-camera-modal__footer">
                <button type="button" id="captureButtonModal" class="btn btn-primary fw-semibold btn-lg w-100" style="border-radius:12px">
                    <iconify-icon icon="solar:camera-bold-duotone"></iconify-icon>
                    <span>Capturer le document</span>
                </button>
                <button type="button" class="btn btn-light fw-semibold w-100" style="border-radius:12px" data-bs-dismiss="modal">
                    <iconify-icon icon="solar:minimize-square-bold-duotone"></iconify-icon>
                    <span>Réduire la vue</span>
                </button>
            </div>
        </div>
    </div>
</div>

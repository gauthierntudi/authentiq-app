{{-- Visionneuse pages scannées (récapitulatif encodage) --}}
<div class="modal fade enc-pages-viewer-modal" id="encPagesViewerModal" tabindex="-1" aria-labelledby="encPagesViewerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content enc-pages-viewer">
            <div class="modal-header enc-pages-viewer__header">
                <div>
                    <h5 class="modal-title enc-pages-viewer__title" id="encPagesViewerTitle">Page scannée</h5>
                    <p class="enc-pages-viewer__counter mb-0" id="encPagesViewerCounter">Page 1 sur 1</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body enc-pages-viewer__body">
                <button type="button" class="enc-pages-viewer__nav enc-pages-viewer__nav--prev" id="encPagesViewerPrev" aria-label="Page précédente">
                    <iconify-icon icon="solar:alt-arrow-left-bold"></iconify-icon>
                </button>
                <div class="enc-pages-viewer__stage">
                    <img src="" alt="" class="enc-pages-viewer__img" id="encPagesViewerImg">
                </div>
                <button type="button" class="enc-pages-viewer__nav enc-pages-viewer__nav--next" id="encPagesViewerNext" aria-label="Page suivante">
                    <iconify-icon icon="solar:alt-arrow-right-bold"></iconify-icon>
                </button>
            </div>
        </div>
    </div>
</div>

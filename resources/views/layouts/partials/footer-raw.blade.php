@unless (request()->routeIs('encodage.index'))
<!-- Bouton flottant -->
<div class="fab-container" id="fabContainer">
  <button type="button" class="fab-main" id="fabMainBtn" aria-expanded="false" aria-controls="fabSecondaryActions" aria-label="Ouvrir le menu d'actions">
    <iconify-icon icon="solar:add-circle-line-duotone"></iconify-icon>
  </button>

  <div id="fabSecondaryActions" class="fab-secondary-group" role="group" aria-label="Actions rapides">
    <a href="{{ url('/encodage-document') }}" class="fab-secondary" data-tooltip="Encoder">
      <iconify-icon icon="solar:printer-minimalistic-bold-duotone"></iconify-icon>
    </a>
    <a href="{{ url('/gestion-clients') }}" class="fab-secondary" data-tooltip="Nv. Client" data-fab-new-client>
      <iconify-icon icon="solar:user-plus-bold-duotone"></iconify-icon>
    </a>
  </div>
</div>
<script>
(function () {
    const container = document.getElementById('fabContainer');
    const mainBtn = document.getElementById('fabMainBtn');
    if (!container || !mainBtn) return;

    const closeFab = () => {
        container.classList.remove('is-open');
        mainBtn.setAttribute('aria-expanded', 'false');
    };

    mainBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const open = !container.classList.contains('is-open');
        container.classList.toggle('is-open', open);
        mainBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    container.querySelectorAll('.fab-secondary').forEach((link) => {
        link.addEventListener('click', () => closeFab());
        if (link.hasAttribute('data-fab-new-client')) {
            link.addEventListener('click', () => {
                try { sessionStorage.setItem('openNewClientModal', '1'); } catch (err) { /* ignore */ }
            });
        }
    });

    document.addEventListener('click', (e) => {
        if (!container.classList.contains('is-open')) return;
        if (container.contains(e.target)) return;
        closeFab();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeFab();
    });
})();
</script>
@endunless

<footer class="footer" style="border: 0px;">
    <div class="page-container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start text-muted">
                <script>document.write(new Date().getFullYear())</script> © Authentiq 
            </div>
            <div class="col-md-6">
                <div class="text-md-end footer-links d-none d-md-block">
                    <a href="javascript: void(0);" class="text-muted">version <span class="fw-bold" style="color:#0eedee;">2.0</span></a>
                </div>
            </div>
        </div>
    </div>
</footer>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const settingsModalEl = document.getElementById('settingsModal');
    const settingsForm = document.getElementById('settingsForm');
    const openSettingsBtn = document.getElementById('openSettingsBtn');

    if (!settingsModalEl || !settingsForm) return;

    const settingsModal = new bootstrap.Modal(settingsModalEl);

    settingsForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            iziToast.error({ title: 'Erreur', message: 'Veuillez remplir tous les champs.' });
            return;
        }

        if (newPassword !== confirmPassword) {
            iziToast.error({ title: 'Erreur', message: 'Les mots de passe ne correspondent pas.' });
            return;
        }

        const formData = new FormData();
        formData.append('currentPassword', currentPassword);
        formData.append('newPassword', newPassword);
        formData.append('confirmPassword', confirmPassword);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const saveBtn = document.getElementById('saveSettingsBtn');
        const defaultLabel = saveBtn?.innerHTML;

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
        }

        fetch('/api/profile/update', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData,
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    iziToast.success({ title: 'Succès', message: data.message });
                    settingsForm.reset();
                    settingsModal.hide();
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                }
            })
            .catch(() => {
                iziToast.error({ title: 'Erreur', message: 'Une erreur s\'est produite.' });
            })
            .finally(() => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = defaultLabel;
                }
            });
    });

    openSettingsBtn?.addEventListener('click', function () {
        settingsForm.reset();
        settingsModal.show();
    });
});
</script>

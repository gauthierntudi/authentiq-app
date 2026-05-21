document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = () => ({ 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' });

    const profile = window.PROFILE_INITIAL || {};
    const selectProvince = document.getElementById('profileProvince');
    const selectVille = document.getElementById('profileVille');
    const selectCommune = document.getElementById('profileCommune');
    const profileAffectation = document.getElementById('profileAffectation');
    const photoInput = document.getElementById('profilePhotoInput');
    const photoDropZone = document.getElementById('photoDropZoneProfile');
    const previewPhoto = document.getElementById('previewPhotoProfile');
    const cropperModalEl = document.getElementById('cropperModalProfile');
    const cropperModal = new bootstrap.Modal(cropperModalEl);
    const cropperImage = document.getElementById('cropperImageProfile');
    const cropperValidate = document.getElementById('cropperValidateProfile');

    let cropper = null;
    let croppedBlob = null;
    let cropperObjectUrl = null;
    const cropperPreviewEl = document.querySelector('.cropper-preview-circle');

    function updateGeoChip(chipId, textId, label, value) {
        const chip = document.getElementById(chipId);
        const text = document.getElementById(textId);
        if (text) text.textContent = value || label;
        if (chip) chip.classList.toggle('profile-geo-chip--empty', !value);
    }

    function updateDisplayCard(data) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
        set('profileDisplayName', data.nom_complet);
        set('profileDisplayTel', data.tel);
        set('profileDisplayEmail', data.email);
        set('profileDisplayAffectation', data.affectation || data.nom_commune);
        updateGeoChip('profileChipProvince', 'profileDisplayProvince', 'Province', data.nom_province);
        updateGeoChip('profileChipVille', 'profileDisplayVille', 'Ville', data.nom_ville);
        updateGeoChip('profileChipCommune', 'profileDisplayCommune', 'Commune', data.nom_commune);

        const roleEl = document.getElementById('profileDisplayRole');
        if (roleEl) {
            const isAdmin = data.role === 'admin';
            roleEl.className = 'profile-role-badge profile-role-badge--' + (isAdmin ? 'admin' : 'user');
            const label = document.getElementById('profileDisplayRoleLabel');
            if (label) label.textContent = data.role || 'utilisateur';
        }

        if (data.photo_url) {
            const img = document.getElementById('profilePagePhoto');
            const headerImg = document.getElementById('userProfileImage');
            if (img) img.src = data.photo_url;
            if (headerImg) headerImg.src = data.photo_url;
            if (previewPhoto) previewPhoto.src = data.photo_url;
        }
    }

    function loadProvinces(selectedId = null) {
        return fetch('/api/provinces').then(r => r.json()).then(data => {
            selectProvince.innerHTML = '';
            if (data.status === 'success') {
                data.data.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id_province;
                    opt.textContent = p.nom;
                    if (selectedId && p.id_province == selectedId) opt.selected = true;
                    selectProvince.appendChild(opt);
                });
            }
        });
    }

    function loadVilles(provinceId, selectedVilleId = null, callback = null) {
        selectVille.innerHTML = '';
        if (!provinceId) {
            if (callback) callback();
            return;
        }
        fetch(`/api/villes-by-province?id_province=${provinceId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    data.data.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id_ville;
                        opt.textContent = v.nom_ville;
                        if (selectedVilleId && v.id_ville == selectedVilleId) opt.selected = true;
                        selectVille.appendChild(opt);
                    });
                }
                if (callback) callback();
            });
    }

    function loadCommunes(villeId, selectedCommuneId = null) {
        selectCommune.innerHTML = '';
        if (!villeId) return;
        fetch(`/api/communes-by-ville?id_ville=${villeId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    data.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id_commune;
                        opt.textContent = c.nom;
                        if (selectedCommuneId && c.id_commune == selectedCommuneId) opt.selected = true;
                        selectCommune.appendChild(opt);
                    });
                }
            });
    }

    selectProvince?.addEventListener('change', e => {
        profileAffectation.value = '';
        loadVilles(e.target.value);
        selectCommune.innerHTML = '';
    });

    selectVille?.addEventListener('change', e => {
        profileAffectation.value = '';
        if (e.target.value) loadCommunes(e.target.value);
        else selectCommune.innerHTML = '';
    });

    selectCommune?.addEventListener('change', e => {
        const opt = selectCommune.options[selectCommune.selectedIndex];
        profileAffectation.value = opt ? opt.textContent : '';
    });

    loadProvinces(profile.id_province).then(() => {
        loadVilles(profile.id_province, profile.id_ville, () => {
            loadCommunes(profile.id_ville, profile.id_commune);
        });
    });

    function revokeCropperUrl() {
        if (cropperObjectUrl) {
            URL.revokeObjectURL(cropperObjectUrl);
            cropperObjectUrl = null;
        }
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function initCropper() {
        destroyCropper();
        cropper = new Cropper(cropperImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.92,
            responsive: true,
            background: false,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            preview: cropperPreviewEl || undefined,
        });

        const wrap = cropperImage.parentElement;
        if (wrap) {
            wrap.style.width = '100%';
            wrap.style.maxWidth = '100%';
        }
    }

    function handleFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            iziToast.error({ title: 'Erreur', message: 'Veuillez choisir une image (JPG, PNG…).' });
            return;
        }

        revokeCropperUrl();
        cropperObjectUrl = URL.createObjectURL(file);
        cropperImage.src = cropperObjectUrl;

        cropperModal.show();

        cropperModalEl.addEventListener('shown.bs.modal', function onShown() {
            initCropper();
        }, { once: true });
    }

    photoDropZone?.addEventListener('click', () => photoInput.click());
    photoDropZone?.addEventListener('dragover', e => {
        e.preventDefault();
        photoDropZone.classList.add('dragover');
    });
    photoDropZone?.addEventListener('dragleave', e => {
        e.preventDefault();
        photoDropZone.classList.remove('dragover');
    });
    photoDropZone?.addEventListener('drop', e => {
        e.preventDefault();
        photoDropZone.classList.remove('dragover');
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
    photoInput?.addEventListener('change', e => {
        if (e.target.files[0]) handleFile(e.target.files[0]);
        e.target.value = '';
    });

    document.querySelectorAll('[data-crop-action]').forEach(btn => {
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
        cropperImage.removeAttribute('src');
    });

    cropperValidate?.addEventListener('click', () => {
        if (!cropper) return;

        cropperValidate.disabled = true;
        const defaultLabel = cropperValidate.innerHTML;
        cropperValidate.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';

        cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        }).toBlob(blob => {
            if (!blob) {
                iziToast.error({ title: 'Erreur', message: 'Impossible de rogner l\'image.' });
                cropperValidate.disabled = false;
                cropperValidate.innerHTML = defaultLabel;
                return;
            }

            croppedBlob = blob;
            if (previewPhoto) {
                previewPhoto.src = URL.createObjectURL(blob);
                previewPhoto.style.display = 'block';
            }
            cropperModal.hide();
            iziToast.success({ title: 'Photo prête', message: 'Cliquez sur Enregistrer pour sauvegarder.' });

            cropperValidate.disabled = false;
            cropperValidate.innerHTML = defaultLabel;
        }, 'image/jpeg', 0.92);
    });

    function submitProfile(formData, btn) {
        const defaultHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        fetch('/api/profile/update', { method: 'POST', headers: apiHeaders(), body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    iziToast.success({ title: 'Succès', message: data.message });
                    if (data.data) updateDisplayCard(data.data);
                    croppedBlob = null;
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                }
            })
            .catch(() => iziToast.error({ title: 'Erreur', message: 'Erreur serveur' }))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = defaultHtml;
            });
    }

    document.getElementById('formProfileInfo')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('nom_complet', document.getElementById('profileNom').value.trim());
        formData.append('tel', document.getElementById('profileTel').value.trim());
        formData.append('email', document.getElementById('profileEmail').value.trim());
        formData.append('id_province', selectProvince.value || '');
        formData.append('id_ville', selectVille.value || '');
        formData.append('id_commune', selectCommune.value || '');
        formData.append('affectation', profileAffectation.value.trim());
        if (croppedBlob) formData.append('photo', croppedBlob, 'photo.jpg');
        submitProfile(formData, document.getElementById('btnSaveProfile'));
    });

    document.getElementById('formProfilePassword')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const current = document.getElementById('profileCurrentPassword').value;
        const newPwd = document.getElementById('profileNewPassword').value;
        const confirm = document.getElementById('profileConfirmPassword').value;

        if (!current || !newPwd) {
            iziToast.error({ title: 'Erreur', message: 'Remplissez tous les champs mot de passe.' });
            return;
        }

        const formData = new FormData();
        formData.append('currentPassword', current);
        formData.append('newPassword', newPwd);
        formData.append('confirmPassword', confirm);
        submitProfile(formData, document.getElementById('btnSavePassword'));
    });
});

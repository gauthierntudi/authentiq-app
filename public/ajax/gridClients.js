document.addEventListener('DOMContentLoaded', function() {

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = (json = false) => {
        const h = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' };
        if (json) {
            h['Content-Type'] = 'application/json';
        }
        return h;
    };

    const clientModalEl = document.getElementById('clientModal');
    if (!clientModalEl) return;
    const clientModal = new bootstrap.Modal(clientModalEl);

    const formClient = document.getElementById('formClient');
    const submitBtn = document.getElementById('clientSubmit');
    const selectProvince = document.getElementById('clientProvince');
    const selectVille = document.getElementById('clientVille');
    const selectTypePiece = document.getElementById('clientTypePiece');
    const numNationalContainer = document.getElementById('numNationalContainer');
    const numPassportContainer = document.getElementById('numPassportContainer');

    const tableContainer = document.getElementById("table-clients");
    const otpModalEl = document.getElementById('otpModal');
    const otpModal = otpModalEl ? new bootstrap.Modal(otpModalEl, { backdrop: 'static', keyboard: false }) : null;
    const clientDetailsModalEl = document.getElementById('clientDetailsModal');
    const clientDetailsModal = clientDetailsModalEl ? new bootstrap.Modal(clientDetailsModalEl) : null;
    const otpInputs = document.querySelectorAll('.otp-input');
    const otpHiddenInput = document.getElementById('otpInput');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const cancelOtpBtn = document.getElementById('cancelOtpBtn');

    let gridClients;
    let lastInsertedClientId = null;

    const clientCameraVideo = document.getElementById('clientCameraVideo');
    const clientCameraCanvas = document.getElementById('clientCameraCanvas');
    const clientPhotoPreview = document.getElementById('clientPhotoPreview');
    const btnClientCapturePhoto = document.getElementById('btnClientCapturePhoto');
    const btnClientStartCamera = document.getElementById('btnClientStartCamera');
    const clientCropperModalEl = document.getElementById('clientCropperModal');
    const clientCropperModal = clientCropperModalEl ? new bootstrap.Modal(clientCropperModalEl) : null;
    const clientCropperImage = document.getElementById('clientCropperImage');
    const clientCropperValidate = document.getElementById('clientCropperValidate');
    const clientCropperPreviewEl = clientCropperModalEl?.querySelector('.cropper-preview-circle');

    let clientCameraStream = null;
    let clientCroppedBlob = null;
    let clientPreviewObjectUrl = null;
    let clientCropper = null;
    let clientCropperObjectUrl = null;
    let clientEditHasPhoto = false;

    function revokeClientPreviewUrl() {
        if (clientPreviewObjectUrl) {
            URL.revokeObjectURL(clientPreviewObjectUrl);
            clientPreviewObjectUrl = null;
        }
    }

    function revokeClientCropperUrl() {
        if (clientCropperObjectUrl) {
            URL.revokeObjectURL(clientCropperObjectUrl);
            clientCropperObjectUrl = null;
        }
    }

    function destroyClientCropper() {
        if (clientCropper) {
            clientCropper.destroy();
            clientCropper = null;
        }
    }

    function stopClientCamera() {
        if (clientCameraStream) {
            clientCameraStream.getTracks().forEach((t) => t.stop());
            clientCameraStream = null;
        }
        if (clientCameraVideo) {
            clientCameraVideo.srcObject = null;
        }
    }

    function startClientCamera() {
        if (!clientCameraVideo || clientCameraStream) return;

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false,
        })
            .then((stream) => {
                clientCameraStream = stream;
                clientCameraVideo.srcObject = stream;
            })
            .catch((err) => {
                console.error('Caméra client:', err);
                iziToast.error({ title: 'Caméra', message: 'Autorisez l\'accès à la caméra pour prendre la photo.' });
            });
    }

    function resetClientPhotoState() {
        clientCroppedBlob = null;
        clientEditHasPhoto = false;
        revokeClientPreviewUrl();
        if (clientPhotoPreview) {
            clientPhotoPreview.src = 'assets/images/user.jpg';
        }
    }

    function clientPhotoRequirementMet() {
        if (clientCroppedBlob) return true;
        const id = document.getElementById('clientId')?.value;
        return id && clientEditHasPhoto;
    }

    function clientPhotoSrc(url, cacheBust) {
        if (!url || url.startsWith('blob:')) {
            return url || 'assets/images/user.jpg';
        }
        if (url.includes('?v=') || url.includes('&v=')) {
            return url;
        }
        const sep = url.includes('?') ? '&' : '?';
        const v = cacheBust ?? Date.now();

        return `${url}${sep}v=${v}`;
    }

    function setClientPhotoPreviewFromUrl(url, cacheBust) {
        revokeClientPreviewUrl();
        if (clientPhotoPreview && url) {
            clientPhotoPreview.src = clientPhotoSrc(url, cacheBust);
        }
    }

    function refreshClientPhotoInGrid(clientId, photoUrl) {
        if (!gridClients || !clientId || !photoUrl) {
            return;
        }
        const wrapper = tableContainer?.querySelector('.gridjs-wrapper');
        if (!wrapper) {
            return;
        }
        const rows = wrapper.querySelectorAll('tbody tr');
        rows.forEach((row) => {
            const idCell = row.querySelector('td');
            if (idCell && String(idCell.textContent).trim() === String(clientId)) {
                const img = row.querySelector('td:nth-child(2) img');
                if (img) {
                    img.src = clientPhotoSrc(photoUrl);
                }
            }
        });
    }

    function openClientCropperWithFile(fileOrBlob) {
        if (!clientCropperImage || !clientCropperModal) return;

        revokeClientCropperUrl();
        clientCropperObjectUrl = URL.createObjectURL(fileOrBlob);
        clientCropperImage.src = clientCropperObjectUrl;
        clientCropperModal.show();

        clientCropperModalEl.addEventListener('shown.bs.modal', function onShown() {
            destroyClientCropper();
            clientCropper = new Cropper(clientCropperImage, {
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
                preview: clientCropperPreviewEl || undefined,
            });
        }, { once: true });
    }

    function captureClientPhotoFromCamera() {
        if (!clientCameraVideo || !clientCameraCanvas) return;

        const w = clientCameraVideo.videoWidth;
        const h = clientCameraVideo.videoHeight;
        if (!w || !h) {
            iziToast.warning({ title: 'Caméra', message: 'La caméra n\'est pas prête.' });
            return;
        }

        clientCameraCanvas.width = w;
        clientCameraCanvas.height = h;
        clientCameraCanvas.getContext('2d').drawImage(clientCameraVideo, 0, 0, w, h);

        clientCameraCanvas.toBlob((blob) => {
            if (!blob) {
                iziToast.error({ title: 'Erreur', message: 'Échec de la capture.' });
                return;
            }
            openClientCropperWithFile(blob);
        }, 'image/jpeg', 0.92);
    }

    if (btnClientCapturePhoto) {
        btnClientCapturePhoto.addEventListener('click', captureClientPhotoFromCamera);
    }
    if (btnClientStartCamera) {
        btnClientStartCamera.addEventListener('click', () => {
            stopClientCamera();
            startClientCamera();
        });
    }

    clientCropperModalEl?.querySelectorAll('[data-crop-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!clientCropper) return;
            const action = btn.getAttribute('data-crop-action');
            if (action === 'zoom-in') clientCropper.zoom(0.1);
            if (action === 'zoom-out') clientCropper.zoom(-0.1);
            if (action === 'rotate-left') clientCropper.rotate(-90);
            if (action === 'rotate-right') clientCropper.rotate(90);
            if (action === 'reset') clientCropper.reset();
        });
    });

    clientCropperModalEl?.addEventListener('hidden.bs.modal', () => {
        destroyClientCropper();
        revokeClientCropperUrl();
        if (clientCropperImage) clientCropperImage.removeAttribute('src');
    });

    if (clientCropperValidate) {
        clientCropperValidate.addEventListener('click', () => {
            if (!clientCropper) return;

            clientCropperValidate.disabled = true;
            const defaultLabel = clientCropperValidate.innerHTML;
            clientCropperValidate.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';

            clientCropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            }).toBlob((blob) => {
                clientCropperValidate.disabled = false;
                clientCropperValidate.innerHTML = defaultLabel;

                if (!blob) {
                    iziToast.error({ title: 'Erreur', message: 'Impossible de rogner l\'image.' });
                    return;
                }

                clientCroppedBlob = blob;
                revokeClientPreviewUrl();
                clientPreviewObjectUrl = URL.createObjectURL(blob);
                if (clientPhotoPreview) {
                    clientPhotoPreview.src = clientPreviewObjectUrl;
                }
                clientCropperModal.hide();
                iziToast.success({ title: 'Photo', message: 'Photo prête pour l\'enregistrement.' });
            }, 'image/jpeg', 0.92);
        });
    }

    clientModalEl.addEventListener('shown.bs.modal', () => {
        startClientCamera();
    });

    clientModalEl.addEventListener('hidden.bs.modal', () => {
        stopClientCamera();
        destroyClientCropper();
        revokeClientCropperUrl();
    });

    // === GESTION TYPE PIÈCE ===
    if(selectTypePiece && numNationalContainer && numPassportContainer) {
        selectTypePiece.addEventListener('change', function() {
            const value = this.value;
            if(value === 'CNI') {
                numNationalContainer.style.display = 'block';
                numPassportContainer.style.display = 'none';
                document.getElementById('clientNumPassport').value = '';
            } else if(value === 'Passeport') {
                numNationalContainer.style.display = 'none';
                numPassportContainer.style.display = 'block';
                document.getElementById('clientNumNational').value = '';
            } else {
                numNationalContainer.style.display = 'none';
                numPassportContainer.style.display = 'none';
            }
        });
    }

    // === GESTION OTP 6 CASES ===
    if(otpInputs.length > 0) {
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if(!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }
                if(value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                updateHiddenOTP();
            });

            input.addEventListener('keydown', (e) => {
                if(e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').substring(0, 6);
                pasteData.split('').forEach((char, i) => {
                    if(otpInputs[i]) otpInputs[i].value = char;
                });
                const lastIndex = Math.min(pasteData.length, otpInputs.length - 1);
                otpInputs[lastIndex].focus();
                updateHiddenOTP();
            });
        });

        function updateHiddenOTP() {
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            if(otpHiddenInput) otpHiddenInput.value = otp;
        }

        function clearOTPInputs() {
            otpInputs.forEach(input => input.value = '');
            if(otpHiddenInput) otpHiddenInput.value = '';
            if(otpInputs[0]) otpInputs[0].focus();
        }

        window.clearOTPInputs = clearOTPInputs;
    }

    // Bouton Annuler OTP
    if(cancelOtpBtn) {
        cancelOtpBtn.addEventListener('click', function() {
            sessionStorage.removeItem('pending_otp_client_id');
            lastInsertedClientId = null;
            clearOTPInputs();
            if(otpModal) otpModal.hide();
            loadClients();
        });
    }

    const resendOtpBtn = document.getElementById('resendOtpBtn');
    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', () => {
            const id = lastInsertedClientId || parseInt(sessionStorage.getItem('pending_otp_client_id'), 10);
            if (!id) return;
            resendOtpBtn.disabled = true;
            fetch('/api/clients/resend-otp', {
                method: 'POST',
                headers: apiHeaders(true),
                body: JSON.stringify({ id_client: id }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        iziToast.success({ title: 'Succès', message: data.message });
                        clearOTPInputs();
                    } else {
                        iziToast.error({ title: 'Erreur', message: data.message });
                    }
                })
                .catch(() => iziToast.error({ title: 'Erreur', message: 'Impossible de renvoyer le code OTP.' }))
                .finally(() => { resendOtpBtn.disabled = false; });
        });
    }

    const pendingClientId = sessionStorage.getItem('pending_otp_client_id');
    if(pendingClientId && otpModal) {
        lastInsertedClientId = parseInt(pendingClientId);
        setTimeout(() => otpModal.show(), 500);
    }

    function loadProvinces(selectedId = null) {
        if (!selectProvince) return;
        fetch('/api/provinces')
            .then(r => r.json())
            .then(data => {
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

    function loadVilles(provinceId = null, selectedVilleId = null) {
        if (!selectVille) return;
        selectVille.innerHTML = '';
        if (!provinceId) return;

        fetch(`/api/villes-by-province?id_province=${provinceId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const provinceName = selectProvince.options[selectProvince.selectedIndex]?.text || 'une province';
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = `Sélectionner ville de ${provinceName}`;
                    defaultOpt.selected = true;
                    defaultOpt.disabled = true;
                    selectVille.appendChild(defaultOpt);
                    
                    data.data.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id_ville;
                        opt.textContent = v.nom_ville;
                        if (selectedVilleId && v.id_ville == selectedVilleId) opt.selected = true;
                        selectVille.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Erreur loadVilles', err));
    }

    if (selectProvince) {
        selectProvince.addEventListener('change', e => {
            loadVilles(e.target.value);
        });
    }

    function loadClients() {
        if (!tableContainer) return;
        fetch('/api/clients')
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    tableContainer.innerHTML = '';
                    const mappedData = resp.data.map(c => [
                        c.id_client,
                        c.photo_url || c.photo || '',
                        c.nom_complet,
                        c.tel || '',
                        c.email || '',
                        c.nom_province || '',
                        c.nom_ville || '',
                        c.active || 0,
                        c.id_province || '',
                        c.id_ville || ''
                    ]);

                    if (gridClients) {
                        gridClients.updateConfig({ data: mappedData }).forceRender();
                    } else {
                        gridClients = new gridjs.Grid({
                            columns: [
                                { name: "ID", width: "50px" },
                                { name: "Photo", width: "70px", formatter: (cell) => {
                                    const photoPath = clientPhotoSrc(cell || 'assets/images/user.jpg');
                                    const safe = photoPath.replace(/"/g, '&quot;');
                                    return gridjs.html(`<img src="${safe}" alt="" style="width:40px; height:40px; border-radius:50%; object-fit:cover;" loading="lazy">`);
                                }},
                                { name: "Nom complet", width: "200px" },
                                { name: "Téléphone", width: "120px" },
                                { name: "Email", width: "200px" },
                                { name: "Province", width: "130px" },
                                { name: "Ville", width: "130px" },
                                {
                                    name: "Statut",
                                    width: "80px",
                                    formatter: (cell, row) => {
                                        return row.cells[7].data == 1 ? 'Actif' : 'Inactif';
                                    }
                                },
                                {
                                    name: "Actions",
                                    width: "210px",
                                    formatter: (cell, row) => {
                                        const id = row.cells[0].data;
                                        const nom = row.cells[2].data.replace(/'/g, "\\'");
                                        const tel = row.cells[3].data || '';
                                        const email = row.cells[4].data || '';
                                        const provinceId = row.cells[8].data || '';
                                        const villeId = row.cells[9].data || '';
                                        const active = row.cells[7].data || 0;
                                        const credBtn = email.trim()
                                            ? `<button class="btn btn-info btn-icon me-1 border-radius" onclick="resendClientCredentials(${id})" title="Renvoyer identifiants (email + OTP)">
                                                    <iconify-icon icon="solar:letter-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                               </button>`
                                            : '';

                                        if(active == 0) {
                                            return gridjs.html(`
                                                <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editClient(${id}, '${nom}', '${tel}', '${email}', '${provinceId}', '${villeId}', ${active})" title="Modifier">
                                                    <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                                </button>
                                                ${credBtn}
                                                <button class="btn btn-success btn-icon border-radius" onclick="confirmOTP(${id})" title="Confirmer OTP">
                                                    <iconify-icon icon="solar:bolt-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                                </button>
                                            `);
                                        } else {
                                            return gridjs.html(`
                                                <button class="btn btn-primary btn-icon me-1 border-radius" onclick="viewClientDetails(${id})" title="Voir détails">
                                                    <iconify-icon icon="solar:eye-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                                </button>
                                                <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editClient(${id}, '${nom}', '${tel}', '${email}', '${provinceId}', '${villeId}', ${active})" title="Modifier">
                                                    <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                                </button>
                                                ${credBtn}
                                                <button class="btn btn-danger btn-icon border-radius" onclick="toggleClient(${id}, ${active})" title="Désactiver">
                                                    <iconify-icon icon="solar:user-block-bold" style="font-size:1.4em"></iconify-icon>
                                                </button>
                                            `);
                                        }
                                    }
                                },
                                { name: "id_province", hidden: true },
                                { name: "id_ville", hidden: true }
                            ],
                            search: true,
                            sort: true,
                            pagination: { limit: 10 },
                            data: mappedData
                        });
                        gridClients.render(tableContainer);
                    }
                } else {
                    iziToast.error({ title: 'Erreur', message: resp.message || 'Impossible de charger les clients' });
                }
            })
            .catch(err => console.error('Erreur loadClients', err));
    }

    loadClients();

    if (formClient && submitBtn) {
        formClient.addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('id_client', document.getElementById('clientId').value);
            formData.append('nom_complet', document.getElementById('clientNom').value);
            formData.append('tel', document.getElementById('clientTel').value);
            formData.append('email', document.getElementById('clientEmail').value);
            formData.append('id_province', selectProvince.value);
            formData.append('id_ville', selectVille.value);
            formData.append('type_piece_identite', selectTypePiece.value);
            formData.append('numero_national', document.getElementById('clientNumNational').value);
            formData.append('numero_passeport', document.getElementById('clientNumPassport').value);
            formData.append('adresse', document.getElementById('clientAdresse').value);

            if (!clientPhotoRequirementMet()) {
                iziToast.warning({
                    title: 'Photo requise',
                    message: 'Capturez la photo du client avec la caméra avant d\'enregistrer.',
                });
                return;
            }

            if (clientCroppedBlob) {
                formData.append('photo', clientCroppedBlob, 'client-photo.jpg');
            }

            const clientIdVal = document.getElementById('clientId').value;

            const runSave = () => {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

                fetch('/api/clients/save', { method: 'POST', headers: apiHeaders(), body: formData })
                .then(r => r.json().then(data => ({ data, status: r.status })))
                .then(({ data }) => {
                    if (window.AuthentiqClientDuplicate?.showFromApiResponse(data, { context: 'admin' })) {
                        return;
                    }
                    if (data.status === 'success') {
                        iziToast.success({ title: 'Succès', message: data.message });
                        const isNewClient = document.getElementById('clientId').value === '';
                        const savedClientId = data.client_id || clientIdVal;
                        lastInsertedClientId = savedClientId;

                        if (data.photo_url && savedClientId) {
                            refreshClientPhotoInGrid(savedClientId, data.photo_url);
                            const detailPhoto = document.getElementById('detailPhoto');
                            if (detailPhoto) {
                                detailPhoto.src = clientPhotoSrc(data.photo_url);
                            }
                        }

                        clientModal.hide();

                        if (otpModal && isNewClient) {
                            sessionStorage.setItem('pending_otp_client_id', lastInsertedClientId);
                            clearOTPInputs();
                            setTimeout(() => otpModal.show(), 300);
                        } else {
                            resetClientPhotoState();
                            loadClients();
                        }
                    } else {
                        iziToast.error({ title: 'Erreur', message: data.message });
                    }
                })
                .catch(err => {
                    console.error('Erreur:', err);
                    iziToast.error({ title: 'Erreur', message: 'Erreur de connexion' });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Enregistrer';
                });
            };

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Vérification…';

            fetch('/api/clients/check-duplicates', { method: 'POST', headers: apiHeaders(), body: formData })
                .then(r => r.json())
                .then(check => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Enregistrer';
                    if (!check.allowed) {
                        window.AuthentiqClientDuplicate?.showFromApiResponse(check, { context: 'admin' });
                        return;
                    }
                    runSave();
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Enregistrer';
                    iziToast.error({ title: 'Erreur', message: 'Impossible de vérifier les doublons.' });
                });
        });
    }

    if (verifyOtpBtn && otpHiddenInput) {
        verifyOtpBtn.addEventListener('click', e => {
            e.preventDefault();
            const otpCode = otpHiddenInput.value.trim();
            if (!otpCode || otpCode.length !== 6 || !lastInsertedClientId) {
                iziToast.warning({ title: 'Attention', message: 'Veuillez entrer le code OTP à 6 chiffres' });
                return;
            }

            verifyOtpBtn.disabled = true;
            verifyOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Vérification...';

            fetch('/api/clients/verify-otp', {
                method: 'POST',
                headers: apiHeaders(true),
                body: JSON.stringify({ id_client: lastInsertedClientId, code_otp: otpCode })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    iziToast.success({ title: 'Succès', message: data.message });
                    sessionStorage.removeItem('pending_otp_client_id');
                    if (otpModal) otpModal.hide();
                    clearOTPInputs();
                    loadClients();
                    lastInsertedClientId = null;
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                    clearOTPInputs();
                }
            })
            .finally(() => {
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.innerHTML = 'Valider OTP';
            });
        });
    }

    window.viewClientDetails = function(id) {
        fetch(`/api/clients/${id}`)
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    const client = data.data;
                    
                    // Photo
                    const photoEl = document.getElementById('detailPhoto');
                    if (photoEl) {
                        photoEl.src = clientPhotoSrc(
                            client.photo_url || client.photo || 'assets/images/user.jpg',
                            client.updated_at ? new Date(client.updated_at).getTime() : Date.now(),
                        );
                    }
                    
                    // Informations principales
                    const nomEl = document.getElementById('detailNom');
                    if(nomEl) nomEl.textContent = client.nom_complet || '-';
                    
                    const statutEl = document.getElementById('detailStatut');
                    if(statutEl) {
                        if(client.is_active == 1) {
                            statutEl.textContent = 'Actif';
                            statutEl.className = 'badge bg-success';
                        } else {
                            statutEl.textContent = 'Inactif';
                            statutEl.className = 'badge bg-danger';
                        }
                    }
                    
                    // Contact
                    const telEl = document.getElementById('detailTel');
                    if(telEl) telEl.textContent = client.tel || '-';
                    
                    const emailEl = document.getElementById('detailEmail');
                    if(emailEl) emailEl.textContent = client.email || '-';
                    
                    // Identité
                    const typePieceEl = document.getElementById('detailTypePiece');
                    if(typePieceEl) typePieceEl.textContent = client.type_piece_identite || '-';
                    
                    const numIdentiteEl = document.getElementById('detailNumIdentite');
                    if(numIdentiteEl) {
                        if(client.type_piece_identite === 'CNI') {
                            numIdentiteEl.textContent = client.numero_national || '-';
                        } else if(client.type_piece_identite === 'Passeport') {
                            numIdentiteEl.textContent = client.numero_passeport || '-';
                        } else {
                            numIdentiteEl.textContent = '-';
                        }
                    }
                    
                    // Localisation
                    const provinceEl = document.getElementById('detailProvince');
                    if(provinceEl) provinceEl.textContent = client.nom_province || '-';
                    
                    const villeEl = document.getElementById('detailVille');
                    if(villeEl) villeEl.textContent = client.nom_ville || '-';
                    
                    // Adresse
                    const adresseEl = document.getElementById('detailAdresse');
                    if(adresseEl) adresseEl.textContent = client.adresse || '-';
                    
                    // Date
                    const dateEl = document.getElementById('detailDate');
                    if(dateEl && client.created_at) {
                        const date = new Date(client.created_at);
                        dateEl.textContent = date.toLocaleDateString('fr-FR');
                    } else if(dateEl) {
                        dateEl.textContent = '-';
                    }
                    
                    // Afficher le modal
                    if(clientDetailsModal) clientDetailsModal.show();
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                }
            })
            .catch(err => {
                console.error('Erreur viewClientDetails:', err);
                iziToast.error({ title: 'Erreur', message: 'Impossible de charger les détails' });
            });
    };

    window.resendClientCredentials = async function(id) {
        const ok = await AuthentiqConfirm.confirm({
            title: 'Renvoyer les identifiants',
            text: 'Un nouveau mot de passe et un code OTP seront envoyés par email. L\'ancien mot de passe ne fonctionnera plus.',
            icon: 'warning',
            confirmButtonText: 'Envoyer',
        });
        if (!ok) return;

        iziToast.info({
            title: 'Envoi',
            message: 'Génération et envoi des identifiants…',
            timeout: 3000,
        });

        fetch('/api/clients/resend-credentials', {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify({ id_client: id }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    iziToast.success({ title: 'Succès', message: data.message });
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message || 'Envoi impossible.' });
                }
            })
            .catch(() => {
                iziToast.error({ title: 'Erreur', message: 'Erreur lors de l\'envoi des identifiants.' });
            });
    };

    window.confirmOTP = function(id) {
        // Afficher un message de chargement
        iziToast.info({ 
            title: 'Génération', 
            message: 'Génération et envoi d\'un nouveau code OTP...',
            timeout: 3000
        });

        // Appeler le backend pour régénérer et envoyer l'OTP
        fetch('/api/clients/resend-otp', {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify({ id_client: id })
        })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                iziToast.success({ 
                    title: 'Succès', 
                    message: data.message 
                });
                
                lastInsertedClientId = id;
                sessionStorage.setItem('pending_otp_client_id', id);
                clearOTPInputs();
                if(otpModal) otpModal.show();
            } else {
                iziToast.error({ 
                    title: 'Erreur', 
                    message: data.message 
                });
            }
        })
        .catch(err => {
            console.error('Erreur resendOtp:', err);
            iziToast.error({ 
                title: 'Erreur', 
                message: 'Erreur lors de l\'envoi du code OTP' 
            });
        });
    };

    window.editClient = function(id, nom, tel, email, provinceId, villeId, active){
        document.getElementById('clientId').value = id;
        document.getElementById('clientNom').value = nom;
        document.getElementById('clientTel').value = tel;
        document.getElementById('clientEmail').value = email;
        
        // Charger les données complètes du client pour avoir l'adresse
        fetch(`/api/clients/${id}`)
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    const client = data.data;
                    if(document.getElementById('clientAdresse')) {
                        document.getElementById('clientAdresse').value = client.adresse || '';
                    }
                    if(selectTypePiece) {
                        selectTypePiece.value = client.type_piece_identite || '';
                        selectTypePiece.dispatchEvent(new Event('change'));
                    }
                    if(document.getElementById('clientNumNational')) {
                        document.getElementById('clientNumNational').value = client.numero_national || '';
                    }
                    if(document.getElementById('clientNumPassport')) {
                        document.getElementById('clientNumPassport').value = client.numero_passeport || '';
                    }
                    resetClientPhotoState();
                    if (client.photo_url || client.photo) {
                        clientEditHasPhoto = true;
                        const photoUrl = client.photo_url
                            || (client.photo.startsWith('http') || client.photo.startsWith('/')
                                ? client.photo
                                : (client.photo.startsWith('uploads/') ? client.photo : `uploads/${client.photo.replace(/^\//, '')}`));
                        const bust = client.updated_at ? new Date(client.updated_at).getTime() : Date.now();
                        setClientPhotoPreviewFromUrl(photoUrl, bust);
                    }
                }
            })
            .catch(err => console.error('Erreur chargement client:', err));
        
        selectVille.innerHTML = '';
        loadProvinces(provinceId);
        if(provinceId) loadVilles(provinceId, villeId);
        document.getElementById('clientModalTitle').textContent = "Modifier le client";
        clientModal.show();
    };

    window.toggleClient = function(id, active){
        fetch('/api/clients/toggle', {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify({ id_client: id, active: 0 })
        }).then(r => r.json()).then(data => {
            if(data.status==='success'){
                iziToast.success({ title:'Succès', message:data.message });
                loadClients();
            } else iziToast.error({ title:'Erreur', message:data.message });
        });
    };

    const btnAddClient = document.getElementById('btnAddClient');
    if(btnAddClient){
        btnAddClient.addEventListener('click', ()=>{
            if(formClient) formClient.reset();
            if(numNationalContainer) numNationalContainer.style.display = 'none';
            if(numPassportContainer) numPassportContainer.style.display = 'none';
            lastInsertedClientId = null;
            resetClientPhotoState();
            loadProvinces();
            selectVille.innerHTML='';
            document.getElementById('clientModalTitle').textContent = "Ajouter un nouveau client";
            clientModal.show();
        });
    }

    try {
        if (sessionStorage.getItem('openNewClientModal') === '1') {
            sessionStorage.removeItem('openNewClientModal');
            btnAddClient?.click();
        }
    } catch (err) { /* ignore */ }
});
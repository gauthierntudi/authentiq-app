document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = (json = false) => {
        const h = { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    };

    const tableContainer = document.getElementById('table-kyc');
    const statusFilter = document.getElementById('kycStatusFilter');
    const pendingBadge = document.getElementById('kycPendingBadge');
    const detailModalEl = document.getElementById('kycDetailModal');
    if (!tableContainer || !detailModalEl) return;

    const detailModal = new bootstrap.Modal(detailModalEl);
    const btnApprove = document.getElementById('btnKycApprove');
    const btnReject = document.getElementById('btnKycReject');
    const rejectBlock = document.getElementById('kycRejectBlock');
    const rejectReason = document.getElementById('kycRejectReason');
    const reviewedInfo = document.getElementById('kycReviewedInfo');
    const modalActions = document.getElementById('kycModalActions');
    const clientInfo = document.getElementById('kycClientInfo');
    const imgRecto = document.getElementById('kycImgRecto');
    const imgVerso = document.getElementById('kycImgVerso');
    const versoCol = document.getElementById('kycVersoCol');

    let gridKyc;
    let currentSubmissionId = null;
    let currentDetail = null;

    const statusLabels = {
        pending: 'En attente',
        approved: 'Approuvé',
        rejected: 'Refusé',
    };

    function statusBadge(status) {
        const label = statusLabels[status] || status;
        const cls =
            status === 'approved'
                ? 'status-badge-approved'
                : status === 'rejected'
                  ? 'status-badge-rejected'
                  : 'status-badge-pending';
        return `<span class="badge ${cls} px-2 py-1" style="border-radius:8px">${label}</span>`;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString('fr-FR');
        } catch {
            return iso;
        }
    }

    function updatePendingBadge(count) {
        if (!pendingBadge) return;
        if (count > 0) {
            pendingBadge.textContent = `${count} en attente`;
            pendingBadge.classList.remove('d-none');
        } else {
            pendingBadge.classList.add('d-none');
        }
    }

    function loadSubmissions() {
        const status = statusFilter?.value ?? 'pending';
        const qs = status ? `?status=${encodeURIComponent(status)}` : '';

        fetch(`/api/kyc-submissions${qs}`, { headers: apiHeaders() })
            .then((res) => res.json())
            .then((response) => {
                if (response.status !== 'success') {
                    iziToast.error({
                        title: 'Erreur',
                        message: response.message || 'Impossible de charger les soumissions KYC.',
                    });
                    return;
                }

                updatePendingBadge(response.pending_count ?? 0);

                const rows = (response.submissions || []).map((s) => {
                    const client = s.client || {};
                    return [
                        s.id_submission,
                        client.nom_complet || '—',
                        client.tel || '—',
                        s.type_piece_identite || '—',
                        s.status,
                        formatDate(s.submitted_at),
                    ];
                });

                if (gridKyc) {
                    gridKyc.updateConfig({ data: rows }).forceRender();
                    return;
                }

                gridKyc = new gridjs.Grid({
                    columns: [
                        { name: 'ID', width: '70px' },
                        { name: 'Client', width: '180px' },
                        { name: 'Téléphone', width: '130px' },
                        { name: 'Pièce', width: '110px' },
                        {
                            name: 'Statut',
                            width: '120px',
                            formatter: (cell) => gridjs.html(statusBadge(cell)),
                        },
                        { name: 'Soumis le', width: '160px' },
                        {
                            name: 'Actions',
                            width: '120px',
                            formatter: (_, row) => {
                                const id = row.cells[0].data;
                                return gridjs.html(`
                                    <button type="button" class="btn btn-primary btn-sm border-radius" onclick="openKycDetail(${id})">
                                        Voir
                                    </button>
                                `);
                            },
                        },
                    ],
                    search: true,
                    sort: true,
                    pagination: { limit: 15 },
                    data: rows,
                });
                gridKyc.render(tableContainer);
            })
            .catch(() => {
                iziToast.error({ title: 'Erreur', message: 'Erreur de connexion au serveur.' });
            });
    }

    function renderClientInfo(sub) {
        const c = sub.client || {};
        const photo = c.photo_url
            ? `<img src="${c.photo_url}" alt="" class="rounded-circle" width="72" height="72" style="object-fit:cover">`
            : '';
        clientInfo.innerHTML = `
            <div class="col-md-8">
                <div class="d-flex gap-3 align-items-start">
                    ${photo}
                    <div>
                        <h5 class="mb-1">${c.nom_complet || '—'}</h5>
                        <p class="mb-0 text-muted">${c.tel || ''} ${c.email ? ' · ' + c.email : ''}</p>
                        <p class="mb-0 text-muted">${[c.nom_ville, c.nom_province].filter(Boolean).join(' — ') || ''}</p>
                        <p class="mb-0 mt-1"><strong>Pièce :</strong> ${sub.type_piece_identite || '—'}</p>
                        <p class="mb-0">${statusBadge(sub.status)} · soumis ${formatDate(sub.submitted_at)}</p>
                    </div>
                </div>
            </div>
        `;
    }

    window.openKycDetail = function (id) {
        currentSubmissionId = id;
        currentDetail = null;
        rejectReason.value = '';
        rejectBlock.classList.add('d-none');
        reviewedInfo.classList.add('d-none');
        imgRecto.src = '';
        imgVerso.src = '';

        fetch(`/api/kyc-submissions/${id}`, { headers: apiHeaders() })
            .then((res) => res.json())
            .then((data) => {
                if (data.status !== 'success' || !data.submission) {
                    iziToast.error({ title: 'Erreur', message: data.message || 'Soumission introuvable.' });
                    return;
                }
                currentDetail = data.submission;
                document.getElementById('kycModalTitle').textContent =
                    `KYC #${currentDetail.id_submission} — ${currentDetail.client?.nom_complet || ''}`;

                renderClientInfo(currentDetail);

                if (currentDetail.recto_url) {
                    imgRecto.src = currentDetail.recto_url;
                }
                if (currentDetail.verso_url) {
                    imgVerso.src = currentDetail.verso_url;
                    versoCol.classList.remove('d-none');
                } else {
                    versoCol.classList.add('d-none');
                }

                const isPending = currentDetail.status === 'pending';
                btnApprove.classList.toggle('d-none', !isPending);
                btnReject.classList.toggle('d-none', !isPending);
                rejectBlock.classList.toggle('d-none', !isPending);
                if (isPending) rejectReason.value = '';

                if (!isPending) {
                    let msg = `Traité le ${formatDate(currentDetail.reviewed_at)}.`;
                    if (currentDetail.rejection_reason) {
                        msg += ` Motif : ${currentDetail.rejection_reason}`;
                    }
                    reviewedInfo.textContent = msg;
                    reviewedInfo.classList.remove('d-none');
                }

                detailModal.show();
            })
            .catch(() => iziToast.error({ title: 'Erreur', message: 'Impossible de charger le détail.' }));
    };

    function refreshAfterReview(message) {
        iziToast.success({ title: 'Succès', message });
        detailModal.hide();
        currentSubmissionId = null;
        loadSubmissions();
    }

    btnApprove?.addEventListener('click', () => {
        if (!currentSubmissionId) return;
        if (!confirm('Approuver ce KYC ? Le client ne pourra plus modifier son profil.')) return;

        btnApprove.disabled = true;
        fetch(`/api/kyc-submissions/${currentSubmissionId}/approve`, {
            method: 'POST',
            headers: apiHeaders(),
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.status === 'success') {
                    refreshAfterReview(data.message || 'KYC approuvé.');
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message || 'Échec de l\'approbation.' });
                }
            })
            .catch(() => iziToast.error({ title: 'Erreur', message: 'Erreur de connexion.' }))
            .finally(() => {
                btnApprove.disabled = false;
            });
    });

    btnReject?.addEventListener('click', () => {
        if (!currentSubmissionId) return;
        if (!confirm('Refuser cette soumission ? Le client pourra en soumettre une nouvelle.')) return;

        btnReject.disabled = true;
        fetch(`/api/kyc-submissions/${currentSubmissionId}/reject`, {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify({
                rejection_reason: rejectReason.value.trim(),
            }),
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.status === 'success') {
                    refreshAfterReview(data.message || 'KYC refusé.');
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message || 'Échec du refus.' });
                }
            })
            .catch(() => iziToast.error({ title: 'Erreur', message: 'Erreur de connexion.' }))
            .finally(() => {
                btnReject.disabled = false;
            });
    });

    statusFilter?.addEventListener('change', loadSubmissions);
    loadSubmissions();
});

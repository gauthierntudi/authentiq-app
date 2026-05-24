(function () {
    const app = document.getElementById('documents-library-app');
    if (!app) return;

    const foldersEl = document.getElementById('docLibFolders');
    const fileListEl = document.getElementById('docLibFileList');
    const emptyEl = document.getElementById('docLibEmpty');
    const listTitleEl = document.getElementById('docLibListTitle');
    const listCountEl = document.getElementById('docLibListCount');
    const asideRecentEl = document.getElementById('docLibAsideRecent');
    const statsEl = document.getElementById('docLibStats');
    const storageValueEl = document.getElementById('docLibStorageValue');
    const searchInput = document.getElementById('docLibSearch');
    const statusInput = document.getElementById('docLibStatus');
    const btnApply = document.getElementById('docLibApplyFilter');
    const btnReset = document.getElementById('docLibResetFilter');

    let selectedClientId = null;
    let storageChart = null;
    let lastData = null;
    let viewerPages = [];
    let viewerIndex = 0;
    let viewerEncodageMeta = null;

    const viewerModal = document.getElementById('encPagesViewerModal');
    const qrPanel = document.getElementById('encPagesViewerQrPanel');
    const qrImg = document.getElementById('encPagesViewerQrImg');
    const qrRef = document.getElementById('encPagesViewerRef');
    const qrLink = document.getElementById('encPagesViewerVerifyLink');
    const viewerImg = document.getElementById('encPagesViewerImg');
    const viewerTitle = document.getElementById('encPagesViewerTitle');
    const viewerCounter = document.getElementById('encPagesViewerCounter');
    const viewerPrev = document.getElementById('encPagesViewerPrev');
    const viewerNext = document.getElementById('encPagesViewerNext');

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function statusLabel(status) {
        const map = { complete: 'Finalisé', incomplete: 'En cours', expired: 'Expiré' };
        return map[status] || status;
    }

    function fileIcon(typeDoc) {
        const t = String(typeDoc || '').toLowerCase();
        if (t.includes('passeport')) return 'solar:passport-bold-duotone';
        if (t.includes('cni') || t.includes('identit')) return 'solar:card-2-bold-duotone';
        return 'solar:document-text-bold-duotone';
    }

    function buildQuery() {
        const params = new URLSearchParams();
        if (selectedClientId !== null && selectedClientId !== '') {
            params.set('client_id', String(selectedClientId));
        }
        if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
        if (statusInput?.value) params.set('status', statusInput.value);
        const qs = params.toString();
        return qs ? `/api/documents-library?${qs}` : '/api/documents-library';
    }

    function renderFolders(clients) {
        const allActive = selectedClientId === null;
        let html = `
            <button type="button" class="doc-lib-folder${allActive ? ' is-active' : ''}" data-client-id="">
                <span class="doc-lib-folder__menu"><iconify-icon icon="solar:menu-dots-bold"></iconify-icon></span>
                <div class="doc-lib-folder__icon doc-lib-folder__icon--all">
                    <iconify-icon icon="solar:folder-bold"></iconify-icon>
                </div>
                <div class="doc-lib-folder__name">Tous les clients</div>
                <div class="doc-lib-folder__meta" id="docLibAllMeta">—</div>
            </button>`;

        (clients || []).forEach((c) => {
            const active = selectedClientId !== null && selectedClientId === c.id_client;
            const color = c.folder_color || 'blue';
            html += `
                <button type="button" class="doc-lib-folder${active ? ' is-active' : ''}" data-client-id="${c.id_client}">
                    <span class="doc-lib-folder__menu"><iconify-icon icon="solar:menu-dots-bold"></iconify-icon></span>
                    <img src="${escapeHtml(c.photo_url)}" alt="" class="doc-lib-folder__avatar" loading="lazy">
                    <div class="doc-lib-folder__name">${escapeHtml(c.nom_complet)}</div>
                    <div class="doc-lib-folder__meta">${c.encodages_count} doc · ${escapeHtml(c.size_label)}</div>
                </button>`;
        });

        if (!foldersEl) return;
        foldersEl.innerHTML = html;
        foldersEl.querySelectorAll('.doc-lib-folder').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.clientId;
                selectedClientId = id === '' ? null : parseInt(id, 10);
                loadLibrary();
            });
        });
    }

    function clientsLine(item, compact) {
        const clients = Array.isArray(item.associated_clients) ? item.associated_clients : [];
        const ownership = item.ownership || 'single';
        const count = item.clients_count || clients.length || 0;

        if (clients.length > 1 || (ownership === 'multiple' && count > 1)) {
            const names = clients.map((c) => c.nom_complet).filter(Boolean).join(', ');
            const label = names || item.client_nom || '—';
            return compact
                ? `${escapeHtml(label)} · ${count} co-titulaires`
                : `${escapeHtml(label)} · ${count} co-titulaires · ${statusLabel(item.status)} · ${item.pages_count} p.`;
        }

        const clientName = escapeHtml(item.client_nom || '—');
        return compact
            ? `${clientName} · ${statusLabel(item.status)}`
            : `${clientName} · ${statusLabel(item.status)} · ${item.pages_count} p.`;
    }

    function renderFileItem(item, compact) {
        const thumb = item.preview_url
            ? `<img src="${escapeHtml(item.preview_url)}" alt="" class="doc-lib-file__thumb" loading="lazy">`
            : `<span class="doc-lib-file__icon"><iconify-icon icon="${fileIcon(item.type_doc)}"></iconify-icon></span>`;
        const sub = `${clientsLine(item, compact)} · ${escapeHtml(item.updated_at || '')}`;
        const qrBadge = item.status === 'complete' && item.qr_url
            ? `<span class="doc-lib-file__qr-badge" title="QR code disponible"><iconify-icon icon="solar:qr-code-bold-duotone"></iconify-icon></span>`
            : '';
        const multiBadge = item.ownership === 'multiple'
            ? `<span class="doc-lib-file__multi-badge" title="Propriété multiple"><iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon><span>${item.clients_count || (item.associated_clients || []).length || 0}</span></span>`
            : '';
        const clientAvatars = (item.associated_clients || []).length > 1
            ? `<div class="doc-lib-file__avatars">${(item.associated_clients || []).slice(0, 3).map((c) => `<img src="${escapeHtml(c.photo_url || '/assets/images/user.jpg')}" alt="" class="doc-lib-file__avatar" loading="lazy">`).join('')}</div>`
            : '';

        return `
            <li class="doc-lib-file${item.ownership === 'multiple' ? ' doc-lib-file--multi' : ''}" data-encodage-id="${item.id_encodage}" role="button" tabindex="0">
                ${thumb}
                ${clientAvatars}
                <div class="doc-lib-file__body">
                    <div class="doc-lib-file__name">${escapeHtml(item.type_doc)} #${item.id_encodage}${multiBadge}${qrBadge}</div>
                    <div class="doc-lib-file__sub">${sub}</div>
                </div>
                <span class="doc-lib-file__size">${escapeHtml(item.size_label)}</span>
            </li>`;
    }

    function bindFileClicks(container) {
        container.querySelectorAll('.doc-lib-file').forEach((row) => {
            const open = () => openEncodage(parseInt(row.dataset.encodageId, 10));
            row.addEventListener('click', open);
            row.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });
    }

    function setText(el, text) {
        if (el) el.textContent = text;
    }

    function renderFileList(encodages, title) {
        setText(listTitleEl, title);
        setText(listCountEl, encodages.length ? `${encodages.length} fichier(s)` : '');

        if (!encodages.length) {
            if (fileListEl) fileListEl.innerHTML = '';
            emptyEl?.classList.remove('d-none');
            return;
        }

        emptyEl?.classList.add('d-none');
        if (fileListEl) {
            fileListEl.innerHTML = encodages.map((e) => renderFileItem(e, false)).join('');
            bindFileClicks(fileListEl);
        }
    }

    function renderAside(recent, summary) {
        setText(storageValueEl, String(summary.encodages_count ?? 0));

        statsEl.innerHTML = `
            <div class="doc-lib-stat-row"><span>Clients</span><strong>${summary.clients_count ?? 0}</strong></div>
            <div class="doc-lib-stat-row"><span>Pages scannées</span><strong>${summary.pages_count ?? 0}</strong></div>
            <div class="doc-lib-stat-row"><span>Volume</span><strong>${escapeHtml(summary.size_label || '0 o')}</strong></div>
        `;

        const mini = (recent || []).slice(0, 5);
        if (asideRecentEl) {
            asideRecentEl.innerHTML = mini.map((e) => renderFileItem(e, true)).join('');
            bindFileClicks(asideRecentEl);
        }

        renderStorageChart(summary);
    }

    function renderStorageChart(summary) {
        const el = document.getElementById('docLibStorageChart');
        if (!el || typeof ApexCharts === 'undefined') return;

        const complete = lastData?.encodages?.filter((e) => e.status === 'complete').length ?? 0;
        const incomplete = lastData?.encodages?.filter((e) => e.status === 'incomplete').length ?? 0;
        const expired = lastData?.encodages?.filter((e) => e.status === 'expired').length ?? 0;
        const total = summary.encodages_count || 1;
        const pct = Math.min(100, Math.round((complete / total) * 100));

        const options = {
            chart: { type: 'radialBar', height: 140, sparkline: { enabled: true } },
            series: [pct],
            colors: ['#0eedee'],
            plotOptions: {
                radialBar: {
                    hollow: { size: '62%' },
                    track: { background: 'rgba(64, 70, 94, 0.5)' },
                    dataLabels: { show: false },
                },
            },
        };

        if (storageChart) {
            storageChart.updateOptions(options);
            storageChart.updateSeries([pct]);
        } else {
            storageChart = new ApexCharts(el, options);
            storageChart.render();
        }
    }

    function updateViewerQr(meta) {
        if (!qrPanel) {
            return;
        }

        const show = meta?.status === 'complete' && meta?.qr_url;
        qrPanel.hidden = !show;

        if (!show) {
            return;
        }

        if (qrImg) {
            qrImg.src = meta.qr_url;
        }
        if (qrRef) {
            qrRef.textContent = meta.numero || '—';
        }
        if (qrLink) {
            if (meta.verify_url) {
                qrLink.href = meta.verify_url;
                qrLink.hidden = false;
            } else {
                qrLink.href = '#';
                qrLink.hidden = true;
            }
        }
    }

    async function openEncodage(id) {
        if (!id) return;

        viewerEncodageMeta = (lastData?.encodages || []).find((e) => e.id_encodage === id) || null;

        try {
            const res = await fetch(`/api/encodage-workflow/${id}/pages`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (json.status !== 'success' || !json.pages?.length) {
                iziToast?.info?.({ title: 'Document', message: 'Aucune page scannée pour cet encodage.' });
                return;
            }
            viewerPages = json.pages;
            viewerIndex = 0;
            showViewerPage();
            updateViewerQr(viewerEncodageMeta);
            bootstrap.Modal.getOrCreateInstance(viewerModal).show();
        } catch (e) {
            iziToast?.error?.({ title: 'Erreur', message: e.message });
        }
    }

    function showViewerPage() {
        const page = viewerPages[viewerIndex];
        if (!page) return;
        if (viewerImg) viewerImg.src = page.file_path || '';
        setText(viewerTitle, `Page ${page.page_number}`);
        setText(viewerCounter, `Page ${viewerIndex + 1} sur ${viewerPages.length}`);
        if (viewerPrev) viewerPrev.disabled = viewerIndex <= 0;
        if (viewerNext) viewerNext.disabled = viewerIndex >= viewerPages.length - 1;
    }

    viewerPrev?.addEventListener('click', () => {
        if (viewerIndex > 0) {
            viewerIndex--;
            showViewerPage();
        }
    });
    viewerNext?.addEventListener('click', () => {
        if (viewerIndex < viewerPages.length - 1) {
            viewerIndex++;
            showViewerPage();
        }
    });

    async function loadLibrary() {
        try {
            const res = await fetch(buildQuery(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (json.status !== 'success') throw new Error(json.message || 'Erreur');

            lastData = json.data;
            const { clients, encodages, recent, summary } = lastData;

            renderFolders(clients);

            setText(
                document.getElementById('docLibAllMeta'),
                `${summary.encodages_count} doc · ${summary.size_label || '0 o'}`,
            );

            let title = 'Fichiers récents';
            if (selectedClientId !== null) {
                const client = clients.find((c) => c.id_client === selectedClientId);
                title = client ? `Documents — ${client.nom_complet}` : 'Documents client';
            }

            renderFileList(encodages, title);
            renderAside(recent, summary);
        } catch (e) {
            iziToast?.error?.({ title: 'Documents', message: e.message });
        }
    }

    btnApply?.addEventListener('click', () => {
        bootstrap.Offcanvas.getInstance(document.getElementById('docLibFilterPanel'))?.hide();
        loadLibrary();
    });
    btnReset?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (statusInput) statusInput.value = '';
        selectedClientId = null;
        bootstrap.Offcanvas.getInstance(document.getElementById('docLibFilterPanel'))?.hide();
        loadLibrary();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadLibrary);
    } else {
        loadLibrary();
    }
})();

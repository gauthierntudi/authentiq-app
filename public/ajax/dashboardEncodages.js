document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = () => ({
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
    });

    const tableContainer = document.getElementById('table-encodages');
    if (!tableContainer) return;

    const filterStatus = document.getElementById('filterStatus');
    const filterPeriod = document.getElementById('filterPeriod');
    const filterSearch = document.getElementById('filterSearch');
    const btnApply = document.getElementById('btnFilterApply');
    const btnReset = document.getElementById('btnFilterReset');

    let gridEncodages = null;
    let showUserColumn = false;
    let searchDebounceTimer = null;
    let encodageRowById = new Map();
    const isAdmin = window.AUTHENTIQ_USER_ROLE === 'admin';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function clientCellHtml(photoUrl, name) {
        const photo = escapeHtml(photoUrl || '/assets/images/user.jpg');
        const label = escapeHtml(name || '—');
        return gridjs.html(`
            <div class="enc-table-client">
                <img src="${photo}" alt="" class="enc-table-client__photo" loading="lazy" width="36" height="36">
                <span class="enc-table-client__name">${label}</span>
            </div>
        `);
    }

    function scheduleLoadEncodages(delay = 0) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(loadEncodages, delay);
    }

    function buildQuery() {
        const params = new URLSearchParams();
        if (filterStatus?.value) params.set('status', filterStatus.value);
        if (filterPeriod?.value) params.set('period', filterPeriod.value);
        if (filterSearch?.value.trim()) params.set('search', filterSearch.value.trim());
        const qs = params.toString();
        return qs ? `/api/encodages?${qs}` : '/api/encodages';
    }

    function formatDate(dateString) {
        if (!dateString) return '—';
        const d = new Date(dateString);
        if (Number.isNaN(d.getTime())) return dateString;
        return d.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function statusBadge(status) {
        const map = {
            complete: { cls: 'encodage-status-badge--complete', label: 'Complet' },
            expired: { cls: 'encodage-status-badge--expired', label: 'Expiré' },
            incomplete: { cls: 'encodage-status-badge--incomplete', label: 'Incomplet' },
        };
        const entry = map[status] || map.incomplete;
        return gridjs.html(`<span class="encodage-status-badge ${entry.cls}">${entry.label}</span>`);
    }

    function updateEncodageStats(stats) {
        const el = id => document.getElementById(id);
        if (el('statTotal')) el('statTotal').textContent = stats?.total ?? 0;
        if (el('statIncomplete')) el('statIncomplete').textContent = stats?.incomplete ?? 0;
        if (el('statComplete')) el('statComplete').textContent = stats?.complete ?? 0;
        if (el('statExpired')) el('statExpired').textContent = stats?.expired ?? 0;
    }

    function updatePlatformStats(stats) {
        const agentsEl = document.getElementById('statAgents');
        const clientsEl = document.getElementById('statClients');
        const agentsHint = document.getElementById('statAgentsHint');
        const clientsHint = document.getElementById('statClientsHint');

        if (agentsEl) agentsEl.textContent = stats?.agents ?? 0;
        if (clientsEl) clientsEl.textContent = stats?.clients ?? 0;

        if (agentsHint && stats) {
            const field = stats.agents_field ?? 0;
            const admin = stats.agents_admin ?? 0;
            agentsHint.textContent = `${field} agent${field !== 1 ? 's' : ''} · ${admin} admin${admin !== 1 ? 's' : ''}`;
        }
        if (clientsHint && stats) {
            const active = stats.clients_active ?? 0;
            const total = stats.clients ?? 0;
            clientsHint.textContent = `${active} actif${active !== 1 ? 's' : ''} sur ${total}`;
        }
    }

    function loadPlatformStats() {
        fetch('/api/dashboard/stats', { headers: apiHeaders() })
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    updatePlatformStats(response.stats);
                }
            })
            .catch(() => {
                const agentsEl = document.getElementById('statAgents');
                const clientsEl = document.getElementById('statClients');
                if (agentsEl) agentsEl.textContent = '—';
                if (clientsEl) clientsEl.textContent = '—';
            });
    }

    function loadEncodages() {
        fetch(buildQuery(), { headers: apiHeaders() })
            .then(res => res.json())
            .then(response => {
                if (response.status !== 'success') {
                    iziToast.error({ title: 'Erreur', message: response.message || 'Chargement impossible.' });
                    return;
                }

                updateEncodageStats(response.stats);
                showUserColumn = response.data.some(r => r.user_nom);

                encodageRowById = new Map(
                    response.data.map(r => [r.id_encodage, {
                        photo: r.client_photo_url,
                        name: r.client_nom,
                    }]),
                );

                tableContainer.innerHTML = '';

                const columns = [
                    { name: 'ID', width: '70px' },
                    {
                        name: 'Statut',
                        width: '110px',
                        formatter: (_, row) => statusBadge(row.cells[1].data),
                    },
                    {
                        name: 'Client',
                        width: '200px',
                        formatter: (_, row) => {
                            const meta = encodageRowById.get(row.cells[0].data) || {};
                            return clientCellHtml(meta.photo, meta.name);
                        },
                    },
                    { name: 'Document', width: '140px' },
                    { name: 'Affectation', width: '120px' },
                    { name: 'Pages', width: '70px' },
                    { name: 'Créé le', width: '140px' },
                    { name: 'Modifié le', width: '140px' },
                ];

                if (showUserColumn) {
                    columns.splice(4, 0, { name: 'Agent', width: '130px' });
                }

                columns.push({
                    name: 'Actions',
                    width: '120px',
                    sort: false,
                    formatter: (_, row) => {
                        const id = row.cells[0].data;
                        const status = row.cells[1].data;
                        if (status !== 'incomplete' && !isAdmin) {
                            return gridjs.html('<span class="text-muted">—</span>');
                        }
                        const editLabel = status === 'incomplete' ? 'Continuer' : 'Modifier';
                        const editIcon = status === 'incomplete'
                            ? 'solar:play-bold-duotone'
                            : 'solar:pen-bold-duotone';
                        return gridjs.html(`
                            <a href="/encodage-document" class="btn btn-secondary btn-icon me-1 border-radius" title="${editLabel}"
                               onclick="sessionStorage.setItem('continueEncodageId','${id}');">
                                <iconify-icon icon="${editIcon}" style="font-size:1.4em"></iconify-icon>
                            </a>
                            <button type="button" class="btn btn-danger btn-icon border-radius" onclick="deleteEncodage(${id})" title="Supprimer">
                                <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                            </button>
                        `);
                    },
                });

                const tableData = response.data.map(row => {
                    const base = [
                        row.id_encodage,
                        row.status,
                        row.client_nom || '—',
                        row.type_doc || '—',
                        row.affectation || '—',
                        row.nb_pages ?? 0,
                        formatDate(row.date_creation),
                        formatDate(row.date_modification),
                    ];
                    if (showUserColumn) {
                        base.splice(4, 0, row.user_nom || '—');
                    }
                    return base;
                });

                if (gridEncodages) {
                    gridEncodages.updateConfig({ columns, data: tableData }).forceRender();
                } else {
                    gridEncodages = new gridjs.Grid({
                        columns,
                        data: tableData,
                        search: false,
                        sort: true,
                        pagination: { limit: 10 },
                    });
                    gridEncodages.render(tableContainer);
                }
            })
            .catch(() => {
                iziToast.error({ title: 'Erreur', message: 'Impossible de charger les encodages.' });
            });
    }

    window.deleteEncodage = function(id) {
        iziToast.question({
            timeout: 20000,
            close: true,
            overlay: true,
            displayMode: 'once',
            backgroundColor: '#3d4153',
            title: 'Confirmation',
            message: 'Supprimer cet encodage ? Cette action est irréversible.',
            position: 'bottomCenter',
            theme: 'dark',
            buttons: [
                ['<button>Oui</button>', function(instance, toast) {
                    instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    fetch(`/api/encodages/${id}`, { method: 'DELETE', headers: apiHeaders() })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                iziToast.success({ title: 'Supprimé', message: data.message });
                                loadEncodages();
                            } else {
                                iziToast.error({ title: 'Erreur', message: data.message });
                            }
                        });
                }, true],
                ['<button>Non</button>', function(instance, toast) {
                    instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                }],
            ],
        });
    };

    filterStatus?.addEventListener('change', loadEncodages);
    filterPeriod?.addEventListener('change', loadEncodages);
    filterSearch?.addEventListener('input', () => scheduleLoadEncodages(400));

    btnApply?.addEventListener('click', () => {
        clearTimeout(searchDebounceTimer);
        loadEncodages();
    });

    btnReset?.addEventListener('click', () => {
        clearTimeout(searchDebounceTimer);
        if (filterStatus) filterStatus.value = '';
        if (filterPeriod) filterPeriod.value = 'all';
        if (filterSearch) filterSearch.value = '';
        loadEncodages();
    });

    loadPlatformStats();
    loadEncodages();
});

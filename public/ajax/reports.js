(function () {
    const root = document.getElementById('report-app');
    if (!root) return;

    const type = root.dataset.reportType || 'daily';
    const dateInput = document.getElementById('reportDate');
    const monthInput = document.getElementById('reportMonth');
    const btnRefresh = document.getElementById('btnReportRefresh');
    const periodLabel = document.getElementById('reportPeriodLabel');
    const statsEl = document.getElementById('reportStats');
    const chartMainEl = document.getElementById('reportChartMain');
    const breakdownAgent = document.getElementById('reportBreakdownAgent');
    const breakdownDoc = document.getElementById('reportBreakdownDoc');
    const breakdownCommune = document.getElementById('reportBreakdownCommune');
    const breakdownStatus = document.getElementById('reportBreakdownStatus');
    const recentTable = document.getElementById('report-recent-table');

    let chartMain = null;
    let recentGrid = null;

    const endpoints = {
        daily: '/api/reports/daily',
        monthly: '/api/reports/monthly',
        global: '/api/reports/global',
    };

    function apiUrl() {
        const base = endpoints[type] || endpoints.daily;
        const url = new URL(base, window.location.origin);
        if (type === 'daily' && dateInput?.value) {
            url.searchParams.set('date', dateInput.value);
        }
        if (type === 'monthly' && monthInput?.value) {
            url.searchParams.set('month', monthInput.value);
        }
        return url.toString();
    }

    function fmtMoney(v) {
        const n = Number(v) || 0;
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' $';
    }

    function statCard(label, value, hint, mod) {
        return `<div class="report-stat-card ${mod || ''}">
            <div class="stat-label">${label}</div>
            <div class="stat-value">${value}</div>
            ${hint ? `<div class="stat-hint">${hint}</div>` : ''}</div>`;
    }

    function renderStats(data) {
        const s = data.summary || {};
        const extraFinalized = s.finalized_today ?? s.finalized_in_period;
        const finalizedHint = extraFinalized != null
            ? `${extraFinalized} finalisé(s) sur la période`
            : null;

        let html = '';
        html += statCard('Encodages', s.encodages_total ?? 0, 'Créés sur la période');
        html += statCard('Finalisés', s.encodages_complete ?? 0, finalizedHint, 'report-stat-card--complete');
        html += statCard('En cours', s.encodages_incomplete ?? 0, null, 'report-stat-card--incomplete');
        if ((s.encodages_expired ?? 0) > 0 || type === 'global') {
            html += statCard('Expirés', s.encodages_expired ?? 0, null, 'report-stat-card--expired');
        }
        html += statCard('Montants', fmtMoney(s.montant_total), `${s.pages_total ?? 0} page(s) scannée(s)`, 'report-stat-card--money');
        if (s.ocr_quality_avg != null) {
            html += statCard('Qualité OCR', `${s.ocr_quality_avg}%`, 'Moyenne Textract', 'report-stat-card--complete');
        }
        if (type === 'global' && s.agents_active != null) {
            html += statCard('Agents actifs', s.agents_active, 'Ayant encodé au moins une fois');
        }
        if (data.clients_new != null) {
            html += statCard('Clients', data.clients_new, type === 'global' ? 'Total en base' : 'Nouveaux sur la période');
        }
        statsEl.innerHTML = html;
    }

    function renderBreakdown(el, items, showComplete) {
        if (!el) return;
        if (!items?.length) {
            el.innerHTML = '<li class="text-muted">Aucune donnée</li>';
            return;
        }
        const max = Math.max(...items.map((i) => i.value), 1);
        el.innerHTML = items.map((item) => {
            const pct = Math.round((item.value / max) * 100);
            const sub = showComplete && item.complete != null
                ? ` <span class="text-muted">(${item.complete} fin.)</span>`
                : '';
            return `<li>
                <span class="name" title="${item.label}">${item.label}${sub}</span>
                <span class="bar-wrap"><span class="bar" style="width:${pct}%"></span></span>
                <span class="count">${item.value}</span>
            </li>`;
        }).join('');
    }

    function renderChart(timeline) {
        if (!chartMainEl || typeof ApexCharts === 'undefined') return;
        const labels = (timeline || []).map((t) => t.label);
        const values = (timeline || []).map((t) => t.value);

        const options = {
            chart: {
                type: 'area',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit',
                background: 'transparent',
            },
            theme: { mode: 'dark' },
            colors: ['#0eedee'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                },
            },
            grid: {
                borderColor: 'rgba(64, 70, 94, 0.45)',
                strokeDashArray: 4,
            },
            xaxis: {
                categories: labels,
                labels: { style: { colors: '#8b93a8' } },
            },
            yaxis: {
                labels: { style: { colors: '#8b93a8' } },
                forceNiceScale: true,
            },
            tooltip: { theme: 'dark' },
            series: [{ name: 'Encodages', data: values }],
        };

        if (chartMain) {
            chartMain.updateOptions(options);
            return;
        }
        chartMain = new ApexCharts(chartMainEl, options);
        chartMain.render();
    }

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

    function statusBadgeHtml(status) {
        const map = {
            complete: ['Finalisé', 'encodage-status-badge--complete'],
            incomplete: ['En cours', 'encodage-status-badge--incomplete'],
            expired: ['Expiré', 'encodage-status-badge--expired'],
        };
        const [text, cls] = map[status] || [status || '—', ''];
        return gridjs.html(`<span class="encodage-status-badge ${cls}">${text}</span>`);
    }

    function renderRecent(rows) {
        if (!recentTable || typeof gridjs === 'undefined') return;

        const list = rows || [];
        const tableData = list.map((r) => [
            r.id_encodage,
            r.status || 'incomplete',
            r.client_nom || '—',
            r.client_photo_url || '',
            r.type_doc || '—',
            r.agent_nom || '—',
            r.affectation || '—',
            r.montant != null ? fmtMoney(r.montant) : '—',
            r.nb_pages ?? 0,
            r.date || '—',
        ]);

        const columns = [
            { name: 'ID', width: '70px' },
            {
                name: 'Client',
                width: '200px',
                sort: false,
                formatter: (_, row) => clientCellHtml(row.cells[3].data, row.cells[2].data),
            },
            { name: 'Document', width: '140px' },
            { name: 'Agent', width: '130px' },
            { name: 'Affectation', width: '120px' },
            { name: 'Montant', width: '100px' },
            { name: 'Pages', width: '70px' },
            {
                name: 'Statut',
                width: '100px',
                formatter: (_, row) => statusBadgeHtml(row.cells[1].data),
            },
            { name: 'Date', width: '130px' },
        ];

        const gridConfig = {
            columns,
            data: tableData,
            pagination: { limit: 10 },
            sort: true,
            search: false,
            language: {
                pagination: {
                    previous: 'Préc.',
                    next: 'Suiv.',
                    showing: 'Affichage',
                    results: () => 'lignes',
                },
                noRecordsFound: 'Aucun encodage sur cette période',
            },
            className: { table: 'table table-sm mb-0' },
        };

        if (recentGrid) {
            recentGrid.updateConfig(gridConfig).forceRender();
            return;
        }

        recentTable.innerHTML = '';
        recentGrid = new gridjs.Grid(gridConfig);
        recentGrid.render(recentTable);
    }

    async function loadReport() {
        statsEl.innerHTML = '<div class="report-loading col-12">Chargement…</div>';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch(apiUrl(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const json = await res.json();
            if (json.status !== 'success') {
                throw new Error(json.message || 'Erreur');
            }
            const data = json.data;
            if (periodLabel && data.period?.label) {
                periodLabel.textContent = data.period.label;
            }
            renderStats(data);
            renderChart(data.timeline);
            renderBreakdown(breakdownAgent, data.by_agent, true);
            renderBreakdown(breakdownDoc, data.by_doc_type, false);
            renderBreakdown(breakdownCommune, data.by_commune, false);
            if (breakdownStatus) {
                renderBreakdown(breakdownStatus, data.by_status, false);
            }
            renderRecent(data.recent_encodages);
        } catch (e) {
            statsEl.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
            if (window.iziToast) {
                iziToast.error({ title: 'Rapport', message: e.message });
            }
        }
    }

    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
    if (monthInput && !monthInput.value) {
        const d = new Date();
        monthInput.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }

    btnRefresh?.addEventListener('click', loadReport);
    dateInput?.addEventListener('change', loadReport);
    monthInput?.addEventListener('change', loadReport);

    loadReport();
})();

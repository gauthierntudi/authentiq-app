document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = () => ({
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
    });

    const tableContainer = document.getElementById('table-villes');
    const villeModalEl = document.getElementById('villeModal');
    const villeModal = new bootstrap.Modal(villeModalEl);
    const formVille = document.getElementById('formVille');
    const submitBtn = document.getElementById('villeSubmit');
    const selectProvince = document.getElementById('villeProvince');
    const modalTitle = document.getElementById('villeModalTitle');
    const btnAddVille = document.getElementById('btnAddVille');

    let gridVilles;

    function setModalTitle(isEdit) {
        if (isEdit) {
            modalTitle.innerHTML = 'Modifier une ville<br><span class="fw-normal" style="font-size: .8em;">Correction du nom de ville</span>';
        } else {
            modalTitle.innerHTML = 'Ajouter une nouvelle ville<br><span class="fw-normal" style="font-size: .8em;">Renseignez le nom et la province</span>';
        }
    }

    function loadProvinces(selectedId = null) {
        return fetch('/api/provinces')
            .then(res => res.json())
            .then(data => {
                selectProvince.innerHTML = '';
                if (data.status === 'success') {
                    data.data.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p.id_province;
                        option.textContent = p.nom;
                        if (selectedId && p.id_province == selectedId) {
                            option.selected = true;
                        }
                        selectProvince.appendChild(option);
                    });
                }
            });
    }

    function openAddModal() {
        document.getElementById('villeId').value = '';
        document.getElementById('villeNom').value = '';
        setModalTitle(false);
        loadProvinces().then(() => villeModal.show());
    }

    function loadVilles() {
        fetch('/api/villes')
            .then(res => res.json())
            .then(response => {
                if (response.status !== 'success') {
                    iziToast.error({ title: 'Erreur', message: 'Impossible de charger les villes.' });
                    return;
                }

                tableContainer.innerHTML = '';

                if (gridVilles) {
                    gridVilles.updateConfig({ data: response.data.map(mapRow) }).forceRender();
                } else {
                    gridVilles = new gridjs.Grid({
                        columns: [
                            { name: 'ID', width: '80px' },
                            { name: 'Ville', width: '200px' },
                            { name: 'Province', width: '200px' },
                            {
                                name: 'Actions',
                                width: '150px',
                                formatter: function(cell, row) {
                                    const id = row.cells[0].data;
                                    const nom = String(row.cells[1].data).replace(/'/g, "\\'");
                                    const idProvince = row.cells[3].data;

                                    return gridjs.html(`
                                        <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editVille(${id}, '${nom}', ${idProvince})">
                                            <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                        <button class="btn btn-danger btn-icon border-radius" onclick="deleteVille(${id})">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                    `);
                                },
                            },
                        ],
                        search: true,
                        sort: true,
                        pagination: { limit: 10 },
                        data: response.data.map(mapRow),
                    });
                    gridVilles.render(tableContainer);
                }
            });
    }

    function mapRow(v) {
        return [v[0], v[1], v[2], v[3]];
    }

    loadVilles();

    if (new URLSearchParams(window.location.search).get('tab') === 'ajouter') {
        openAddModal();
    }

    btnAddVille?.addEventListener('click', openAddModal);

    window.deleteVille = async function(id) {
        await AuthentiqConfirm.whenConfirmed({
            title: 'Supprimer la ville',
            text: 'Voulez-vous vraiment supprimer cette ville ?',
            danger: true,
        }, async () => {
            const res = await fetch(`/api/villes/${id}`, { method: 'DELETE', headers: apiHeaders() });
            const data = await res.json();
            if (data.status === 'success') {
                iziToast.success({ title: 'Supprimé', message: data.message });
                loadVilles();
            } else {
                iziToast.error({ title: 'Erreur', message: data.message });
            }
        });
    };

    window.editVille = function(id, nom, idProvince) {
        document.getElementById('villeId').value = id;
        document.getElementById('villeNom').value = nom;
        setModalTitle(true);
        loadProvinces(idProvince).then(() => villeModal.show());
    };

    formVille.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('villeId').value;
        const nom = document.getElementById('villeNom').value.trim();
        const province = selectProvince.value;

        if (!nom || !province) {
            iziToast.error({ backgroundColor: '#3d4153', title: 'Erreur', message: 'Veuillez remplir tous les champs.' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        const formData = new URLSearchParams();
        formData.append('id_ville', id);
        formData.append('nom', nom);
        formData.append('province', province);

        fetch('/api/villes/save', { method: 'POST', headers: apiHeaders(), body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    iziToast.success({
                        backgroundColor: '#3d4153',
                        title: 'Succès',
                        message: data.message,
                        position: 'bottomCenter',
                        theme: 'dark',
                    });
                    villeModal.hide();
                    loadVilles();
                } else {
                    iziToast.error({
                        backgroundColor: '#3d4153',
                        title: 'Erreur',
                        message: data.message,
                        position: 'bottomCenter',
                        theme: 'dark',
                    });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Enregistrer';
                document.getElementById('villeId').value = '';
                document.getElementById('villeNom').value = '';
            });
    });
});

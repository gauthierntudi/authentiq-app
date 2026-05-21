document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = (json = false) => {
        const h = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    };

    const tableContainer = document.getElementById("table-communes");
    const communeModalEl = document.getElementById('communeModal');
    const communeModal = new bootstrap.Modal(communeModalEl);
    const formCommune = document.getElementById('formCommune');
    const submitBtn = document.getElementById('communeSubmit');
    const selectVille = document.getElementById('communeVille');

    let gridCommunes; // instance Grid.js

    // Charger toutes les villes pour le select
    function loadVillesForSelect(selectedId = null) {
        fetch('/api/villes-for-select')
        .then(res => res.json())
        .then(data => {
            selectVille.innerHTML = '';
            if(data.status === 'success') {
                data.data.forEach(v => {
                    const option = document.createElement('option');
                    option.value = v.id_ville;
                    option.textContent = `${v.nom_ville} (${v.nom_province})`;
                    if(selectedId && v.id_ville == selectedId) option.selected = true;
                    selectVille.appendChild(option);
                });
            }
        });
    }

    // Charger les communes dans Grid.js
    function loadCommunes() {
        fetch('/api/communes')
        .then(res => res.json())
        .then(response => {
            if(response.status === 'success') {
                tableContainer.innerHTML = ''; // vider container

                if(gridCommunes) {
                    gridCommunes.updateConfig({ data: response.data }).forceRender();
                } else {
                    gridCommunes = new gridjs.Grid({
                        columns: [
                            { name: "ID", width: "80px" },
                            { name: "Commune", width: "200px" },
                            { 
                                name: "Ville", 
                                width: "200px",
                                formatter: function(cell, row) {
                                    return gridjs.html(row.cells[3].data); // <-- afficher le nom de la ville
                                }
                            },
                            { name: "Province", width: "200px" },
                            {
                                name: "Actions",
                                width: "150px",
                                formatter: function(cell, row) {
                                    const communeId = row.cells[0].data;
                                    const communeNom = row.cells[1].data.replace(/'/g, "\\'");
                                    const villeId = row.cells[2].data;   // ID pour modal

                                    return gridjs.html(`
                                        <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editCommune(${communeId}, '${communeNom}', ${villeId})">
                                            <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                        <button class="btn btn-danger btn-icon border-radius" onclick="deleteCommune(${communeId})">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                    `);
                                }
                            }
                        ],
                        search: true,
                        sort: true,
                        pagination: { limit: 10 },
                        data: response.data.map(c => [
                            c[0],           // id_commune
                            c[1],           // nom_commune
                            c[2],           // id_ville → pour edit
                            c[3],           // nom_ville → affichage
                            c[4]            // nom_province
                        ])
                    });
                    gridCommunes.render(tableContainer);
                }
            } else {
                iziToast.error({ title: 'Erreur', message: 'Impossible de charger les communes.' });
            }
        });
    }

    loadCommunes();

    // Supprimer commune
    window.deleteCommune = function(id) {
        iziToast.question({
            timeout: 20000,
            close: true,
            overlay: true,
            displayMode: 'once',
            backgroundColor: '#3d4153',
            id: 'question',
            zindex: 9999,
            title: 'Confirmation',
            message: 'Voulez-vous vraiment supprimer cette commune ?',
            position: 'center',
            theme: 'dark',
            buttons: [
                ['<button>Oui</button>', function (instance, toast) {
                    instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                    fetch(`/api/communes/${id}`, {
                        method: 'DELETE',
                        headers: apiHeaders()
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            iziToast.success({ title: 'Supprimé', message: data.message });
                            loadCommunes();
                        } else {
                            iziToast.error({ title: 'Erreur', message: data.message });
                        }
                    });
                }, true],
                ['<button>Non</button>', function (instance, toast) {
                    instance.hide({ transitionOut: 'fadeOut' }, toast, 'button');
                }]
            ]
        });
    };

    // Éditer commune
    window.editCommune = function(id_commune, nom_commune, id_ville) {
        document.getElementById('communeId').value = id_commune;
        document.getElementById('communeNom').value = nom_commune;

        // Charger toutes les villes pour le select et sélectionner celle correspondant à id_ville
        loadVillesForSelect(id_ville);

        communeModal.show();
    };

    // Ajouter / modifier commune via modal
    formCommune.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('communeId').value;
        const nom = document.getElementById('communeNom').value.trim();
        const ville = selectVille.value;

        if(!nom || !ville) {
            iziToast.error({ title: 'Erreur', message: 'Veuillez remplir tous les champs.' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        const formData = new URLSearchParams();
        formData.append('id_commune', id);
        formData.append('nom', nom);
        formData.append('ville', ville);

        fetch('/api/communes/save', { method: 'POST', headers: apiHeaders(), body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                iziToast.success({ title: 'Succès', message: data.message });
                communeModal.hide();
                loadCommunes();
            } else {
                iziToast.error({ title: 'Erreur', message: data.message });
            }
        })
        .catch(err => {
            console.error(err);
            iziToast.error({ title: 'Erreur', message: 'Erreur serveur' });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enregistrer';
            document.getElementById('communeId').value = '';
            document.getElementById('communeNom').value = '';
        });
    });


    const btnAddCommune = document.getElementById('btnAddCommune');

    btnAddCommune.addEventListener('click', function() {
        // Vider les champs
        document.getElementById('communeId').value = '';
        document.getElementById('communeNom').value = '';

        // Charger les villes pour le select
        loadVillesForSelect(); // aucun selectedId

        // Mettre le titre du modal à "Ajouter une commune"
        document.getElementById('communeModalTitle').textContent = "Ajouter une nouvelle commune";

        // Ouvrir le modal
        communeModal.show();
    });

});

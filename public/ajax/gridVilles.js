document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById("table-villes");
    const villeModalEl = document.getElementById('villeModal');
    const villeModal = new bootstrap.Modal(villeModalEl);
    const formVille = document.getElementById('formVille');
    const submitBtn = document.getElementById('villeSubmit');
    const selectProvince = document.getElementById('villeProvince');

    let gridVilles; // instance globale de Grid.js

    // Fonction pour charger les villes
    function loadVilles() {
        fetch('../php/getVilles.php')
        .then(res => res.json())
        .then(response => {
            if(response.status === 'success') {
                if(gridVilles) {
                    // Actualiser les données existantes
                    gridVilles.updateConfig({ data: response.data }).forceRender();
                } else {
                    // Créer l'instance Grid.js la première fois
                    gridVilles = new gridjs.Grid({
                        columns: [
                            { name: "ID", width: "80px" },
                            { name: "Ville", width: "200px" },
                            { name: "Province", width: "200px" },
                            {
                                name: "Actions",
                                width: "150px",
                                formatter: function(cell, row) {
                                    return gridjs.html(`
                                        <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editVille(${row.cells[0].data}, '${row.cells[1].data}', '${row.cells[2].data}')">
                                            <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                        <button class="btn btn-danger btn-icon border-radius" onclick="deleteVille(${row.cells[0].data})">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                    `);
                                }
                            }
                        ],
                        search: true,
                        sort: true,
                        pagination: { limit: 10 },
                        data: response.data
                    });
                    gridVilles.render(tableContainer);
                }
            } else {
                iziToast.error({ title: 'Erreur', message: 'Impossible de charger les villes.' });
            }
        });
    }

    loadVilles(); // charger au départ

    // Supprimer ville avec IziToast confirm dark
    window.deleteVille = async function(id) {
        await AuthentiqConfirm.whenConfirmed({
            title: 'Supprimer la ville',
            text: 'Voulez-vous vraiment supprimer cette ville ?',
            danger: true,
        }, async () => {
            try {
                const res = await fetch('../php/deleteVille.php', {
                    method: 'POST',
                    body: new URLSearchParams({ id_ville: id }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    iziToast.success({ title: 'Supprimé', message: data.message });
                    loadVilles();
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                }
            } catch (err) {
                console.error(err);
                iziToast.error({ title: 'Erreur', message: 'Erreur serveur' });
            }
        });
    };

    // Éditer ville
    window.editVille = function(id, nom, provinceNom) {
        document.getElementById('villeId').value = id;
        document.getElementById('villeNom').value = nom;

        fetch('../php/getProvinces.php')
        .then(res => res.json())
        .then(data => {
            selectProvince.innerHTML = '';
            if(data.status === 'success') {
                data.data.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id_province;
                    option.textContent = p.nom;
                    if(p.nom === provinceNom) option.selected = true; // province sélectionnée
                    selectProvince.appendChild(option);
                });
            }
        });

        villeModal.show();
    };

    // Ajouter / modifier ville
    formVille.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('villeId').value;
        const nom = document.getElementById('villeNom').value.trim();
        const province = selectProvince.value;

        if(!nom || !province) {
            iziToast.error({backgroundColor: '#3d4153', title: 'Erreur', message: 'Veuillez remplir tous les champs.' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        const formData = new URLSearchParams();
        formData.append('id_ville', id);
        formData.append('nom', nom);
        formData.append('province', province);

        fetch('../php/editVille.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                iziToast.success({backgroundColor: '#3d4153', title: 'Succès', message: data.message, position: 'bottomCenter',theme: 'dark' });
                villeModal.hide();
                loadVilles(); // actualisation dynamique
            } else {
                iziToast.error({backgroundColor: '#3d4153', title: 'Erreur', message: data.message, position: 'bottomCenter',theme: 'dark' });
            }
        })
        .catch(err => {
            console.error(err);
            iziToast.error({ title: 'Erreur', message: 'Erreur serveur' });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enregistrer';
            document.getElementById('villeId').value = '';
            document.getElementById('villeNom').value = '';
        });
    });
});

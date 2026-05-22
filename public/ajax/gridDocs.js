document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = (json = false) => {
        const h = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    };

    const tableContainer = document.getElementById("table-docs");
    const docModalEl = document.getElementById('docModal');
    const docModal = new bootstrap.Modal(docModalEl);
    const formDoc = document.getElementById('formDoc');
    const submitBtn = document.getElementById('docSubmit');

    let gridDocs;

    function loadDocs() {
        fetch('/api/docs')
        .then(res => res.json())
        .then(response => {
            if(response.status === 'success') {
                tableContainer.innerHTML = '';

                const mappedData = response.data.map(d => [
                    d.id_doc,
                    d.nom_doc || '',
                    d.type_doc || '',
                    d.montant || 0,
                    d.duree || 0,
                    d.validite || 'court'
                ]);

                if(gridDocs) {
                    gridDocs.updateConfig({ data: mappedData }).forceRender();
                } else {
                    gridDocs = new gridjs.Grid({
                        columns: [
                            { name: "ID", width: "60px" },
                            { name: "Nom du document", width: "200px" },
                            { name: "Type", width: "100px" },
                            { name: "Montant", width: "100px" },
                            { 
                                name: "Durée (mois)", 
                                width: "120px",
                                formatter: cell => (cell === 0 || cell === '0') ? 'À vie' : cell
                            },
                            { name: "Validité", width: "100px" },
                            {
                                name: "Actions",
                                width: "150px",
                                formatter: function(cell, row) {
                                    const docId = row.cells[0].data;
                                    const docNom = (row.cells[1].data || '').toString().replace(/'/g, "\\'").replace(/"/g, '\\"');
                                    const docType = (row.cells[2].data || '').toString();
                                    const docMontant = row.cells[3].data || 0;
                                    const docDuree = row.cells[4].data || 0;
                                    const docValidite = (row.cells[5].data || 'court').toString();

                                    return gridjs.html(`
                                        <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editDoc(${docId}, '${docNom}', '${docType}', ${docMontant}, ${docDuree}, '${docValidite}')">
                                            <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                        <button class="btn btn-danger btn-icon border-radius" onclick="deleteDoc(${docId})">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                    `);
                                }
                            }
                        ],
                        search: true,
                        sort: true,
                        pagination: { limit: 10 },
                        data: mappedData
                    });
                    gridDocs.render(tableContainer);
                }
            } else {
                iziToast.error({ title: 'Erreur', message: 'Impossible de charger les documents.' });
            }
        });
    }

    loadDocs();

    formDoc.addEventListener('submit', function(e) {
        e.preventDefault();

        const id = document.getElementById('docId').value;
        const nom = document.getElementById('docNom').value.trim();
        const type = document.getElementById('docType').value;
        const montant = parseFloat(document.getElementById('docMontant').value) || 0;
        const illimite = document.getElementById('docIllimite').checked;
        const duree = illimite ? 0 : parseInt(document.getElementById('docDuree').value) || 0;
        const validite = document.getElementById('docValidite').value;

        if(!nom || !type || !validite){
            iziToast.error({ title:'Erreur', message:'Veuillez remplir tous les champs obligatoires.' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        const formData = new URLSearchParams();
        formData.append('id_doc', id);
        formData.append('nom', nom);
        formData.append('type', type);
        formData.append('montant', montant);
        formData.append('duree', duree);
        formData.append('validite', validite);

        fetch('/api/docs/save', { method:'POST', headers: apiHeaders(), body:formData })
        .then(res=>res.json())
        .then(data=>{
            if(data.status==='success'){
                iziToast.success({ title:'Succès', message:data.message });
                docModal.hide();
                loadDocs();
            } else {
                iziToast.error({ title:'Erreur', message:data.message });
            }
        })
        .catch(err=>{
            console.error(err);
            iziToast.error({ title:'Erreur', message:'Erreur serveur' });
        })
        .finally(()=>{
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enregistrer';
            document.getElementById('docId').value='';
            document.getElementById('docNom').value='';
            document.getElementById('docType').value='free';
            document.getElementById('docMontant').value=0;
            document.getElementById('docDuree').value=0;
            document.getElementById('docValidite').value='court';
            document.getElementById('docIllimite').checked=false;
        });
    });

    window.editDoc = function(id, nom, type, montant, duree, validite){
        document.getElementById('docId').value = id;
        document.getElementById('docNom').value = nom;
        document.getElementById('docType').value = type;
        document.getElementById('docMontant').value = montant;
        document.getElementById('docDuree').value = duree;
        document.getElementById('docValidite').value = validite;
        document.getElementById('docIllimite').checked = duree === 0;

        document.getElementById('docModalTitle').textContent = "Modifier le document";
        docModal.show();
    };

    window.deleteDoc = async function(id){
        await AuthentiqConfirm.whenConfirmed({
            title: 'Supprimer le document',
            text: 'Voulez-vous vraiment supprimer ce document ?',
            danger: true,
        }, async () => {
            const res = await fetch(`/api/docs/${id}`, { method: 'DELETE', headers: apiHeaders() });
            const data = await res.json();
            if (data.status === 'success') {
                iziToast.success({ title: 'Supprimé', message: data.message });
                loadDocs();
            } else {
                iziToast.error({ title: 'Erreur', message: data.message });
            }
        });
    };

    document.getElementById('btnAddDoc').addEventListener('click', function(){
        document.getElementById('docId').value='';
        document.getElementById('docNom').value='';
        document.getElementById('docType').value='free';
        document.getElementById('docMontant').value=0;
        document.getElementById('docDuree').value=0;
        document.getElementById('docValidite').value='court';
        document.getElementById('docIllimite').checked=false;
        document.getElementById('docModalTitle').textContent = "Ajouter un nouveau document";
        docModal.show();
    });
});
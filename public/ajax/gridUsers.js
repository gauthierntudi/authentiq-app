document.addEventListener('DOMContentLoaded', function() {

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiHeaders = (json = false) => {
        const h = { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    };

    const userModalEl = document.getElementById('userModal');
    if (!userModalEl) return;
    const userModal = new bootstrap.Modal(userModalEl);

    const formUser = document.getElementById('formUser');
    const submitBtn = document.getElementById('userSubmit');
    const selectProvince = document.getElementById('userProvince');
    const selectVille = document.getElementById('userVille');
    const selectCommune = document.getElementById('userCommune');
    const userPhotoInput = document.getElementById('userPhoto');
    const previewPhoto = document.getElementById('previewPhoto');
    const photoDropZone = document.getElementById('photoDropZone');

    const cropperModalEl = document.getElementById('cropperModal');
    const cropperModal = new bootstrap.Modal(cropperModalEl);
    const cropperImage = document.getElementById('cropperImage');
    const cropperValidate = document.getElementById('cropperValidate');

    const tableContainer = document.getElementById("table-users");
    const cropperPreviewEl = cropperModalEl?.querySelector('.cropper-preview-circle');
    let gridUsers;
    let cropper = null;
    let croppedBlob = null;
    let cropperObjectUrl = null;

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

        cropperModalEl.addEventListener('shown.bs.modal', function() {
            initCropper();
        }, { once: true });
    }

    if (photoDropZone) {
        photoDropZone.addEventListener('click', () => userPhotoInput.click());
        photoDropZone.addEventListener('dragover', e => {
            e.preventDefault();
            photoDropZone.classList.add('dragover');
        });
        photoDropZone.addEventListener('dragleave', e => {
            e.preventDefault();
            photoDropZone.classList.remove('dragover');
        });
        photoDropZone.addEventListener('drop', e => {
            e.preventDefault();
            photoDropZone.classList.remove('dragover');
            if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
        });
    }

    if (userPhotoInput) {
        userPhotoInput.addEventListener('change', e => {
            if (e.target.files[0]) handleFile(e.target.files[0]);
            e.target.value = '';
        });
    }

    cropperModalEl?.querySelectorAll('[data-crop-action]').forEach(btn => {
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

    if (cropperValidate) {
        cropperValidate.addEventListener('click', () => {
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

                cropperValidate.disabled = false;
                cropperValidate.innerHTML = defaultLabel;
            }, 'image/jpeg', 0.92);
        });
    }

    // --- Load provinces / villes / communes ---
    function loadProvinces(selectedId=null){
        if(!selectProvince) return;
        fetch('/api/provinces').then(r=>r.json()).then(data=>{
            selectProvince.innerHTML='';
            if(data.status==='success'){
                data.data.forEach(p=>{
                    const opt = document.createElement('option');
                    opt.value = p.id_province;
                    opt.textContent = p.nom;
                    if(selectedId && p.id_province==selectedId) opt.selected=true;
                    selectProvince.appendChild(opt);
                });
            }
        });
    }

    function loadVilles(provinceId = null, selectedVilleId = null, callback = null) {
        if (!selectVille) return;
        selectVille.innerHTML = '';
        if (!provinceId) {
            if(callback) callback();
            return;
        }

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
                    if(callback) callback();
                }
            })
            .catch(err => console.error('Erreur loadVilles', err));
    }

    function loadCommunes(villeId = null, selectedCommuneId = null) {
        if (!selectCommune) return;
        selectCommune.innerHTML = '';
        if (!villeId) return;

        fetch(`/api/communes-by-ville?id_ville=${villeId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const villeName = selectVille.options[selectVille.selectedIndex]?.text || 'une ville';
                    
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = `Sélectionner commune de ${villeName}`;
                    defaultOpt.selected = true;
                    defaultOpt.disabled = true;
                    selectCommune.appendChild(defaultOpt);
                    
                    data.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id_commune;
                        opt.textContent = c.nom;
                        if(selectedCommuneId && c.id_commune == selectedCommuneId) opt.selected = true;
                        selectCommune.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Erreur loadCommunes', err));
    }

    selectProvince.addEventListener('change', e => {
        selectCommune.innerHTML = '';
        document.getElementById('userAffectation').value = '';
        loadVilles(e.target.value);
    });

    selectVille.addEventListener('change', e => {
        document.getElementById('userAffectation').value = '';
        if(e.target.value) {
            loadCommunes(e.target.value);
        } else {
            selectCommune.innerHTML = '';
        }
    });

    selectCommune.addEventListener('change', e => {
        const affectationInput = document.getElementById('userAffectation');
        if(e.target.value) {
            const communeName = e.target.options[e.target.selectedIndex]?.text || '';
            affectationInput.value = communeName;
        } else {
            affectationInput.value = '';
        }
    });

    // --- Load users ---
    function loadUsers(){
        if(!tableContainer) return;
        fetch('/api/users')
            .then(r=>r.json())
            .then(resp=>{
                console.log('Resp getUsers.php:', resp);
                if(resp.status==='success'){
                    tableContainer.innerHTML='';
                    const mappedData = resp.data.map(u=>[
                        u.id_user,
                        u.photo||'',
                        u.nom_complet,
                        u.tel,
                        u.email,
                        u.role,
                        u.nom_province||'',
                        u.nom_ville||'',
                        u.nom_commune||'',
                        u.affectation||'',
                        u.id_province||'',
                        u.id_ville||'',
                        u.id_commune||''
                    ]);
                    if(gridUsers){
                        gridUsers.updateConfig({data:mappedData}).forceRender();
                    } else {
                        gridUsers = new gridjs.Grid({
                            columns:[
                                {name:"ID", width:"60px"},
                                {name:"Photo", width:"80px", formatter:(cell)=>{
                                    const photoPath = cell || 'assets/images/user.jpg';
                                    return gridjs.html(`<img src="${photoPath}" style="width:35px; height:35px; border-radius:50%; object-fit:cover;">`);
                                }},
                                {name:"Nom complet", width:"200px"},
                                {name:"Téléphone", width:"120px"},
                                {name:"Email", width:"200px"},
                                {name:"Rôle", width:"100px"},
                                {name:"Province", width:"120px"},
                                {name:"Ville", width:"120px"},
                                {name:"Commune", width:"120px"},
                                {name:"Affectation", width:"150px"},
                                {name:"Actions", width:"150px", formatter:(cell,row)=>{
                                    const id=row.cells[0].data;
                                    const photo=row.cells[1].data||'';
                                    const nom=row.cells[2].data.replace(/'/g,"\\'");
                                    const tel=row.cells[3].data||'';
                                    const email=row.cells[4].data||'';
                                    const role=row.cells[5].data||'';
                                    const affectation=row.cells[9].data||'';
                                    const provinceId=row.cells[10].data||'';
                                    const villeId=row.cells[11].data||'';
                                    const communeId=row.cells[12].data||'';
                                    
                                    return gridjs.html(`
                                        <button class="btn btn-secondary btn-icon me-1 border-radius" onclick="editUser(${id},'${photo}','${nom}','${tel}','${email}','${role}','${provinceId}','${villeId}','${communeId}','${affectation}')">
                                            <iconify-icon icon="solar:pen-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                        <button class="btn btn-danger btn-icon border-radius" onclick="deleteUser(${id})">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone" style="font-size:1.4em"></iconify-icon>
                                        </button>
                                    `);
                                }},
                                {name:"id_province", hidden:true},
                                {name:"id_ville", hidden:true},
                                {name:"id_commune", hidden:true}
                            ],
                            search:true, sort:true, pagination:{limit:10}, data:mappedData
                        });
                        gridUsers.render(tableContainer);
                    }
                } else {
                    iziToast.error({title:'Erreur', message:resp.message||'Impossible de charger les utilisateurs'});
                }
            })
            .catch(err=>{
                console.error('Erreur fetch:', err);
                iziToast.error({title:'Erreur', message:'Erreur de connexion au serveur'});
            });
    }

    loadUsers();

    // --- Add / Edit user ---
    if(formUser && submitBtn){
        formUser.addEventListener('submit', e=>{
            e.preventDefault();
            const formData=new FormData();
            formData.append('id_user', document.getElementById('userId').value);
            formData.append('nom_complet', document.getElementById('userNom').value);
            formData.append('tel', document.getElementById('userTel').value);
            formData.append('email', document.getElementById('userEmail').value);
            formData.append('password', document.getElementById('userPassword').value);
            formData.append('role', document.getElementById('userRole').value);
            formData.append('id_province', selectProvince.value);
            formData.append('id_ville', selectVille.value);
            formData.append('id_commune', selectCommune.value);
            formData.append('affectation', document.getElementById('userAffectation').value);
            
            // IMPORTANT: Envoyer le blob croppé
            if(croppedBlob) {
                formData.append('photo', croppedBlob, 'photo.jpg');
                console.log('Photo envoyée:', croppedBlob);
            }

            submitBtn.disabled=true;
            submitBtn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

            fetch('/api/users/save',{method:'POST', headers: apiHeaders(), body:formData})
                .then(r=>r.json())
                .then(data=>{
                    if(data.status==='success'){
                        iziToast.success({title:'Succès', message:data.message});
                        userModal.hide(); 
                        croppedBlob=null; 
                        previewPhoto.style.display='none';
                        loadUsers();
                    } else iziToast.error({title:'Erreur', message:data.message});
                }).finally(()=>{
                    submitBtn.disabled=false;
                    submitBtn.innerHTML='Enregistrer';
                });
        });
    }

    // --- Edit user ---
    window.editUser = function(id, photo, nom, tel, email, role, provinceId, villeId, communeId, affectation){
        document.getElementById('userId').value = id;
        document.getElementById('userNom').value = nom;
        document.getElementById('userTel').value = tel;
        document.getElementById('userEmail').value = email;
        document.getElementById('userPassword').value = '';
        document.getElementById('userRole').value = role;
        document.getElementById('userAffectation').value = affectation;

        // Réinitialiser le blob croppé
        croppedBlob = null;

        if(previewPhoto){
            previewPhoto.src = photo || 'assets/images/user.jpg';
            previewPhoto.style.display = 'block';
        }

        selectVille.innerHTML = '';
        selectCommune.innerHTML = '';

        loadProvinces(provinceId);
        
        if(provinceId) {
            loadVilles(provinceId, villeId, () => {
                if(villeId) {
                    loadCommunes(villeId, communeId);
                }
            });
        }

        document.getElementById('userModalTitle').textContent = "Modifier l'utilisateur";
        userModal.show();
    };

    // --- Delete user ---
    window.deleteUser = function(id){
        iziToast.question({
            timeout:20000, close:true, overlay:true, displayMode:'once',
            id:'question-user', zindex:9999,backgroundColor: '#3d4153',
            title:'Confirmation', message:'Voulez-vous vraiment supprimer cet utilisateur ?',
            position:'center', theme:'dark',
            buttons:[
                ['<button>Oui</button>', function(instance, toast){
                    instance.hide({transitionOut:'fadeOut'}, toast,'button');
                    fetch(`/api/users/${id}`,{method:'DELETE', headers: apiHeaders()})
                        .then(r=>r.json()).then(data=>{
                            if(data.status==='success'){ 
                                iziToast.success({title:'Supprimé', message:data.message}); 
                                loadUsers(); 
                            } else iziToast.error({title:'Erreur', message:data.message});
                        });
                }, true],
                ['<button>Non</button>', function(instance, toast){
                    instance.hide({transitionOut:'fadeOut'}, toast,'button');
                }]
            ]
        });
    };

    // --- Bouton Ajouter user ---
    const btnAddUser = document.getElementById('btnAddUser');
    if(btnAddUser){
        btnAddUser.addEventListener('click', ()=>{
            if(formUser) formUser.reset();
            croppedBlob=null; 
            if(previewPhoto) previewPhoto.style.display='none';
            
            loadProvinces(); 
            selectVille.innerHTML=''; 
            selectCommune.innerHTML='';

            document.getElementById('userModalTitle').textContent = "Ajouter un nouvel utilisateur";
            userModal.show();
        });
    }
});
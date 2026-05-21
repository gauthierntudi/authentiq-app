document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.card-body form');
    const button = form.querySelector('button[type="submit"]');
    const spinnerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>`;

    const provinceSelect = document.getElementById('province');

    // 1️⃣ Charger les provinces dynamiquement
    fetch('../php/getProvinces.php')
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                provinceSelect.innerHTML = '<option selected>Sélectionner province</option>';
                data.data.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province.id_province;
                    option.textContent = province.nom;
                    provinceSelect.appendChild(option);
                });
            } else {
                iziToast.error({ title: 'Erreur', message: 'Impossible de récupérer les provinces' });
            }
        })
        .catch(err => {
            console.error(err);
            iziToast.error({ title: 'Erreur', message: 'Erreur réseau lors du chargement des provinces' });
        });

    // 2️⃣ Gestion du formulaire AJAX
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const nomVille = document.getElementById('nomVille').value.trim();
        const province = provinceSelect.value;

        if(!nomVille || province === "Sélectionner province") {
            iziToast.error({ title: 'Erreur', message: 'Veuillez remplir tous les champs.' });
            return;
        }

        button.disabled = true;
        button.innerHTML = spinnerHTML + 'Soumission...';

        const formData = new FormData();
        formData.append('nomVille', nomVille);
        formData.append('province', province);

        fetch('../php/addVille.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    iziToast.success({ title: 'Succès', message: data.message });
                    form.reset();
                } else {
                    iziToast.error({ title: 'Erreur', message: data.message });
                }
            })
            .catch(err => {
                console.error(err);
                iziToast.error({ title: 'Erreur', message: 'Erreur réseau ou serveur' });
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Soumettre';
            });
    });
});
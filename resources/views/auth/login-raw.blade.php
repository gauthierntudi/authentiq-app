<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <title>Log In | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta content="Authentiq" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/ico.png">

    <!-- Theme Config Js -->
    <script src="assets/js/config.js"></script>

    <!-- Vendor css -->
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css -->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css'>

    <!-- Assurez-vous d’inclure iziToast -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>

    <style>
    .custom-bg--background-container {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #fff;
        }

        .custom-bg--overlay {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: #0a0000;
          background: linear-gradient(180deg, rgba(0, 0, 0, 0.5) 0%, rgba(0, 0, 0, 1) 100%);
          z-index: 1;
        }

        .custom-bg--image-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 200%; /* Double hauteur pour permettre le défilement */
            z-index: 0;
            animation: custom-bg--scrollAlternate 120s linear infinite;
        }

        .custom-bg--image-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 20px;
            width: 100%;
            height: 50%; /* Chaque ligne occupe la moitié du conteneur */
            padding: 0 20px;
        }

        .custom-bg--image-row img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 25px;
            transform: rotate(15deg);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0);
            transition: transform 0.3s ease;
        }

        .custom-bg--image-row img:hover {
            transform: rotate(15deg) scale(1.05);
            z-index: 10;
        }

        .custom-bg--content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
        }        

        .custom-bg--pulse {
            animation: pulseEffect 2s infinite;
        }

        @keyframes pulseEffect {
            0% {
                transform: scale(1) rotate(15deg);
            }
            50% {
                transform: scale(1.014) rotate(15deg);
            }
            100% {
                transform: scale(1) rotate(15deg);
            }
        }

        .custom-bg--border-animate {
            border: 5px solid transparent;
            animation: borderColorChange 10s infinite alternate;
        }

        @keyframes borderColorChange {
            0% {
                border-color: #0eedee;
            }
            20% {
                border-color: #5474f8;
            }
            40% {
                border-color: #04cdff;
            }
            60% {
                border-color: #0053c3;
            }
            80% {
                border-color: #adaeb0;
            }
            100% {
                border-color: #22b396;
            }
        }

        /* Animation de défilement corrigée */
        @keyframes custom-bg--scrollAlternate {
            0% {
                transform: translateY(0%);
            }
            50% {
                transform: translateY(-50%);
            }
            100% {
                transform: translateY(0%);
            }
        }

        /* Indicateur de direction */
        .direction-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            z-index: 3;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .direction-arrow {
            font-size: 1.5rem;
            animation: bounce 1s infinite alternate;
        }

        @keyframes bounce {
            0% {
                transform: translateY(0);
            }
            100% {
                transform: translateY(-5px);
            }
        }


        /* Container général */
        .custom-switch {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px; /* Espace entre le toggle et le texte */
            cursor: pointer;
        }

        /* Masquer le checkbox original mais le rendre fonctionnel */
        .custom-switch-input {
            display: none;
        }

        /* Le slider du toggle */
        .custom-switch-slider {
            position: relative;
            width: 50px;
            height: 24px;
            background-color: #ccc;
            border-radius: 12px;
            transition: background-color 0.3s;
        }

        /* Le cercle du slider */
        .custom-switch-slider::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background-color: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }

        /* Lorsqu'un toggle est activé (checkbox checked) */
        .custom-switch-input:checked + .custom-switch-slider {
            background-color: #4caf50; /* Couleur active */
        }

        .custom-switch-input:checked + .custom-switch-slider::before {
            transform: translateX(26px); /* Déplace le cercle vers la droite */
        }

        /* Classe par défaut - couleur de fond lorsque l'input n'est pas sélectionné */
        .form-control {
          border: 2px solid #40465e;
          border-radius: 15px;
          color: #fff!important;
          background-color: #373a46; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .form-control:focus {
          border: 2px solid #0eedee!important;
          background-color: #373a46!important; /* Couleur de fond par défaut */
          transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Label pour le texte */
        .custom-switch-label {
            font-size: 14px;
            color: #fff;
            cursor: pointer;
            transition: color 0.3s ease;
            user-select: none; /* Empêcher la sélection du texte */
        }

        /* Changer la couleur du texte lorsque le toggle est activé */
        .custom-switch-input:checked ~ .custom-switch-label {
            color: #4caf50; /* Couleur du texte quand activé */
            font-weight: bold;
        }

        /* Optionnel : effet hover */
        .custom-switch:hover .custom-switch-slider {
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        /* Animation supplémentaire pour l'état actif */
        .custom-switch-input:checked + .custom-switch-slider {
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.5);
        }

        /* Exemple de style pour un état désactivé */
        .custom-switch.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .custom-switch.disabled .custom-switch-label {
            cursor: not-allowed;
        }
</style>
</head>

<body>

<div class="custom-bg--background-container">
  <div class="custom-bg--overlay"></div>

    <div class="custom-bg--image-container">
        <!-- Première rangée d'images -->
        <div class="custom-bg--image-row">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" alt="Image 3">
            <img src="bg/bg012.png" class="custom-bg--pulse" alt="Image 3">
            <img src="bg/bg013.png" alt="Image 3">
            <img src="bg/bg014.png" alt="Image 3">
            <img src="bg/bg015.png" alt="Image 3">
            <img src="bg/bg016.png" alt="Image 3">
            <img src="bg/bg06.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
            <img src="bg/bg01.png" alt="Image 1">
            <img src="bg/bg02.png" alt="Image 2">
            <img src="bg/bg03.png" alt="Image 3">
            <img src="bg/bg04.png" alt="Image 3">
            <img src="bg/bg05.png" alt="Image 3">
            <img src="bg/bg06.png" alt="Image 3">
            <img src="bg/bg07.png" alt="Image 3">
            <img src="bg/bg08.png" alt="Image 3">
            <img src="bg/bg09.png" alt="Image 3">
            <img src="bg/bg010.png" alt="Image 3">
            <img src="bg/bg011.png" class="custom-bg--pulse custom-bg--border-animate" alt="Image 3">
        </div>
        
        <!-- Deuxième rangée d'images (différentes) -->
        <div class="custom-bg--image-row"></div>
    </div>
  <div class="custom-bg--content">
    <div class="d-flex min-vh-100 justify-content-center align-items-center">
        <div class="row g-0 justify-content-center w-100 m-xxl-5 px-xxl-4 m-3">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="card overflow-hidden text-center h-100 p-xxl-5 p-4 mb-0" style="border-radius: 50px;background: #242630;">
                    <a href="accueil" class="auth-brand mb-3">
                        <img src="assets/images/logo-auth03.png" alt="dark logo" height="110" class="logo-dark">
                        <img src="assets/images/logo-auth03.png" alt="logo light" height="110" class="logo-light">
                    </a>

                    <h3 class="fw-semibold mb-2 text-white">Connectez vous</h3>

                    <p class="text-muted mb-4">Entrez votre adresse e-mail et votre mot de passe</p>

                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <a class="btn btn-soft-danger avatar-lg disabled-link" onclick="return false;" style="border-radius: 50px;">
                            <i class="fi fi-rr-lock fs-24"></i>
                        </a>
                        <a class="btn btn-soft-success avatar-lg disabled-link" onclick="return false;" style="border-radius: 50px;">
                            <i class="fi fi-rr-features-alt fs-24"></i>
                        </a>
                        <a class="btn btn-soft-primary avatar-lg disabled-link" onclick="return false;" style="border-radius: 50px;">
                            <i class="fi fi-rr-user-trust fs-24"></i>
                        </a>
                        <a class="btn btn-soft-info avatar-lg disabled-link" onclick="return false;" style="border-radius: 50px;">
                            <i class="fi fi-rr-badge fs-24"></i>
                        </a>
                    </div>

                    <p class="fs-13 fw-semibold"></p>

                    <form action="#!" id="loginForm" class="text-start mb-3">
                        <div class="mb-3">
                            <label class="form-label text-white" for="email">E-mail ou Téléphone</label>
                            <input type="text" id="email" name="email" class="form-control form-control-lg bg-input" placeholder="E-mail ou Téléphone">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white" for="password">Mot de passe</label>
                            <input type="password" id="password" class="form-control form-control-lg" placeholder="Mot de passe">
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <!-- Version 1: Label englobant tout -->
                            <label class="custom-switch">
                                <input type="checkbox" id="custom-toggle-1" class="custom-switch-input">
                                <span class="custom-switch-slider"></span>
                                <span class="custom-switch-label">Se souvenir de moi</span>
                            </label>
                        </div>

                        <div class="text-center">
                            <button class="btn btn-primary btn-lg w-75 fw-bold" type="submit" style="border-radius:15px;">Se connecter</button>
                        </div>
                    </form>

                    <p class="text-danger fs-14 mb-4">
                        <!-- Don't have an account? <a href="auth-register.php" class="fw-semibold text-dark ms-1">Sign Up !</a> -->
                    </p>

                    <p class="mt-auto mb-0">
                        <script>document.write(new Date().getFullYear())</script> © Authentiq
                    </p>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

    

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>
    
    <script>
        document.querySelector('.disabled-link').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });

        // Sélectionner tous les inputs avec la classe bg-input
        const inputs = document.querySelectorAll('.bg-input');

        // Ajouter l'événement focus
        inputs.forEach(input => {
          input.addEventListener('focus', () => {
            input.classList.add('bg-input-focus'); // Ajoute la classe au focus
          });

          // Ajouter l'événement blur
          input.addEventListener('blur', () => {
            input.classList.remove('bg-input-focus'); // Enlève la classe au blur
          });
        });


        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('#email');
            
            // Fonction pour détecter si la valeur est purement numérique
            function isNumeric(value) {
                // Retirer les espaces et caractères spéciaux autorisés pour les numéros de téléphone
                const cleanValue = value.replace(/[\s\-\(\)\+]/g, '');
                return /^\d+$/.test(cleanValue) && cleanValue.length > 0;
            }
            
            // Fonction pour changer le type du champ
            function updateInputType() {
                const value = emailInput.value.trim();
                
                if (!value) {
                    // Si le champ est vide, remettre en text
                    emailInput.type = 'text';
                    return;
                }
                
                if (isNumeric(value)) {
                    // Si uniquement des chiffres -> type tel
                    emailInput.type = 'tel';
                } else {
                    // Si contient des lettres ou alphanumerique -> type email
                    emailInput.type = 'email';
                }
            }
            
            // Écouter les événements de saisie
            emailInput.addEventListener('input', updateInputType);
            
            // Écouter aussi le changement (pour le copier-coller)
            emailInput.addEventListener('change', updateInputType);
            
            // Vérifier aussi au focus (au cas où le champ est prérempli)
            emailInput.addEventListener('focus', updateInputType);
            
            // Vérification initiale au chargement de la page (si prérempli)
            if (emailInput.value) {
                updateInputType();
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('#loginForm');
    const loginEmail = document.querySelector('#email');
    const loginPassword = document.querySelector('#password');
    const submitButton = loginForm.querySelector('button[type="submit"]');
    
    // Variable pour gérer l'état de chargement
    let isSubmitting = false;
    
    // Sauvegarder le texte original du bouton
    const originalButtonText = submitButton.innerHTML;
    
    // Fonction pour désactiver/activer le bouton avec spinner
    function toggleSubmitButton(loading){
        if(loading){
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connexion...';
        } else {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    }
    
    loginForm.addEventListener('submit', function(e){
        e.preventDefault();
        
        // Empêcher les soumissions multiples
        if(isSubmitting) return;
        
        const loginValue = loginEmail.value.trim();
        const password = loginPassword.value.trim();
        
        if(!loginValue || !password){
            iziToast.error({
                title:'Erreur', message:'Email ou mot de passe manquant',
                position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
            });
            return;
        }
        
        // Activer l'état de chargement
        isSubmitting = true;
        toggleSubmitButton(true);
        
        fetch('{{ url('/api/auth/login') }}', {
            method:'POST',
            credentials:'same-origin',
            headers:{
                'Content-Type':'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body:`login=${encodeURIComponent(loginValue)}&password=${encodeURIComponent(password)}`
        })
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success'){
                sessionStorage.setItem('login_user_id', data.user_id);
                window.location.replace('{{ url('/connexion/otp') }}');
            } else {
                iziToast.error({
                    title:'Erreur', message:data.message,
                    position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
                });
                // Réactiver le bouton en cas d'erreur
                isSubmitting = false;
                toggleSubmitButton(false);
            }
        })
        .catch(err=>{
            console.error(err);
            iziToast.error({
                title:'Erreur', message:'Erreur serveur',
                position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
            });
            // Réactiver le bouton en cas d'erreur
            isSubmitting = false;
            toggleSubmitButton(false);
        });
    });
});
    </script>

</body>

</html>
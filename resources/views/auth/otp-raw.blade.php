<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <title>Auth. 2FA | Authentiq</title>
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


        .otp-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 18px;
            background: #373a46;
            color: #fff;
            border: 2px solid #40465e;
            border-radius: 12px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .otp-input:focus {
            border-color: #0eedee;
            box-shadow: 0 0 5px rgba(84, 116, 248, 0.3);
        }
        
        .otp-input.filled {
            border-color: #0eedee;
            background-color: #373a46;
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
                <div class="card overflow-hidden text-center h-100 p-xxl-4 p-3 mb-0" style="border-radius: 50px;background: #242630;">
                    <a href="accueil" class="auth-brand mb-3">
                        <img src="assets/images/logo-auth03.png" alt="dark logo" height="110" class="logo-dark">
                        <img src="assets/images/logo-auth03.png" alt="logo light" height="110" class="logo-light">
                    </a>

                    <h3 class="fw-semibold mb-2 text-white">Authentification <span style="color:#5474f8;font-weight: 700;">2FA</span></h3>

                    <p class="text-muted mb-4">Entrez le code OTP que vous avez reçu</p>

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

                    <form action="#!" id="otpForm" class="text-start mb-3">
                        
                        <div class="otp-container mb-3">
                            <input type="text" id="otp-1" class="otp-input" maxlength="1" data-index="1">
                            <input type="text" id="otp-2" class="otp-input" maxlength="1" data-index="2">
                            <input type="text" id="otp-3" class="otp-input" maxlength="1" data-index="3">
                            <input type="text" id="otp-4" class="otp-input" maxlength="1" data-index="4">
                            <input type="text" id="otp-5" class="otp-input" maxlength="1" data-index="5">
                            <input type="text" id="otp-6" class="otp-input" maxlength="1" data-index="6">
                        </div>

                        <div class="text-center mb-3">
                            <button class="btn btn-primary btn-lg w-75 fw-bold" type="submit" style="border-radius:15px;">Continuer</button>
                        </div>
                        <p class="mb-0 text-center text-white">Vous n'avez pas reçu le code ? <a href="#!" class="link-primary resendOtpUser fw-bold">Renvoyer</a></p>
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
    </script>
    <script>
        // Attacher l'événement paste à CHAQUE input
        document.querySelectorAll('.otp-input').forEach((input, index, inputs) => {
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('Text');
                handlePaste(pastedData, inputs);
            });
        });

        // Fonction pour gérer le collage
        function handlePaste(pastedData, inputs) {
            // Nettoyer les données : garder seulement les chiffres
            const digitsOnly = pastedData.replace(/\D/g, '');
            
            if (digitsOnly.length >= 6) {
                // Prendre les 6 premiers chiffres
                const sixDigits = digitsOnly.substring(0, 6).split('');
                
                // Remplir chaque case
                sixDigits.forEach((digit, index) => {
                    if (inputs[index]) {
                        inputs[index].value = digit;
                        inputs[index].classList.add('filled');
                    }
                });
                
                // Focus sur la dernière case
                inputs[5].focus();
            } else if (digitsOnly.length > 0) {
                // Si moins de 6 chiffres, remplir à partir de la case courante
                const currentIndex = parseInt(document.activeElement.getAttribute('data-index')) - 1;
                
                digitsOnly.split('').forEach((digit, i) => {
                    const targetIndex = currentIndex + i;
                    if (inputs[targetIndex]) {
                        inputs[targetIndex].value = digit;
                        inputs[targetIndex].classList.add('filled');
                    }
                });
                
                // Focus sur la prochaine case disponible
                const nextIndex = Math.min(currentIndex + digitsOnly.length, 5);
                inputs[nextIndex].focus();
            }
        }

        // Fonction pour déplacer le focus automatiquement
        function moveFocus(currentIndex, event) {
            const inputs = document.querySelectorAll('.otp-input');
            
            // Si un chiffre est entré (et pas Backspace)
            if (event.key && event.key !== 'Backspace' && event.key !== 'Delete') {
                // Vérifier si c'est un chiffre
                if (/[0-9]/.test(event.key)) {
                    // Mettre à jour visuellement la case actuelle
                    event.target.classList.add('filled');
                    
                    // Aller à la case suivante si on n'est pas à la dernière
                    if (currentIndex < inputs.length) {
                        const nextInput = inputs[currentIndex]; // Index commence à 0
                        if (nextInput) {
                            nextInput.focus();
                        }
                    }
                }
            }
            
            // Gestion de la suppression avec Backspace
            if (event.key === 'Backspace') {
                // Si la case actuelle est vide, aller à la précédente
                if (event.target.value === '' && currentIndex > 1) {
                    const prevInput = inputs[currentIndex - 2]; // -2 car index commence à 0
                    if (prevInput) {
                        prevInput.focus();
                    }
                } else {
                    // Si la case contient un chiffre, le vider et rester sur la même case
                    event.target.value = '';
                    event.target.classList.remove('filled');
                }
            }
        }

        // Fonction pour autoriser uniquement les chiffres
        document.querySelectorAll('.otp-input').forEach(input => {
            input.addEventListener('keydown', (event) => {
                // Autoriser Ctrl+V (paste)
                if ((event.ctrlKey || event.metaKey) && event.key === 'v') {
                    return; // Laisser le paste se faire normalement
                }
                
                // Autoriser seulement les chiffres, Backspace, Tab, flèches
                if (!/[0-9]/.test(event.key) && 
                    event.key !== "Backspace" && 
                    event.key !== "Delete" &&
                    event.key !== "Tab" &&
                    !event.key.includes("Arrow")) {
                    event.preventDefault();
                }
            });
            
            // Événement input pour gérer la saisie
            input.addEventListener('input', (event) => {
                const value = event.target.value;
                
                // Si la valeur dépasse 1 caractère (peut arriver avec certains collages)
                if (value.length > 1) {
                    // Prendre seulement le dernier caractère (utile pour mobile)
                    event.target.value = value.charAt(value.length - 1);
                }
                
                // Mettre à jour la classe filled
                if (event.target.value) {
                    event.target.classList.add('filled');
                } else {
                    event.target.classList.remove('filled');
                }
            });
            
            // Événement keyup pour le déplacement du focus
            input.addEventListener('keyup', (event) => {
                const currentIndex = parseInt(event.target.getAttribute('data-index'));
                moveFocus(currentIndex, event);
            });
        });

        // Fonction pour gérer le focus et sélectionner le texte
        document.querySelectorAll('.otp-input').forEach(input => {
            input.addEventListener('focus', function() {
                setTimeout(() => this.select(), 0);
            });
            
            input.addEventListener('click', function() {
                this.select();
            });
        });

        // Gestion du collage global (au cas où)
        document.addEventListener('paste', (e) => {
            // Vérifier si un input OTP est focused
            if (e.target.classList.contains('otp-input')) {
                e.preventDefault();
                const inputs = document.querySelectorAll('.otp-input');
                const pastedData = e.clipboardData.getData('Text');
                handlePaste(pastedData, inputs);
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const otpForm = document.querySelector('#otpForm');
    const otpInputs = document.querySelectorAll('.otp-input');
    const resendLink = document.querySelector('.resendOtpUser');
    const submitButton = otpForm.querySelector('button[type="submit"]');

    const userId = sessionStorage.getItem('login_user_id');
    if(!userId){
        iziToast.error({
            title:'Erreur', message:'Session expirée, veuillez vous reconnecter',
            position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
        });
        setTimeout(()=> window.location.href='{{ url('/accueil') }}', 1500);
        return;
    }

    // Variables pour gérer l'état de chargement
    let isSubmitting = false;
    let isResending = false;

    function getOtpValue(){
        let code='';
        otpInputs.forEach(input=>code+=input.value);
        return code;
    }

    function clearOtpInputs(){
        otpInputs.forEach(input=>input.value='');
        otpInputs[0].focus();
    }

    // Fonction pour désactiver/activer le bouton de soumission avec spinner
    function toggleSubmitButton(loading){
        if(loading){
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Vérification...';
        } else {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Continuer';
        }
    }

    // Fonction pour désactiver/activer le lien de renvoi avec spinner
    function toggleResendLink(loading){
        if(loading){
            resendLink.style.pointerEvents = 'none';
            resendLink.style.opacity = '0.6';
            resendLink.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Envoi en cours...';
        } else {
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
            resendLink.innerHTML = 'Renvoyer le code';
        }
    }

    // --- OTP submit ---
    otpForm.addEventListener('submit', function(e){
        e.preventDefault();
        
        // Empêcher les soumissions multiples
        if(isSubmitting) return;
        
        const otpCode = getOtpValue();
        if(otpCode.length !== 6){
            iziToast.error({
                title:'Erreur', message:'Veuillez entrer un code OTP à 6 chiffres',
                position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
            });
            return;
        }

        isSubmitting = true;
        toggleSubmitButton(true);

        fetch('{{ url('/api/auth/verify-otp') }}',{
            method:'POST',
            headers:{
                'Content-Type':'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body:`user_type=user&user_id=${userId}&code_otp=${otpCode}`
        })
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success'){
                iziToast.success({
                    title:'Succès', message:'Connexion réussie !',
                    position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
                });
                // Garder le bouton désactivé pendant la redirection
                window.location.replace('{{ url('/dashboard') }}');
            } else {
                iziToast.error({
                    title:'Erreur', message:data.message || 'OTP incorrect',
                    position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
                });
                clearOtpInputs();
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
            isSubmitting = false;
            toggleSubmitButton(false);
        });
    });

    function requestLoginOtp(isAutoSend){
        if(isResending) return Promise.resolve();

        isResending = true;
        toggleResendLink(true);

        return fetch('{{ url('/api/auth/send-otp') }}',{
            method:'POST',
            credentials:'same-origin',
            headers:{
                'Content-Type':'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body:`user_id=${userId}`
        })
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success'){
                const msg = data.message || (isAutoSend ? 'Code envoyé' : 'Code renvoyé');
                iziToast.success({
                    title:'Succès', message: msg,
                    position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
                });
                clearOtpInputs();
            } else {
                iziToast.error({
                    title:'Erreur', message:data.message || 'Impossible d\'envoyer le code',
                    position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
                });
            }
            return data;
        })
        .catch(err=>{
            console.error(err);
            iziToast.error({
                title:'Erreur', message:'Erreur serveur',
                position:'topRight', theme:'dark', color:'#343a40', messageColor:'#fff'
            });
            throw err;
        })
        .finally(()=>{
            isResending = false;
            toggleResendLink(false);
        });
    }

    // Envoi automatique du code à l'arrivée sur la page OTP
    requestLoginOtp(true);

    // --- Resend OTP ---
    resendLink.addEventListener('click', function(e){
        e.preventDefault();
        requestLoginOtp(false);
    });

    // --- OTP auto-focus ---
    otpInputs.forEach((input, idx)=>{
        input.addEventListener('input', function(){
            if(this.value.length===1 && idx<otpInputs.length-1) otpInputs[idx+1].focus();
        });
        input.addEventListener('keydown', function(e){
            if(e.key==='Backspace' && !this.value && idx>0) otpInputs[idx-1].focus();
        });
    });

});
    </script>

</body>

</html>
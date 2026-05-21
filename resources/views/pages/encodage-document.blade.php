<!DOCTYPE html>
<html lang="fr" data-sidenav-size="default" data-bs-theme="dark" data-menu-color="dark" data-topbar-color="light" data-layout-mode="detached">

<head>
    <meta charset="utf-8" />
    <title>Encodage document | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Authentifier. Traquer la fraude. Protéger ce qui compte." name="description" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">

    <link rel="shortcut icon" href="assets/images/ico.png">
    <script src="assets/js/config.js"></script>
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/authentiq-encodage.css') }}?v={{ @filemtime(public_path('assets/css/authentiq-encodage.css')) }}" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">

    <style>
        html[data-layout-mode="detached"]:not([data-layout="topnav"]) .wrapper {
            max-width: 95%;
            margin: 0 auto;
        }
    </style>

    <script async src="https://docs.opencv.org/4.5.0/opencv.js" onload="onOpenCvReady()"></script>
</head>

<body>
    <div class="wrapper">
        @include('layouts.partials.header-raw')

        <div class="page-content">
            <div class="page-container">
                @if (session('error'))
                    <div class="alert alert-danger border-0 mt-2" style="border-radius:15px;">{{ session('error') }}</div>
                @endif
                @include('pages.partials.encodage-wizard')
            </div>

            @include('pages.partials.client-photo-cropper-modal', [
                'modalId' => 'encNewClientCropperModal',
                'imageId' => 'encNewClientCropperImage',
                'validateId' => 'encNewClientCropperValidate',
            ])

            @include('layouts.partials.footer-raw')
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        window.ENCODAGE_API_BASE = @json(url('/api/encodage-workflow'));
        window.ENCODAGE_DASHBOARD_URL = @json(url('/dashboard'));
        window.AUTHENTIQ_USER_ROLE = @json($user->role ?? '');
        window.AUTHENTIQ_TEXTRACT_ENABLED = @json(config('authentiq.textract_enabled'));
        window.AUTHENTIQ_REKOGNITION_ENABLED = @json(config('authentiq.rekognition_enabled'));
        if (typeof iziToast !== 'undefined') {
            iziToast.settings({
                position: 'topRight',
                theme: 'dark',
                backgroundColor: '#3d4153',
                messageColor: '#fff',
                titleColor: '#fff',
            });
        }
    </script>
    <script src="{{ asset('js/authentiq-client-duplicate.js') }}?v={{ @filemtime(public_path('js/authentiq-client-duplicate.js')) }}"></script>
    <script src="{{ asset('js/encode.js') }}?v={{ @filemtime(public_path('js/encode.js')) }}"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} | Authentiq</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="{{ url('/') }}/">
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container py-5 text-center">
        <h1 class="mb-3">{{ $title }}</h1>
        <p class="text-muted">Page en cours de migration vers Laravel.</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary mt-3">Retour au tableau de bord</a>
    </div>
</body>
</html>

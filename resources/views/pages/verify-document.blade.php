<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification document — AuthentiQ</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}">
    <style>
        body { background: #0f1117; color: #e8eaed; min-height: 100vh; }
        .verify-wrap { max-width: 560px; margin: 0 auto; padding: 2rem 1rem 3rem; }
        .verify-card {
            background: #1a1d27;
            border: 1px solid #2d3348;
            border-radius: 24px;
            padding: 1.75rem;
        }
        .verify-badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .verify-badge--valid { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
        .verify-badge--expired { background: rgba(231, 76, 60, 0.15); color: #e74c3c; }
        .verify-badge--unknown { background: rgba(149, 165, 166, 0.15); color: #bdc3c7; }
        .verify-qr { text-align: center; margin: 1.25rem 0; }
        .verify-qr img { max-width: 200px; border-radius: 12px; background: #fff; padding: 8px; }
        .verify-meta dt { color: #8b92a8; font-size: 0.8rem; margin-top: 0.75rem; }
        .verify-meta dd { margin: 0.15rem 0 0; font-weight: 500; }
        .verify-thumb { border-radius: 12px; max-width: 100%; margin-top: 1rem; border: 1px solid #2d3348; }
    </style>
</head>
<body>
    <div class="verify-wrap">
        <div class="text-center mb-4">
            <h1 class="h3 mb-1">AuthentiQ</h1>
            <p class="text-muted mb-0">Vérification d'authenticité</p>
        </div>

        <div class="verify-card">
            @if(!$encodage)
                <span class="verify-badge verify-badge--unknown">Document introuvable</span>
                <p class="mt-3 mb-0">Le numéro <strong>{{ $numero }}</strong> ne correspond à aucun document enregistré ou finalisé.</p>
            @else
                @php
                    $isValid = $encodage->status === 'complete';
                    $isExpired = $encodage->status === 'expired';
                @endphp

                @if($isValid)
                    <span class="verify-badge verify-badge--valid">Document authentique</span>
                @elseif($isExpired)
                    <span class="verify-badge verify-badge--expired">Document expiré</span>
                @else
                    <span class="verify-badge verify-badge--unknown">Statut : {{ $encodage->status }}</span>
                @endif

                @if($qrUrl)
                    <div class="verify-qr">
                        <img src="{{ $qrUrl }}" alt="QR code de vérification" width="200" height="200">
                    </div>
                @endif

                <dl class="verify-meta mb-0">
                    <dt>Référence</dt>
                    <dd>{{ $encodage->numero }}</dd>

                    <dt>Type de document</dt>
                    <dd>{{ $encodage->doc?->nom_doc ?: $encodage->type_doc ?: '—' }}</dd>

                    @if($encodage->date_emission)
                        <dt>Date d'émission</dt>
                        <dd>{{ $encodage->date_emission->format('d/m/Y') }}</dd>
                    @endif

                    @if($encodage->date_expiration)
                        <dt>Date d'expiration</dt>
                        <dd>{{ $encodage->date_expiration->format('d/m/Y') }}</dd>
                    @endif

                    @if($encodage->affectation || $encodage->commune)
                        <dt>Affectation</dt>
                        <dd>{{ $encodage->affectation ?: $encodage->commune?->nom }}</dd>
                    @endif
                </dl>

                @if($firstPageUrl)
                    <img class="verify-thumb" src="{{ $firstPageUrl }}" alt="Aperçu du document">
                @endif
            @endif
        </div>

        <p class="text-center text-muted small mt-4 mb-0">
            Scannez le QR code sur le document pour accéder à cette page.
        </p>
    </div>
</body>
</html>

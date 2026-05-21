<?php

namespace App\Services;

use App\Models\Encodage;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class EncodageQrService
{
    public function __construct(private DocumentStorage $storage) {}

    /**
     * Attribue un numéro unique, génère le QR et met à jour l'encodage.
     *
     * @return array{numero: string, verify_url: string, qr_url: string|null, qr_path: string}
     */
    public function issueForEncodage(Encodage $encodage): array
    {
        $numero = $encodage->numero ?: $this->generateUniqueNumero();
        $verifyUrl = rtrim((string) config('app.url'), '/').'/verify/'.$numero;

        $qrPath = $encodage->qr_path ?: $this->storage->prefix().'/qr_'.$numero.'.png';

        if (! $encodage->qr_path || ! $this->storage->disk()->exists($qrPath)) {
            $png = $this->renderPng($verifyUrl);
            $this->storage->storeBinary($png, $qrPath);
        }

        $encodage->update([
            'numero' => $numero,
            'qr_path' => $qrPath,
        ]);

        return [
            'numero' => $numero,
            'verify_url' => $verifyUrl,
            'qr_path' => $qrPath,
            'qr_url' => $this->storage->url($qrPath),
        ];
    }

    private function generateUniqueNumero(): string
    {
        do {
            $numero = strtoupper(bin2hex(random_bytes(4)));
        } while (Encodage::query()->where('numero', $numero)->exists());

        return $numero;
    }

    private function renderPng(string $data): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 8,
            'imageBase64' => false,
        ]);

        return (new QRCode($options))->render($data);
    }
}

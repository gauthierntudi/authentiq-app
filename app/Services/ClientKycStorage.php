<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ClientKycStorage
{
    public function __construct(private DocumentStorage $documents) {}

    public function store(int $clientId, int $submissionId, UploadedFile $file, string $side): string
    {
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Impossible de lire l\'image.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $relativePath = sprintf('kyc/client_%d/submission_%d/%s.%s', $clientId, $submissionId, $side, $ext);
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $this->documents->diskPut($relativePath, $bytes, $mime);

        return $this->documents->diskName() === 'uploads'
            ? 'uploads/'.$relativePath
            : $relativePath;
    }
}

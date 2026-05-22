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

    public function readBytes(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = str_replace('\\', '/', ltrim(str_replace(['../', '..\\'], '', $path), '/'));
        $diskPath = $this->diskPath($normalized);

        $bytes = $this->documents->diskGet($diskPath);
        if ($bytes !== null) {
            return $bytes;
        }

        foreach ($this->legacyCandidates($normalized) as $file) {
            if (is_file($file)) {
                $contents = file_get_contents($file);

                return $contents !== false ? $contents : null;
            }
        }

        return null;
    }

    private function diskPath(string $normalized): string
    {
        if (str_starts_with($normalized, 'uploads/kyc/')) {
            return substr($normalized, strlen('uploads/'));
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return substr($normalized, strlen('uploads/'));
        }

        if (str_starts_with($normalized, 'kyc/')) {
            return $normalized;
        }

        return 'kyc/'.basename($normalized);
    }

    /** @return list<string> */
    private function legacyCandidates(string $normalized): array
    {
        return [
            public_path($normalized),
            public_path('uploads/'.$normalized),
        ];
    }
}

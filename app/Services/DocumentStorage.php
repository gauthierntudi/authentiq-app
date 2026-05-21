<?php

namespace App\Services;

use App\Services\Aws\S3ObjectHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentStorage
{
    public function diskName(): string
    {
        $disk = (string) config('authentiq.documents_disk', 'uploads');

        return in_array($disk, ['uploads', 's3'], true) ? $disk : 'uploads';
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /** S3 utilisable (bucket + credentials renseignés). */
    public function cloudDiskReady(): bool
    {
        if ($this->diskName() !== 's3') {
            return true;
        }

        $disk = config('filesystems.disks.s3', []);

        return filled($disk['bucket'] ?? null)
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null);
    }

    /**
     * @throws \RuntimeException
     */
    public function assertCloudDiskReady(): void
    {
        if ($this->cloudDiskReady()) {
            return;
        }

        throw new \RuntimeException(
            'Stockage S3 non configuré : définissez AWS_BUCKET, AWS_ACCESS_KEY_ID et AWS_SECRET_ACCESS_KEY '
            .'(Laravel Cloud → Environment, ou activez Object Storage).'
        );
    }

    public function diskExists(string $path): bool
    {
        if ($this->diskName() === 's3' && ! $this->cloudDiskReady()) {
            return false;
        }

        try {
            return $this->disk()->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function diskGet(string $path): ?string
    {
        if ($this->diskName() === 's3' && ! $this->cloudDiskReady()) {
            return null;
        }

        $key = ltrim($path, '/');

        if ($this->diskName() === 's3') {
            $s3 = S3ObjectHelper::fromDiskConfig();
            if ($s3) {
                $bytes = $s3->get($key);
                if ($bytes !== null) {
                    return $bytes;
                }
            }
        }

        try {
            $contents = $this->disk()->get($key);
            if ($contents !== false && $contents !== '') {
                return $contents;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @throws \RuntimeException
     */
    public function diskPut(string $path, string $bytes, string $contentType = 'application/octet-stream'): void
    {
        $this->assertCloudDiskReady();

        $key = ltrim($path, '/');

        if ($this->diskName() === 's3') {
            $s3 = S3ObjectHelper::fromDiskConfig();
            if (! $s3) {
                throw new \RuntimeException('Configuration S3 incomplète.');
            }

            try {
                $s3->put($key, $bytes, $contentType);

                return;
            } catch (\Throwable $e) {
                throw new \RuntimeException('Upload S3 échoué : '.$e->getMessage(), 0, $e);
            }
        }

        if (! $this->disk()->put($key, $bytes)) {
            throw new \RuntimeException('Écriture sur le disque uploads impossible.');
        }
    }

    public function prefix(): string
    {
        return trim((string) config('authentiq.documents_path_prefix', 'fileAuthentiq'), '/');
    }

    /**
     * @return array{path: string, size: int|null}
     */
    public function storeUploadedPage(UploadedFile $file, int $encodageId, int $pageNumber): array
    {
        $this->assertCloudDiskReady();

        $fileName = sprintf('doc_%d_p%d_%s.jpg', $encodageId, $pageNumber, uniqid());
        $relativePath = $this->prefix().'/'.$fileName;

        $bytes = $file->get();
        if ($bytes === '') {
            $path = $file->getRealPath();
            $bytes = ($path && is_readable($path)) ? (file_get_contents($path) ?: '') : '';
        }
        if ($bytes === '') {
            throw new \RuntimeException('Impossible de lire l\'image de la page.');
        }

        $this->diskPut($relativePath, $bytes, 'image/jpeg');

        $size = $file->getSize();
        if ($size === false || $size === null) {
            try {
                $size = $this->disk()->size($relativePath);
            } catch (\Throwable) {
                $size = null;
            }
        }

        return [
            'path' => $relativePath,
            'size' => $size,
        ];
    }

    public function storeBinary(string $contents, string $relativePath): string
    {
        $this->assertCloudDiskReady();

        $normalized = $this->normalizePath($relativePath);
        $this->diskPut($normalized, $contents, 'image/jpeg');

        return $normalized;
    }

    /**
     * URL pour un fichier déjà référencé en base (évite HeadObject S3 par fichier).
     */
    public function urlForKnownPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);

        if (str_starts_with($normalized, 'uploads/')) {
            return asset($normalized);
        }

        if ($this->diskName() === 'uploads') {
            return rtrim(config('app.url'), '/').'/uploads/'.$normalized;
        }

        if ($this->diskName() === 's3' && $this->cloudDiskReady()) {
            $signed = $this->temporaryUrlOrNull($normalized);

            if ($signed !== null) {
                return $signed;
            }
        }

        return $this->legacyPublicUrl($normalized);
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);

        if (str_starts_with($normalized, 'uploads/')) {
            return asset($normalized);
        }

        if ($this->diskName() === 'uploads') {
            return rtrim(config('app.url'), '/').'/uploads/'.$normalized;
        }

        if ($this->diskExists($normalized)) {
            $signed = $this->temporaryUrlOrNull($normalized);

            if ($signed !== null) {
                return $signed;
            }
        }

        if ($this->diskName() === 's3' && $this->cloudDiskReady()) {
            $signed = $this->temporaryUrlOrNull($normalized);

            if ($signed !== null) {
                return $signed;
            }
        }

        return $this->legacyPublicUrl($normalized);
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $normalized = $this->normalizePath($path);

        $diskPath = $normalized;
        if (str_starts_with($diskPath, 'uploads/')) {
            $diskPath = substr($diskPath, strlen('uploads/'));
        }

        if ($this->diskExists($diskPath)) {
            try {
                $this->disk()->delete($diskPath);
            } catch (\Throwable) {
            }

            return;
        }

        if ($this->diskExists($normalized)) {
            try {
                $this->disk()->delete($normalized);
            } catch (\Throwable) {
            }

            return;
        }

        $this->deleteLegacyFile($normalized);
    }

    public function normalizePath(string $path): string
    {
        return ltrim(str_replace(['../', '..\\'], '', $path), '/\\');
    }

    public function readBytes(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);
        $diskPath = $normalized;
        if (str_starts_with($diskPath, 'uploads/')) {
            $diskPath = substr($diskPath, strlen('uploads/'));
        }

        $bytes = $this->diskGet($diskPath);
        if ($bytes !== null) {
            return $bytes;
        }

        $bytes = $this->diskGet($normalized);
        if ($bytes !== null) {
            return $bytes;
        }

        foreach ($this->legacyFileCandidates($normalized) as $file) {
            if (is_file($file)) {
                $contents = file_get_contents($file);

                return $contents !== false ? $contents : null;
            }
        }

        return null;
    }

    private function temporaryUrlOrNull(string $path): ?string
    {
        try {
            return $this->disk()->temporaryUrl($path, now()->addHours(6));
        } catch (\Throwable) {
            try {
                return $this->disk()->url($path);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function legacyPublicUrl(string $normalized): ?string
    {
        foreach ($this->legacyFileCandidates($normalized) as $file) {
            if (is_file($file)) {
                if (str_starts_with($file, public_path('uploads'))) {
                    return asset('uploads/'.ltrim(substr($file, strlen(public_path('uploads'))), '/'));
                }

                return asset(ltrim(substr($file, strlen(public_path())), '/'));
            }
        }

        return null;
    }

    private function deleteLegacyFile(string $normalized): void
    {
        foreach ($this->legacyFileCandidates($normalized) as $file) {
            if (is_file($file)) {
                @unlink($file);
                break;
            }
        }
    }

    /** @return list<string> */
    private function legacyFileCandidates(string $normalized): array
    {
        return [
            public_path($normalized),
            public_path('uploads/'.$normalized),
            public_path('uploads/fileAuthentiq/'.basename($normalized)),
        ];
    }
}

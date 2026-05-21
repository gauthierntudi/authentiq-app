<?php

namespace App\Services;

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

    public function prefix(): string
    {
        return trim((string) config('authentiq.documents_path_prefix', 'fileAuthentiq'), '/');
    }

    /**
     * @return array{path: string, size: int|null}
     */
    public function storeUploadedPage(UploadedFile $file, int $encodageId, int $pageNumber): array
    {
        $fileName = sprintf('doc_%d_p%d_%s.jpg', $encodageId, $pageNumber, uniqid());
        $relativePath = $this->prefix().'/'.$fileName;

        $this->disk()->putFileAs($this->prefix(), $file, $fileName, ['visibility' => 'public']);

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
        $normalized = $this->normalizePath($relativePath);
        $this->disk()->put($normalized, $contents, ['visibility' => 'public']);

        return $normalized;
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

        if ($this->disk()->exists($normalized)) {
            try {
                return $this->disk()->temporaryUrl($normalized, now()->addHours(6));
            } catch (\Throwable) {
                return $this->disk()->url($normalized);
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

        if ($this->disk()->exists($diskPath)) {
            $this->disk()->delete($diskPath);

            return;
        }

        if ($this->disk()->exists($normalized)) {
            $this->disk()->delete($normalized);

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

        if ($this->disk()->exists($diskPath)) {
            return $this->disk()->get($diskPath);
        }

        if ($this->disk()->exists($normalized)) {
            return $this->disk()->get($normalized);
        }

        foreach ($this->legacyFileCandidates($normalized) as $file) {
            if (is_file($file)) {
                $contents = file_get_contents($file);

                return $contents !== false ? $contents : null;
            }
        }

        return null;
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

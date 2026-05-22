<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

class UserPhotoStorage
{
    public function __construct(private DocumentStorage $documents) {}

    public function diskName(): string
    {
        return $this->documents->diskName();
    }

    public function disk(): Filesystem
    {
        return $this->documents->disk();
    }

    public function store(User $user, UploadedFile $file): string
    {
        $bytes = $this->bytesFromUpload($file);
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Impossible de lire la photo envoyée.');
        }

        $name = 'user_'.$user->id_user.'_'.uniqid().'.jpg';
        $relativePath = 'users/'.$name;

        $this->documents->diskPut($relativePath, $bytes, 'image/jpeg');

        return $this->diskName() === 'uploads' ? 'uploads/'.$relativePath : $relativePath;
    }

    public function storeFromUpload(UploadedFile $file): string
    {
        $bytes = $this->bytesFromUpload($file);
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Impossible de lire la photo envoyée.');
        }

        $name = 'user_'.uniqid().'.jpg';
        $relativePath = 'users/'.$name;

        $this->documents->diskPut($relativePath, $bytes, 'image/jpeg');

        return $this->diskName() === 'uploads' ? 'uploads/'.$relativePath : $relativePath;
    }

    public function defaultUrl(): string
    {
        return asset('assets/images/user.jpg');
    }

    public function photoUrl(?string $path, ?int $userId = null, ?int $cacheVersion = null): string
    {
        if (! $path) {
            return $this->defaultUrl();
        }

        if ($this->diskName() === 's3' && $userId !== null && $userId > 0) {
            $v = $cacheVersion ?? $this->cacheVersionFromPath($path);

            return url('/api/users/'.$userId.'/photo').'?v='.$v;
        }

        $base = $this->url($path) ?? $this->defaultUrl();
        if ($cacheVersion !== null && ! str_contains($base, 'user.jpg')) {
            $sep = str_contains($base, '?') ? '&' : '?';

            return $base.$sep.'v='.$cacheVersion;
        }

        return $base;
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);
        $diskPath = $this->diskPath($normalized);

        if ($this->documents->diskExists($diskPath)) {
            $signed = $this->temporaryUrlOrNull($diskPath);

            if ($signed !== null) {
                return $signed;
            }
        }

        if ($this->diskName() === 's3' && $this->documents->cloudDiskReady()) {
            $signed = $this->temporaryUrlOrNull($diskPath);

            if ($signed !== null) {
                return $signed;
            }
        }

        if ($this->diskName() === 'uploads' && str_starts_with($normalized, 'uploads/')) {
            return asset($normalized);
        }

        return $this->legacyPublicUrl($normalized);
    }

    public function readBytes(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);
        $diskPath = $this->diskPath($normalized);

        $bytes = $this->documents->diskGet($diskPath);
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

    public function migrateUserPhoto(User $user): bool
    {
        if (! $user->photo) {
            return false;
        }

        $normalized = $this->normalizePath($user->photo);
        $diskPath = $this->diskPath($normalized);

        if ($this->documents->diskExists($diskPath)) {
            return true;
        }

        $bytes = $this->readBytes($user->photo);
        if ($bytes === null || $bytes === '') {
            return false;
        }

        try {
            $this->documents->assertCloudDiskReady();
        } catch (\RuntimeException) {
            return false;
        }

        $name = 'user_'.$user->id_user.'_'.uniqid().'.jpg';
        $newDiskPath = 'users/'.$name;
        $this->documents->diskPut($newDiskPath, $bytes, 'image/jpeg');

        $stored = $this->diskName() === 'uploads'
            ? 'uploads/'.$newDiskPath
            : $newDiskPath;

        $user->update(['photo' => $stored]);

        return true;
    }

    public function cacheVersionFromPath(string $path): int
    {
        return crc32($this->normalizePath($path));
    }

    public function bytesFromUpload(UploadedFile $file): ?string
    {
        try {
            $contents = $file->get();
            if ($contents !== '') {
                return $contents;
            }
        } catch (\Throwable) {
        }

        $path = $file->getRealPath();
        if ($path && is_readable($path)) {
            $bytes = file_get_contents($path);

            return ($bytes !== false && $bytes !== '') ? $bytes : null;
        }

        return null;
    }

    public function normalizePath(string $path): string
    {
        return ltrim(str_replace(['../', '..\\'], '', $path), '/\\');
    }

    private function temporaryUrlOrNull(string $diskPath): ?string
    {
        try {
            return $this->disk()->temporaryUrl($diskPath, now()->addHours(6));
        } catch (\Throwable) {
            try {
                return $this->disk()->url($diskPath);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function diskPath(string $normalized): string
    {
        if (str_starts_with($normalized, 'uploads/users/')) {
            return 'users/'.basename($normalized);
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return substr($normalized, strlen('uploads/'));
        }

        if (str_starts_with($normalized, 'users/')) {
            return $normalized;
        }

        return 'users/'.basename($normalized);
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

    /** @return list<string> */
    private function legacyFileCandidates(string $normalized): array
    {
        $base = basename($normalized);

        return [
            public_path($normalized),
            public_path('uploads/'.$normalized),
            public_path('uploads/users/'.$base),
            public_path('users/'.$base),
        ];
    }
}

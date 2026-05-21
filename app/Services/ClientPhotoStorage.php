<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClientPhotoStorage
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

    public function store(Client $client, UploadedFile $file): string
    {
        $name = 'client_'.$client->id_client.'_'.uniqid().'.jpg';
        $this->disk()->putFileAs('clients', $file, $name, ['visibility' => 'public']);
        $relativePath = 'clients/'.$name;

        return $this->diskName() === 'uploads' ? 'uploads/'.$relativePath : $relativePath;
    }

    public function defaultUrl(): string
    {
        return asset('assets/images/user.jpg');
    }

    public function photoUrl(?string $path): string
    {
        return $this->url($path) ?? $this->defaultUrl();
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = $this->normalizePath($path);
        $diskPath = $this->diskPath($normalized);

        if ($this->disk()->exists($diskPath)) {
            try {
                return $this->disk()->temporaryUrl($diskPath, now()->addHours(6));
            } catch (\Throwable) {
                return $this->disk()->url($diskPath);
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

        if ($this->disk()->exists($diskPath)) {
            return $this->disk()->get($diskPath);
        }

        foreach ($this->legacyFileCandidates($normalized) as $file) {
            if (is_file($file)) {
                $contents = file_get_contents($file);

                return $contents !== false ? $contents : null;
            }
        }

        return null;
    }

    /**
     * Copie une photo locale vers le disque configuré (S3 en production).
     */
    public function migrateClientPhoto(Client $client): bool
    {
        if (! $client->photo) {
            return false;
        }

        $normalized = $this->normalizePath($client->photo);
        $diskPath = $this->diskPath($normalized);

        if ($this->disk()->exists($diskPath)) {
            return true;
        }

        $bytes = $this->readBytes($client->photo);
        if ($bytes === null || $bytes === '') {
            return false;
        }

        $name = 'client_'.$client->id_client.'_'.uniqid().'.jpg';
        $newDiskPath = 'clients/'.$name;
        $this->disk()->put($newDiskPath, $bytes, ['visibility' => 'public']);

        $stored = $this->diskName() === 'uploads'
            ? 'uploads/'.$newDiskPath
            : $newDiskPath;

        $client->update(['photo' => $stored]);

        return true;
    }

    public function normalizePath(string $path): string
    {
        return ltrim(str_replace(['../', '..\\'], '', $path), '/\\');
    }

    private function diskPath(string $normalized): string
    {
        if (str_starts_with($normalized, 'uploads/clients/')) {
            return 'clients/'.basename($normalized);
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return substr($normalized, strlen('uploads/'));
        }

        if (str_starts_with($normalized, 'clients/')) {
            return $normalized;
        }

        return 'clients/'.basename($normalized);
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
            public_path('uploads/clients/'.$base),
            public_path('clients/'.$base),
        ];
    }
}

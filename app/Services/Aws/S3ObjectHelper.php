<?php

namespace App\Services\Aws;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

/**
 * Lecture/écriture S3 via le SDK AWS (fiable avec R2, Laravel Object Storage et AWS S3).
 */
class S3ObjectHelper
{
    public static function fromDiskConfig(): ?self
    {
        $disk = config('filesystems.disks.s3', []);
        $bucket = $disk['bucket'] ?? null;
        $key = $disk['key'] ?? null;
        $secret = $disk['secret'] ?? null;

        if (! filled($bucket) || ! filled($key) || ! filled($secret)) {
            return null;
        }

        return new self(
            bucket: (string) $bucket,
            region: (string) ($disk['region'] ?? 'us-east-1'),
            key: (string) $key,
            secret: (string) $secret,
            endpoint: filled($disk['endpoint'] ?? null) ? (string) $disk['endpoint'] : null,
            usePathStyle: (bool) ($disk['use_path_style_endpoint'] ?? false),
        );
    }

    public function __construct(
        private string $bucket,
        private string $region,
        private string $key,
        private string $secret,
        private ?string $endpoint = null,
        private bool $usePathStyle = false,
    ) {}

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function client(): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ],
        ];

        if ($this->endpoint) {
            $config['endpoint'] = $this->endpoint;
            $config['use_path_style_endpoint'] = $this->usePathStyle;
        }

        return new S3Client($config);
    }

    public function normalizeKey(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }

    public function exists(string $path): bool
    {
        try {
            $this->client()->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->normalizeKey($path),
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    public function get(string $path): ?string
    {
        try {
            $result = $this->client()->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->normalizeKey($path),
            ]);

            $body = (string) ($result['Body'] ?? '');

            return $body !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function put(string $path, string $bytes, string $contentType = 'application/octet-stream'): void
    {
        $this->client()->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->normalizeKey($path),
            'Body' => $bytes,
            'ContentType' => $contentType,
        ]);
    }
}

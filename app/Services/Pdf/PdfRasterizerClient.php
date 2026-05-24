<?php

namespace App\Services\Pdf;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdfRasterizerClient
{
    public function isEnabled(): bool
    {
        return (bool) config('authentiq.pdf_service.enabled', false)
            && (string) config('authentiq.pdf_service.url') !== '';
    }

    public function shouldOffload(UploadedFile $file): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $minBytes = (int) config('authentiq.pdf_service.min_bytes', 2 * 1024 * 1024);

        return $file->getSize() >= $minBytes;
    }

    /**
     * @return array{page_count: int, pages: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function rasterize(UploadedFile $file, ?int $dpi = null, ?int $maxPages = null): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Service PDF non configuré.');
        }

        $baseUrl = rtrim((string) config('authentiq.pdf_service.url'), '/');
        $apiKey = (string) config('authentiq.pdf_service.api_key', '');
        $timeout = (int) config('authentiq.pdf_service.timeout', 120);

        $query = array_filter([
            'dpi' => $dpi ?? (int) config('authentiq.pdf_service.dpi', 150),
            'max_pages' => $maxPages ?? (int) config('authentiq.pdf_service.max_pages', 100),
        ]);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->attach(
                'file',
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName() ?: 'document.pdf',
            )
            ->post("{$baseUrl}/v1/rasterize", $query);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->body()
                ?? 'Erreur du service PDF.';

            throw new RuntimeException(is_string($message) ? $message : 'Erreur du service PDF.');
        }

        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? '') !== 'success') {
            throw new RuntimeException('Réponse invalide du service PDF.');
        }

        $pages = $data['pages'] ?? [];
        if (! is_array($pages) || $pages === []) {
            throw new RuntimeException('Aucune page extraite du PDF.');
        }

        return [
            'page_count' => (int) ($data['page_count'] ?? count($pages)),
            'pages' => $pages,
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
        ];
    }
}

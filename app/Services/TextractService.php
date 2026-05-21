<?php

namespace App\Services;

use App\Services\Aws\AwsClientFactory;
use Aws\Textract\TextractClient;

class TextractService
{
    public function enabled(): bool
    {
        return (bool) config('authentiq.textract_enabled', false) && AwsClientFactory::configured();
    }

    public function extractTextFromStoragePath(string $relativePath): string
    {
        return $this->extractDocument($relativePath)['text'];
    }

    /**
     * @return array{text: string, quality_score: ?float} quality_score = confiance OCR moyenne Textract (0–100)
     */
    public function extractDocument(string $relativePath): array
    {
        $storage = app(DocumentStorage::class);
        $normalized = ltrim($relativePath, '/');

        if ($storage->diskName() === 's3') {
            $bucket = config('filesystems.disks.s3.bucket');
            if (! $bucket) {
                throw new \RuntimeException('Bucket S3 non configuré.');
            }

            $key = $normalized;
            if (str_starts_with($key, 'uploads/')) {
                $key = substr($key, strlen('uploads/'));
            }

            $result = $this->client()->detectDocumentText([
                'Document' => [
                    'S3Object' => [
                        'Bucket' => $bucket,
                        'Name' => $key,
                    ],
                ],
            ]);
        } else {
            $bytes = $storage->readBytes($relativePath);

            if ($bytes === null || $bytes === '') {
                throw new \RuntimeException('Fichier introuvable pour Textract.');
            }

            $result = $this->client()->detectDocumentText([
                'Document' => ['Bytes' => $bytes],
            ]);
        }

        return $this->parseBlocks($result['Blocks'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{text: string, quality_score: ?float}
     */
    private function parseBlocks(array $blocks): array
    {
        $lines = [];
        $lineConfidences = [];
        $wordConfidences = [];

        foreach ($blocks as $block) {
            $type = $block['BlockType'] ?? '';
            if ($type === 'LINE' && ! empty($block['Text'])) {
                $lines[] = $block['Text'];
                if (isset($block['Confidence'])) {
                    $lineConfidences[] = (float) $block['Confidence'];
                }
            }
            if ($type === 'WORD' && isset($block['Confidence'])) {
                $wordConfidences[] = (float) $block['Confidence'];
            }
        }

        $confidences = $lineConfidences !== [] ? $lineConfidences : $wordConfidences;
        $qualityScore = null;
        if ($confidences !== []) {
            $avg = array_sum($confidences) / count($confidences);
            $qualityScore = round(min(100, max(0, $avg)), 2);
        }

        return [
            'text' => implode("\n", $lines),
            'quality_score' => $qualityScore,
        ];
    }

    private function client(): TextractClient
    {
        return AwsClientFactory::textract();
    }
}

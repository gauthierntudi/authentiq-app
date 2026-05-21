<?php

namespace App\Jobs;

use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Services\TextractService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessEncodagePageTextract implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $pageId) {}

    public function handle(TextractService $textract): void
    {
        if (! $textract->enabled()) {
            return;
        }

        $page = EncodagePage::query()->find($this->pageId);
        if (! $page || ! $page->file_path) {
            return;
        }

        $page->update(['textract_status' => 'processing']);

        try {
            $extracted = $textract->extractDocument($page->file_path);
            $page->update([
                'ocr_text' => $extracted['text'],
                'quality_score' => $extracted['quality_score'],
                'textract_status' => 'succeeded',
            ]);
            $this->syncEncodageOcr($page->id_encodage);
        } catch (\Throwable $e) {
            Log::warning('Textract page failed', [
                'page_id' => $this->pageId,
                'error' => $e->getMessage(),
            ]);
            $page->update(['textract_status' => 'failed']);
        }
    }

    private function syncEncodageOcr(int $encodageId): void
    {
        $pages = EncodagePage::query()
            ->where('id_encodage', $encodageId)
            ->orderBy('page_number')
            ->get();

        $allDone = $pages->every(fn (EncodagePage $p) => in_array($p->textract_status, ['succeeded', 'failed'], true) || $p->textract_status === null);

        if (! $allDone) {
            return;
        }

        $combined = $pages->map(function (EncodagePage $p) {
            $header = '--- Page '.$p->page_number.' ---';

            return $header."\n".trim((string) $p->ocr_text);
        })->implode("\n\n");

        Encodage::query()->where('id_encodage', $encodageId)->update([
            'ocrTextFiles' => $combined,
        ]);
    }
}

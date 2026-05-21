<?php

namespace App\Console\Commands;

use App\Models\EncodagePage;
use App\Services\TextractService;
use Illuminate\Console\Command;

class TextractBackfillQualityScoreCommand extends Command
{
    protected $signature = 'textract:backfill-quality {--encodage= : ID encodage limité}';

    protected $description = 'Recalcule quality_score (confiance OCR Textract) pour les pages déjà traitées';

    public function handle(TextractService $textract): int
    {
        if (! $textract->enabled()) {
            $this->warn('Textract désactivé ou credentials AWS manquants.');

            return self::FAILURE;
        }

        $query = EncodagePage::query()
            ->where('textract_status', 'succeeded')
            ->whereNull('quality_score')
            ->whereNotNull('file_path');

        $encodageId = (int) $this->option('encodage');
        if ($encodageId > 0) {
            $query->where('id_encodage', $encodageId);
        }

        $pages = $query->get();
        if ($pages->isEmpty()) {
            $this->info('Aucune page à mettre à jour.');

            return self::SUCCESS;
        }

        $this->info('Pages à traiter : '.$pages->count());

        $ok = 0;
        $fail = 0;

        foreach ($pages as $page) {
            try {
                $extracted = $textract->extractDocument($page->file_path);
                $page->update([
                    'ocr_text' => $extracted['text'],
                    'quality_score' => $extracted['quality_score'],
                ]);
                $score = $extracted['quality_score'] ?? '—';
                $this->line("Page {$page->id_page} (encodage {$page->id_encodage}) → quality_score {$score}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("Page {$page->id_page} : ".$e->getMessage());
                $fail++;
            }
        }

        $this->info("Terminé : {$ok} OK, {$fail} erreur(s).");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}

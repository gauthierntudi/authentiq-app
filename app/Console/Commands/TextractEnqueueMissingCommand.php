<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEncodagePageTextract;
use App\Models\EncodagePage;
use App\Services\TextractService;
use Illuminate\Console\Command;

class TextractEnqueueMissingCommand extends Command
{
    protected $signature = 'textract:enqueue-missing
                            {--sync : Traiter immédiatement (sans file d\'attente)}
                            {--retry-failed : Ré-enfiler aussi les pages en échec}';

    protected $description = 'Met en file AWS Textract les pages créées avant l\'intégration (textract_status NULL)';

    public function handle(TextractService $textract): int
    {
        if (! $textract->enabled()) {
            $this->warn('Textract désactivé ou credentials AWS manquants.');

            return self::FAILURE;
        }

        $pages = EncodagePage::query()
            ->whereNotNull('file_path')
            ->where(function ($query) {
                $query->whereNull('textract_status');
                if ($this->option('retry-failed')) {
                    $query->orWhere('textract_status', 'failed');
                }
            })
            ->orderBy('id_page')
            ->get();

        if ($pages->isEmpty()) {
            $this->info('Aucune page à traiter.');

            return self::SUCCESS;
        }

        $this->info('Pages à traiter : '.$pages->count());

        $queue = (string) config('authentiq.queue', 'default');
        $sync = (bool) $this->option('sync');

        foreach ($pages as $page) {
            if ($sync) {
                $this->line("Traitement synchrone page {$page->id_page} (encodage {$page->id_encodage})…");
                ProcessEncodagePageTextract::dispatchSync($page->id_page);
                $page->refresh();
                $this->line("  → {$page->textract_status}, quality_score ".($page->quality_score ?? '—'));

                continue;
            }

            EncodagePage::query()->where('id_page', $page->id_page)->update(['textract_status' => 'queued']);
            ProcessEncodagePageTextract::dispatch($page->id_page)->onQueue($queue);
            $this->line("Page {$page->id_page} (encodage {$page->id_encodage}) → file « {$queue} »");
        }

        if (! $sync) {
            $this->newLine();
            $this->info("Lancez le worker : php artisan queue:work --queue={$queue}");
        }

        return self::SUCCESS;
    }
}

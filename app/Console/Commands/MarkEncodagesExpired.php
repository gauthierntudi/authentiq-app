<?php

namespace App\Console\Commands;

use App\Models\Encodage;
use Illuminate\Console\Command;

class MarkEncodagesExpired extends Command
{
    protected $signature = 'encodage:mark-expired';

    protected $description = 'Marque comme expirés les encodages complets dont la date d\'expiration est dépassée';

    public function handle(): int
    {
        $today = now()->toDateString();

        $count = Encodage::query()
            ->where('status', 'complete')
            ->whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<', $today)
            ->update(['status' => 'expired']);

        $this->info("{$count} encodage(s) marqué(s) comme expiré(s).");

        return self::SUCCESS;
    }
}

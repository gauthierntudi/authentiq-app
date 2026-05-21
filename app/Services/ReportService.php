<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Encodage;
use App\Models\EncodagePage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private ClientPhotoStorage $clientPhotos) {}
    public function daily(User $user, ?string $date = null): array
    {
        $day = $this->parseDate($date) ?? today();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $encodages = $this->encodageQuery($user)
            ->whereBetween('created_at', [$from, $to])
            ->with(['client', 'doc', 'user', 'commune']);

        $finalizedToday = $this->encodageQuery($user)
            ->where('status', 'complete')
            ->whereBetween('updated_at', [$from, $to]);

        $summary = $this->buildSummary($encodages->clone(), $finalizedToday->clone());
        $summary['finalized_today'] = (int) $finalizedToday->count();

        return [
            'period' => [
                'type' => 'daily',
                'label' => $day->locale('fr')->isoFormat('dddd D MMMM YYYY'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $summary,
            'timeline' => $this->hourlyTimeline($encodages->clone()),
            'by_agent' => $this->byAgent($encodages->clone(), $user),
            'by_doc_type' => $this->byDocType($encodages->clone()),
            'by_commune' => $this->byCommune($encodages->clone()),
            'recent_encodages' => $this->recentRows($encodages->clone()->orderByDesc('created_at'), 15),
            'clients_new' => $this->clientsInPeriod($from, $to),
        ];
    }

    public function monthly(User $user, ?string $month = null): array
    {
        $start = $this->parseMonth($month) ?? now()->startOfMonth();
        $from = $start->copy()->startOfMonth();
        $to = $start->copy()->endOfMonth();

        $encodages = $this->encodageQuery($user)
            ->whereBetween('created_at', [$from, $to])
            ->with(['client', 'doc', 'user', 'commune']);

        $finalizedInMonth = $this->encodageQuery($user)
            ->where('status', 'complete')
            ->whereBetween('updated_at', [$from, $to]);

        $summary = $this->buildSummary($encodages->clone(), $finalizedInMonth->clone());
        $summary['finalized_in_period'] = (int) $finalizedInMonth->count();

        return [
            'period' => [
                'type' => 'monthly',
                'label' => $from->locale('fr')->isoFormat('MMMM YYYY'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $summary,
            'timeline' => $this->dailyTimeline($encodages->clone(), $from, $to),
            'by_agent' => $this->byAgent($encodages->clone(), $user),
            'by_doc_type' => $this->byDocType($encodages->clone()),
            'by_commune' => $this->byCommune($encodages->clone()),
            'recent_encodages' => $this->recentRows($encodages->clone()->orderByDesc('created_at'), 20),
            'clients_new' => $this->clientsInPeriod($from, $to),
        ];
    }

    public function global(User $user): array
    {
        $encodages = $this->encodageQuery($user)
            ->with(['client', 'doc', 'user', 'commune']);

        $from = Encodage::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('id_user', $user->id_user))
            ->min('created_at');

        $firstDate = $from ? Carbon::parse($from)->startOfMonth() : now()->startOfMonth();
        $to = now()->endOfMonth();

        $summary = $this->buildSummary($encodages->clone(), null);
        $summary['agents_active'] = (int) $this->encodageQuery($user)
            ->whereNotNull('id_user')
            ->distinct('id_user')
            ->count('id_user');

        return [
            'period' => [
                'type' => 'global',
                'label' => 'Depuis le début',
                'from' => $firstDate->toDateString(),
                'to' => now()->toDateString(),
            ],
            'summary' => $summary,
            'timeline' => $this->monthlyTimeline($encodages->clone(), $firstDate, $to),
            'by_agent' => $this->byAgent($encodages->clone(), $user),
            'by_doc_type' => $this->byDocType($encodages->clone()),
            'by_commune' => $this->byCommune($encodages->clone()),
            'by_status' => $this->byStatus($encodages->clone()),
            'recent_encodages' => $this->recentRows($encodages->clone()->orderByDesc('created_at'), 25),
            'clients_new' => (int) Client::query()->count(),
        ];
    }

    private function encodageQuery(User $user): Builder
    {
        $query = Encodage::query();

        if ($user->role !== 'admin') {
            $query->where('id_user', $user->id_user);
        }

        return $query;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function buildSummary(Builder $createdQuery, ?Builder $finalizedQuery): array
    {
        $rows = (clone $createdQuery)->get(['status', 'montant', 'page_count', 'id_encodage']);

        $pageIds = $rows->pluck('id_encodage')->filter()->all();
        $ocrAvg = null;
        if ($pageIds !== []) {
            $avg = EncodagePage::query()
                ->whereIn('id_encodage', $pageIds)
                ->whereNotNull('quality_score')
                ->avg('quality_score');
            $ocrAvg = $avg !== null ? round((float) $avg, 2) : null;
        }

        return [
            'encodages_total' => $rows->count(),
            'encodages_complete' => $rows->where('status', 'complete')->count(),
            'encodages_incomplete' => $rows->where('status', 'incomplete')->count(),
            'encodages_expired' => $rows->where('status', 'expired')->count(),
            'montant_total' => round((float) $rows->sum(fn ($e) => (float) ($e->montant ?? 0)), 2),
            'pages_total' => (int) $rows->sum(fn ($e) => (int) ($e->page_count ?? 0)),
            'ocr_quality_avg' => $ocrAvg,
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private function hourlyTimeline(Builder $query): array
    {
        $counts = (clone $query)
            ->selectRaw('HOUR(created_at) as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket');

        $timeline = [];
        for ($h = 0; $h < 24; $h++) {
            $timeline[] = [
                'label' => sprintf('%02dh', $h),
                'value' => (int) ($counts[$h] ?? 0),
            ];
        }

        return $timeline;
    }

    /** @return list<array{label: string, value: int}> */
    private function dailyTimeline(Builder $query, Carbon $from, Carbon $to): array
    {
        $counts = (clone $query)
            ->selectRaw('DAY(created_at) as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $timeline = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $day = (int) $cursor->day;
            $timeline[] = [
                'label' => $cursor->format('d/m'),
                'value' => (int) ($counts[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $timeline;
    }

    /** @return list<array{label: string, value: int}> */
    private function monthlyTimeline(Builder $query, Carbon $from, Carbon $to): array
    {
        $counts = (clone $query)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket');

        $timeline = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $timeline[] = [
                'label' => $cursor->locale('fr')->isoFormat('MMM YY'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        if (count($timeline) > 18) {
            $timeline = array_slice($timeline, -18);
        }

        return $timeline;
    }

    /** @return list<array{label: string, value: int, complete: int}> */
    private function byAgent(Builder $query, User $user): array
    {
        $rows = (clone $query)
            ->select('id_user', 'status', DB::raw('COUNT(*) as total'))
            ->whereNotNull('id_user')
            ->groupBy('id_user', 'status')
            ->get();

        $agentIds = $rows->pluck('id_user')->unique()->filter()->all();
        $names = User::query()
            ->whereIn('id_user', $agentIds)
            ->pluck('nom_complet', 'id_user');

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row->id_user;
            if (! isset($grouped[$id])) {
                $grouped[$id] = ['label' => $names[$id] ?? "Agent #{$id}", 'value' => 0, 'complete' => 0];
            }
            $grouped[$id]['value'] += (int) $row->total;
            if ($row->status === 'complete') {
                $grouped[$id]['complete'] += (int) $row->total;
            }
        }

        $list = array_values($grouped);
        usort($list, fn ($a, $b) => $b['value'] <=> $a['value']);

        if ($user->role !== 'admin' && $list === []) {
            $list[] = [
                'label' => $user->nom_complet,
                'value' => 0,
                'complete' => 0,
            ];
        }

        return array_slice($list, 0, 12);
    }

    /** @return list<array{label: string, value: int}> */
    private function byDocType(Builder $query): array
    {
        $rows = (clone $query)
            ->leftJoin('DOCS as d', 'd.id_doc', '=', 'ENCODAGES.id_doc')
            ->selectRaw("COALESCE(d.nom_doc, ENCODAGES.type_doc, 'Non renseigné') as label, COUNT(*) as value")
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'label' => (string) $r->label,
            'value' => (int) $r->value,
        ])->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function byCommune(Builder $query): array
    {
        $rows = (clone $query)
            ->leftJoin('COMMUNES as c', 'c.id_commune', '=', 'ENCODAGES.id_commune')
            ->selectRaw("COALESCE(c.nom, ENCODAGES.affectation, 'Non renseigné') as label, COUNT(*) as value")
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'label' => (string) $r->label,
            'value' => (int) $r->value,
        ])->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function byStatus(Builder $query): array
    {
        $labels = [
            'complete' => 'Finalisés',
            'incomplete' => 'En cours',
            'expired' => 'Expirés',
        ];

        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as value')
            ->groupBy('status')
            ->pluck('value', 'status');

        $out = [];
        foreach ($labels as $key => $label) {
            $out[] = [
                'label' => $label,
                'value' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function recentRows(Builder $query, int $limit): array
    {
        return $query->limit($limit)->get()->map(function (Encodage $e) {
            return [
                'id_encodage' => $e->id_encodage,
                'status' => $e->status,
                'client_nom' => $e->client?->nom_complet,
                'client_photo_url' => $this->clientPhotos->photoUrl(
                    $e->client?->photo,
                    $e->client?->id_client,
                    $e->client?->updated_at?->getTimestamp(),
                ),
                'type_doc' => $e->doc?->nom_doc ?: $e->type_doc,
                'agent_nom' => $e->user?->nom_complet,
                'affectation' => $e->affectation ?: $e->commune?->nom,
                'montant' => $e->montant,
                'nb_pages' => (int) ($e->page_count ?? 0),
                'date' => $e->created_at?->format('d/m/Y H:i'),
            ];
        })->all();
    }

    private function clientsInPeriod(Carbon $from, Carbon $to): int
    {
        return (int) Client::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function parseDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMonth(?string $month): ?Carbon
    {
        if (! $month) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            }

            return Carbon::parse($month)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReportPageController extends Controller
{
    public function daily(): View
    {
        return $this->page('daily', 'Rapport journalier', 'Activité du jour');
    }

    public function monthly(): View
    {
        return $this->page('monthly', 'Rapport mensuel', 'Synthèse du mois');
    }

    public function global(): View
    {
        return $this->page('global', 'Rapport global', 'Vue d\'ensemble');
    }

    private function page(string $type, string $title, string $subtitle): View
    {
        return view('pages.reports.show', [
            'reportType' => $type,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle,
        ]);
    }
}

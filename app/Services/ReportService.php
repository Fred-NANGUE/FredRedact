<?php

namespace App\Services;

use App\Models\Report;

class ReportService
{
    public function refreshProgress(Report $report): void
    {
        $sections = $report->sections()->get(['id', 'contenu']);
        $completed = $sections->filter(fn ($section) => trim((string) $section->contenu) !== '')->count();
        $progress = $sections->isEmpty()
            ? 0
            : (int) round($completed / $sections->count() * 100);

        $report->forceFill([
            'progression' => $progress,
            'statut' => $progress === 100 ? 'termine' : 'brouillon',
        ])->save();
    }
}

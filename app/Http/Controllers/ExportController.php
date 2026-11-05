<?php

namespace App\Http\Controllers;

use App\Exceptions\AIServiceException;
use App\Models\Report;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function __construct(private AIService $ai) {}

    public function export(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id, 404);
        $data = $request->validate(['format' => ['required', 'in:pdf,docx']]);
        $report->load(['internship', 'sections.children']);

        try {
            $file = $this->ai->export([
                'format' => $data['format'],
                'title' => $report->titre,
                'internship' => $report->internship?->only(['entreprise', 'theme', 'problematique']),
                'sections' => $report->sections->map(fn ($section) => [
                    'titre' => $section->titre,
                    'contenu' => $section->contenu,
                    'children' => $section->children->map(fn ($child) => [
                        'titre' => $child->titre,
                        'contenu' => $child->contenu,
                    ])->values(),
                ])->values()->all(),
            ]);
        } catch (AIServiceException $exception) {
            Log::warning('Report export failed.', ['report_id' => $report->id, 'reason' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }

        $extension = $data['format'];
        $name = 'rapport-'.$report->id.'-'.now()->format('Ymd-His').'.'.$extension;
        $path = 'exports/'.$request->user()->id.'/'.$name;
        if (! Storage::disk('local')->put($path, $file->body())) {
            abort(500, 'Le fichier exporté n’a pas pu être enregistré.');
        }

        return response()->download(Storage::disk('local')->path($path), $name)->deleteFileAfterSend();
    }
}

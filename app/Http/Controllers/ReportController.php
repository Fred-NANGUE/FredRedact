<?php

namespace App\Http\Controllers;

use App\Models\Internship;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->reports()->with('internship')->withCount('sections')->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:180'],
            'internship_id' => ['nullable', 'integer', Rule::exists('internships', 'id')->where('user_id', $request->user()->id)],
        ]);

        $report = $request->user()->reports()->create($data);

        return response()->json($report->load('internship'), 201);
    }

    public function show(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);

        return $report->load(['internship', 'sections.children']);
    }

    public function update(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);
        $data = $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:180'],
            'internship_id' => ['nullable', 'integer', Rule::exists('internships', 'id')->where('user_id', $request->user()->id)],
        ]);
        $report->update($data);

        return $report->fresh()->load('internship');
    }

    public function destroy(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);
        $report->delete();

        return response()->json(['message' => 'Rapport supprimé.']);
    }

    public function createFromInternship(Request $request, Internship $internship)
    {
        abort_unless($internship->user_id === $request->user()->id, 404);
        $data = $request->validate(['titre' => ['nullable', 'string', 'max:180']]);
        $report = $request->user()->reports()->create([
            'internship_id' => $internship->id,
            'titre' => $data['titre'] ?? 'Rapport - '.$internship->titre,
        ]);

        return response()->json($report->load('internship'), 201);
    }

    private function authorizeOwner(Request $request, Report $report): void
    {
        abort_unless($report->user_id === $request->user()->id, 404);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportSection;
use App\Models\SectionVersion;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportSectionController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);

        return $report->sections()->with('children')->get();
    }

    public function store(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);
        $data = $this->validated($request, $report);
        $parentId = $data['parent_id'] ?? null;
        $data['type'] = $data['type'] ?? ($parentId !== null ? 'sous_section' : 'chapitre');
        $this->ensureStructure($report->id, $parentId, $data['type']);
        $section = $report->sections()->create($data + ['ordre' => $data['ordre'] ?? $report->sections()->count()]);
        $this->reports->refreshProgress($report);

        return response()->json($section, 201);
    }

    public function update(Request $request, Report $report, ReportSection $section)
    {
        $this->authorizeSection($request, $report, $section);
        $data = $this->validated($request, $report, true);
        if (array_key_exists('parent_id', $data) || array_key_exists('type', $data)) {
            $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $section->parent_id;
            $type = $data['type'] ?? $section->type;
            if ($parentId !== null && ! array_key_exists('type', $data)) {
                $type = 'sous_section';
            } elseif ($parentId === null && $type === 'sous_section' && ! array_key_exists('type', $data)) {
                $type = 'section';
            }
            $this->ensureStructure($report->id, $parentId, $type);
            $data['type'] = $type;
        }

        DB::transaction(function () use ($request, $report, $section, $data) {
            if (array_key_exists('contenu', $data) && $data['contenu'] !== $section->contenu) {
                SectionVersion::create([
                    'report_section_id' => $section->id,
                    'user_id' => $request->user()->id,
                    'contenu' => $section->contenu ?? '',
                ]);
            }
            $section->update($data);
            $report->touch();
            $this->reports->refreshProgress($report);
        });

        return $section->fresh();
    }

    public function destroy(Request $request, Report $report, ReportSection $section)
    {
        $this->authorizeSection($request, $report, $section);
        $section->delete();
        $this->reports->refreshProgress($report);

        return response()->json(['message' => 'Section supprimée.']);
    }

    public function versions(Request $request, Report $report, ReportSection $section)
    {
        $this->authorizeSection($request, $report, $section);

        return $section->versions()->paginate(20);
    }

    public function restore(Request $request, Report $report, ReportSection $section, SectionVersion $version)
    {
        $this->authorizeSection($request, $report, $section);
        abort_unless($version->report_section_id === $section->id, 404);

        DB::transaction(function () use ($request, $report, $section, $version) {
            SectionVersion::create([
                'report_section_id' => $section->id,
                'user_id' => $request->user()->id,
                'contenu' => $section->contenu ?? '',
            ]);
            $section->update(['contenu' => $version->contenu]);
            $this->reports->refreshProgress($report);
        });

        return $section->fresh();
    }

    private function validated(Request $request, Report $report, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'titre' => [$required, 'string', 'max:180'],
            'type' => ['sometimes', 'string', Rule::in(['chapitre', 'section', 'sous_section', 'introduction', 'conclusion'])],
            'contenu' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
            'parent_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('report_sections', 'id')->where('rapport_id', $report->id),
            ],
        ]);
    }

    private function authorizeOwner(Request $request, Report $report): void
    {
        abort_unless($report->user_id === $request->user()->id, 404);
    }

    private function authorizeSection(Request $request, Report $report, ReportSection $section): void
    {
        $this->authorizeOwner($request, $report);
        abort_unless($section->rapport_id === $report->id, 404);
    }

    private function ensureStructure(int $reportId, ?int $parentId, string $type): void
    {
        abort_if(($parentId !== null) !== ($type === 'sous_section'), 422, 'Une sous-section doit appartenir à un chapitre et inversement.');

        if ($parentId !== null) {
            $parent = ReportSection::where('rapport_id', $reportId)->findOrFail($parentId);
            abort_if($parent->parent_id !== null, 422, 'Les sous-sections ne peuvent pas contenir d’autres niveaux.');
        }
    }
}

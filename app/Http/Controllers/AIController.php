<?php

namespace App\Http\Controllers;

use App\Exceptions\AIServiceException;
use App\Models\Dialogue;
use App\Models\GenerationSession;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\SectionVersion;
use App\Models\Webography;
use App\Services\AIService;
use App\Services\ReportService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    public function __construct(
        private AIService $ai,
        private TokenService $tokens,
        private ReportService $reports
    ) {}

    public function chat(Request $request)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:12000'],
            'rapport_id' => ['nullable', 'integer', 'exists:rapports,id'],
        ]);
        $report = isset($data['rapport_id']) ? $this->ownedReport($request, $data['rapport_id']) : null;
        $history = Dialogue::where('user_id', $request->user()->id)
            ->when($report, fn ($query) => $query->where('rapport_id', $report->id))
            ->latest('id')->limit(10)->get()->reverse()
            ->map(fn (Dialogue $item) => ['question' => $item->question, 'reponse' => $item->reponse])->values();
        $context = $report ? $this->context($report) : [];
        $payload = ['question' => $data['question'], 'history' => $history, 'context' => $context];
        $result = $this->execute($request, $report, 'dialogue', $data['question'], $payload);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $text = $result['data']['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            return $this->invalidAIResult($result['session'], 'Réponse de dialogue vide.');
        }

        $dialogue = Dialogue::create([
            'user_id' => $request->user()->id,
            'rapport_id' => $report?->id,
            'generation_session_id' => $result['session']->id,
            'question' => $data['question'],
            'reponse' => $text,
        ]);

        return response()->json(['dialogue' => $dialogue, 'tokens_consommes' => $result['session']->tokens_consommes]);
    }

    public function history(Request $request)
    {
        $data = $request->validate(['rapport_id' => ['nullable', 'integer', 'exists:rapports,id']]);
        $report = isset($data['rapport_id']) ? $this->ownedReport($request, $data['rapport_id']) : null;

        return Dialogue::where('user_id', $request->user()->id)
            ->when($report, fn ($query) => $query->where('rapport_id', $report->id))
            ->oldest('id')->limit(100)->get();
    }

    public function plan(Request $request)
    {
        $data = $request->validate(['rapport_id' => ['required', 'integer', 'exists:rapports,id']]);
        $report = $this->ownedReport($request, $data['rapport_id']);
        if ($report->sections()->exists()) {
            return response()->json(['message' => 'Le rapport contient déjà des sections. Supprimez sa structure actuelle avant de générer un nouveau plan.'], 409);
        }
        $prompt = 'Créer un plan détaillé de rapport de stage, adapté au thème et à la problématique fournis.';
        $result = $this->execute($request, $report, 'plan', $prompt, ['context' => $this->context($report)]);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $sections = $result['data']['sections'] ?? null;
        $validator = Validator::make(['sections' => $sections], [
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*.titre' => ['required', 'string', 'max:180'],
            'sections.*.type' => ['nullable', 'string', 'in:chapitre,section,sous_section'],
            'sections.*.children' => ['nullable', 'array', 'max:20'],
            'sections.*.children.*.titre' => ['required', 'string', 'max:180'],
        ]);
        if ($validator->fails()) {
            return $this->invalidAIResult($result['session'], 'Le plan généré n’a pas le format attendu.');
        }

        if (! $report->sections()->exists()) {
            foreach ($sections as $index => $item) {
                $parent = $report->sections()->create([
                    'titre' => $item['titre'],
                    'type' => 'chapitre',
                    'ordre' => $index,
                ]);
                foreach ($item['children'] ?? [] as $childIndex => $child) {
                    $report->sections()->create([
                        'parent_id' => $parent->id,
                        'titre' => $child['titre'],
                        'type' => 'sous_section',
                        'ordre' => $childIndex,
                    ]);
                }
            }
        }

        return response()->json([
            'sections' => $report->sections()->with('children')->get(),
            'tokens_consommes' => $result['session']->tokens_consommes,
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['required', 'integer', 'exists:rapports,id'],
            'section_id' => ['required', 'integer', 'exists:report_sections,id'],
            'consignes' => ['nullable', 'string', 'max:4000'],
        ]);
        $report = $this->ownedReport($request, $data['rapport_id']);
        $section = $report->sections()->whereKey($data['section_id'])->firstOrFail();
        $prompt = trim(($data['consignes'] ?? '')."\n".$section->titre);
        $result = $this->execute($request, $report, 'generation', $prompt, [
            'section_title' => $section->titre,
            'instructions' => $data['consignes'] ?? '',
            'context' => $this->context($report),
        ]);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $text = $result['data']['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            return $this->invalidAIResult($result['session'], 'Le texte généré est vide.');
        }

        $this->saveSectionContent($request, $report, $section, $text);

        return response()->json(['section' => $section->fresh(), 'tokens_consommes' => $result['session']->tokens_consommes]);
    }

    public function humanize(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['required', 'integer', 'exists:rapports,id'],
            'section_id' => ['nullable', 'integer', 'exists:report_sections,id'],
            'text' => ['required_without:section_id', 'nullable', 'string', 'max:30000'],
        ]);
        $report = $this->ownedReport($request, $data['rapport_id']);
        $section = isset($data['section_id'])
            ? $report->sections()->whereKey($data['section_id'])->firstOrFail()
            : null;
        $text = $data['text'] ?? $section?->contenu;
        abort_if(! is_string($text) || trim($text) === '', 422, 'Le texte à améliorer est vide.');

        $result = $this->execute($request, $report, 'humanisation', $text, [
            'text' => $text,
            'context' => $this->context($report),
        ]);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $improved = $result['data']['text'] ?? null;
        if (! is_string($improved) || trim($improved) === '') {
            return $this->invalidAIResult($result['session'], 'Le texte amélioré est vide.');
        }
        if ($section) {
            $this->saveSectionContent($request, $report, $section, $improved);
        }

        return response()->json(['text' => $improved, 'tokens_consommes' => $result['session']->tokens_consommes]);
    }

    public function research(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['required', 'integer', 'exists:rapports,id'],
            'question' => ['required', 'string', 'max:4000'],
        ]);
        $report = $this->ownedReport($request, $data['rapport_id']);
        $result = $this->execute($request, $report, 'webographie', $data['question'], [
            'question' => $data['question'],
            'context' => $this->context($report),
        ]);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $sources = $result['data']['sources'] ?? null;
        $validator = Validator::make(['sources' => $sources], [
            'sources' => ['required', 'array', 'max:20'],
            'sources.*.titre' => ['required', 'string', 'max:255'],
            'sources.*.url' => ['required', 'url:http,https', 'max:2048'],
            'sources.*.resume' => ['nullable', 'string', 'max:4000'],
            'sources.*.lien_verifie' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->invalidAIResult($result['session'], 'Les sources proposées n’ont pas le format attendu.');
        }

        $webographies = [];
        foreach ($sources as $source) {
            $webographies[] = Webography::create([
                'user_id' => $request->user()->id,
                'rapport_id' => $report->id,
                'titre' => $source['titre'],
                'url' => $source['url'],
                'resume' => $source['resume'] ?? null,
                'lien_verifie' => $source['lien_verifie'] ?? false,
                'verifie_le' => ($source['lien_verifie'] ?? false) ? now() : null,
            ]);
        }

        return response()->json(['sources' => $webographies, 'tokens_consommes' => $result['session']->tokens_consommes]);
    }

    public function webographies(Request $request, Report $report)
    {
        $this->authorizeOwner($request, $report);

        return $report->webographies()->latest()->get();
    }

    public function deleteWebography(Request $request, Report $report, Webography $webography)
    {
        $this->authorizeOwner($request, $report);
        abort_unless($webography->rapport_id === $report->id, 404);
        $webography->delete();

        return response()->json(['message' => 'Source supprimée.']);
    }

    private function execute(Request $request, ?Report $report, string $operation, string $prompt, array $payload): array|JsonResponse
    {
        $session = GenerationSession::create([
            'user_id' => $request->user()->id,
            'rapport_id' => $report?->id,
            'type_operation' => $operation,
            'modele' => config('services.ai_service.model'),
            'statut' => 'en_cours',
            'prompt' => $prompt,
        ]);

        try {
            $result = $this->ai->invoke('/api/'.$operation, $payload);
        } catch (AIServiceException $exception) {
            $session->update(['statut' => 'echec', 'error_message' => $exception->getMessage()]);
            Log::warning('AI service request failed.', ['session_id' => $session->id, 'operation' => $operation]);

            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }

        $text = $result['text'] ?? json_encode($result, JSON_UNESCAPED_UNICODE);
        $estimatedTokens = (int) ceil((
            mb_strlen($prompt)
            + mb_strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE))
            + mb_strlen((string) $text)
        ) / 4);
        $spent = isset($result['tokens_used']) && is_numeric($result['tokens_used']) && (int) $result['tokens_used'] > 0
            ? (int) $result['tokens_used']
            : max(1, $estimatedTokens);
        $session->update([
            'statut' => 'succes',
            'reponse' => is_string($text) ? $text : json_encode($text, JSON_UNESCAPED_UNICODE),
            'tokens_consommes' => $spent,
        ]);
        $this->tokens->consumeForSession($session->id);

        return ['session' => $session->fresh(), 'data' => $result];
    }

    private function invalidAIResult(GenerationSession $session, string $message): JsonResponse
    {
        $session->update(['statut' => 'echec', 'error_message' => $message]);

        return response()->json(['message' => 'Le service IA a renvoyé des données invalides.'], 502);
    }

    private function context(Report $report): array
    {
        $report->loadMissing(['internship', 'documents']);

        return [
            'report_title' => $report->titre,
            'theme' => $report->internship?->theme,
            'problematique' => $report->internship?->problematique,
            'entreprise' => $report->internship?->entreprise,
            'poste' => $report->internship?->poste,
            'sources' => $report->documents
                ->pluck('texte_extrait')->filter()->take(5)->map(fn ($text) => mb_substr($text, 0, 5000))->values(),
        ];
    }

    private function saveSectionContent(Request $request, Report $report, ReportSection $section, string $content): void
    {
        if ($section->contenu !== $content) {
            SectionVersion::create([
                'report_section_id' => $section->id,
                'user_id' => $request->user()->id,
                'contenu' => $section->contenu ?? '',
            ]);
        }
        $section->update(['contenu' => $content]);
        $this->reports->refreshProgress($report);
    }

    private function ownedReport(Request $request, int $id): Report
    {
        return $request->user()->reports()->whereKey($id)->firstOrFail();
    }

    private function authorizeOwner(Request $request, Report $report): void
    {
        abort_unless($report->user_id === $request->user()->id, 404);
    }
}

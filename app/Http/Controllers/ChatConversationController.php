<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\Report;
use Illuminate\Http\Request;

class ChatConversationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['nullable', 'integer', 'exists:rapports,id'],
        ]);
        if (isset($data['rapport_id'])) {
            $this->ownedReport($request, $data['rapport_id']);
        }

        return $request->user()->chatConversations()
            ->where('rapport_id', $data['rapport_id'] ?? null)
            ->withCount('dialogues')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['nullable', 'integer', 'exists:rapports,id'],
            'titre' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($data['rapport_id'])) {
            $this->ownedReport($request, $data['rapport_id']);
        }

        $conversation = $request->user()->chatConversations()->create([
            'rapport_id' => $data['rapport_id'] ?? null,
            'titre' => trim($data['titre'] ?? '') ?: 'Nouvelle discussion',
        ]);

        return response()->json($conversation->loadCount('dialogues'), 201);
    }

    public function update(Request $request, ChatConversation $conversation)
    {
        $this->authorizeOwner($request, $conversation);
        $data = $request->validate(['titre' => ['required', 'string', 'min:1', 'max:100']]);
        $conversation->update(['titre' => trim($data['titre'])]);

        return $conversation->fresh()->loadCount('dialogues');
    }

    public function destroy(Request $request, ChatConversation $conversation)
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return response()->json(['message' => 'Discussion supprimée.']);
    }

    public function documents(Request $request)
    {
        $data = $request->validate([
            'rapport_id' => ['nullable', 'integer', 'exists:rapports,id'],
        ]);
        $reportId = $data['rapport_id'] ?? null;
        if ($reportId !== null) {
            $this->ownedReport($request, $reportId);
        }

        return $request->user()->documents()
            ->whereNotNull('texte_extrait')
            ->where('texte_extrait', '<>', '')
            ->where(function ($query) use ($reportId) {
                $query->whereNull('rapport_id');
                if ($reportId !== null) {
                    $query->orWhere('rapport_id', $reportId);
                }
            })
            ->latest('updated_at')
            ->limit(100)
            ->get(['id', 'rapport_id', 'nom_original', 'mime_type', 'taille_bytes', 'updated_at']);
    }

    private function ownedReport(Request $request, int $id): Report
    {
        return $request->user()->reports()->whereKey($id)->firstOrFail();
    }

    private function authorizeOwner(Request $request, ChatConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }
}

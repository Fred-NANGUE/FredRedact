<?php

namespace App\Http\Controllers;

use App\Exceptions\AIServiceException;
use App\Models\Document;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private AIService $ai) {}

    public function index(Request $request)
    {
        return $request->user()->documents()->with('report:id,titre')->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,docx,jpg,jpeg,png,webp'],
            'rapport_id' => [
                'nullable', 'integer',
                Rule::exists('rapports', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $file = $data['file'];
        $path = $file->store('documents/'.$request->user()->id, 'local');
        if (! $path) {
            throw new \RuntimeException('Le fichier n’a pas pu être enregistré.');
        }

        try {
            $extracted = $this->ai->extract(
                Storage::disk('local')->path($path),
                $file->getClientOriginalName(),
                $file->getMimeType() ?: 'application/octet-stream'
            );
        } catch (AIServiceException $exception) {
            Storage::disk('local')->delete($path);
            Log::warning('Document extraction failed.', ['user_id' => $request->user()->id, 'reason' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }

        $document = $request->user()->documents()->create([
            'rapport_id' => $data['rapport_id'] ?? null,
            'nom_original' => $file->getClientOriginalName(),
            'chemin' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'taille_bytes' => $file->getSize(),
            'texte_extrait' => $extracted['text'] ?? '',
        ]);

        return response()->json($document, 201);
    }

    public function download(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);
        abort_unless(Storage::disk('local')->exists($document->chemin), 404);

        return Storage::disk('local')->download($document->chemin, $document->nom_original);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);
        if (Storage::disk('local')->exists($document->chemin) && ! Storage::disk('local')->delete($document->chemin)) {
            abort(500, 'Le fichier n’a pas pu être supprimé.');
        }
        $document->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }

    private function authorizeOwner(Request $request, Document $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 404);
    }
}

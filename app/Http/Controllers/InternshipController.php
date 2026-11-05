<?php

namespace App\Http\Controllers;

use App\Models\Internship;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->internships()->withCount('reports')->latest()->get();
    }

    public function store(Request $request)
    {
        $internship = $request->user()->internships()->create($this->validated($request));

        return response()->json($internship, 201);
    }

    public function show(Request $request, Internship $internship)
    {
        $this->authorizeOwner($request, $internship);

        return $internship->load('reports');
    }

    public function update(Request $request, Internship $internship)
    {
        $this->authorizeOwner($request, $internship);
        $internship->update($this->validated($request, true, $internship->date_debut?->toDateString()));

        return $internship->fresh();
    }

    public function destroy(Request $request, Internship $internship)
    {
        $this->authorizeOwner($request, $internship);
        $internship->delete();

        return response()->json(['message' => 'Stage supprimé.']);
    }

    private function validated(Request $request, bool $partial = false, ?string $existingStartDate = null): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'titre' => [$required, 'string', 'max:180'],
            'entreprise' => [$required, 'string', 'max:180'],
            'adresse_entreprise' => ['nullable', 'string', 'max:255'],
            'poste' => ['nullable', 'string', 'max:180'],
            'encadrant_nom' => ['nullable', 'string', 'max:180'],
            'encadrant_email' => ['nullable', 'email', 'max:255'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'theme' => ['nullable', 'string', 'max:10000'],
            'problematique' => ['nullable', 'string', 'max:10000'],
        ]);

        $startDate = $data['date_debut'] ?? $existingStartDate;
        if (! empty($data['date_fin']) && $startDate && Carbon::parse($data['date_fin'])->lt($startDate)) {
            throw ValidationException::withMessages(['date_fin' => 'La date de fin doit être égale ou postérieure à la date de début.']);
        }

        return $data;
    }

    private function authorizeOwner(Request $request, Internship $internship): void
    {
        abort_unless($internship->user_id === $request->user()->id, 404);
    }
}

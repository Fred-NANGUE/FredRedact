<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->withCount(['internships', 'reports'])
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));
    }

    public function show(User $user)
    {
        return $user->loadCount(['internships', 'reports']);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'etudiant'])],
        ]);

        if ($user->is($request->user()) && isset($data['role']) && $data['role'] !== 'admin') {
            return response()->json(['message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.'], 422);
        }

        $user->update($data);

        return $user->fresh();
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas supprimer votre propre compte administrateur.');
        foreach ($user->documents()->pluck('chemin') as $path) {
            if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                abort(500, 'Un document utilisateur n’a pas pu être supprimé.');
            }
        }
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}

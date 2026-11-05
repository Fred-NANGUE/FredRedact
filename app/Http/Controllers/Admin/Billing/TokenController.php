<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct(private TokenService $tokens) {}

    /** Solde + historique d'un utilisateur. */
    public function show(Request $request, User $user)
    {
        return [
            'user' => $user->only(['id', 'name', 'email']),
            'tokens_restants' => $this->tokens->balance($user),
            'tokens_consommes' => $this->tokens->consumed($user),
            'historique' => TokenUsage::where('user_id', $user->id)->latest('id')
                ->paginate((int) $request->integer('per_page', 20)),
        ];
    }

    /** Recharge manuelle (crédit). */
    public function credit(Request $request, User $user)
    {
        $data = $request->validate([
            'quantite' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->tokens->credit($user, $data['quantite'], 'recharge', [
            'subscription_id' => $this->tokens->currentSubscription($user)?->id,
            'description' => $data['description'] ?? 'Recharge manuelle (admin)',
        ]);

        return response()->json([
            'mouvement' => $entry,
            'tokens_restants' => $this->tokens->balance($user),
        ], 201);
    }

    /** Ajustement signé (correction : positif ou négatif). */
    public function adjust(Request $request, User $user)
    {
        $data = $request->validate([
            'quantite' => ['required', 'integer', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $entry = $this->tokens->adjust($user, $data['quantite'], $data['description'], [
            'subscription_id' => $this->tokens->currentSubscription($user)?->id,
        ]);

        return response()->json([
            'mouvement' => $entry,
            'tokens_restants' => $this->tokens->balance($user),
        ], 201);
    }
}

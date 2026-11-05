<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenController extends Controller
{
    public function __construct(private TokenService $tokens)
    {
    }

    public function balance(Request $request)
    {
        $user = $request->user();

        return [
            'tokens_restants'  => $this->tokens->balance($user),
            'tokens_consommes' => $this->tokens->consumed($user),
            'abonnement_actif' => $this->tokens->hasActiveSubscription($user),
        ];
    }

    /** Historique des mouvements (filtre optionnel ?type=generation). */
    public function history(Request $request)
    {
        return TokenUsage::where('user_id', $request->user()->id)
            ->when($request->query('type'), fn ($q, $type) => $q->where('type_operation', $type))
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));
    }

    /** Consommation par rapport. */
    public function byReport(Request $request)
    {
        return DB::table('v_report_token_usage')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('tokens_utilises')
            ->get();
    }
}

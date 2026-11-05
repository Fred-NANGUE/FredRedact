<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\TokenService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions, private TokenService $tokens)
    {
    }

    /** Mon abonnement courant + mon solde. */
    public function current(Request $request)
    {
        $user = $request->user();
        $subscription = $this->subscriptions->current($user)?->load('plan');

        return [
            'subscription'   => $subscription,
            'tokens_restants' => $this->tokens->balance($user),
            'tokens_consommes' => $this->tokens->consumed($user),
            'jours_restants' => $subscription?->date_fin
                ? (int) max(0, today()->diffInDays($subscription->date_fin, false))
                : null,
        ];
    }

    /** Historique de mes abonnements. */
    public function history(Request $request)
    {
        return Subscription::with('plan')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));
    }

    /** Annuler mon abonnement courant. */
    public function cancel(Request $request)
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->whereIn('statut', [Subscription::ACTIF, Subscription::EN_ATTENTE])
            ->with('plan')
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'message'      => 'Abonnement annulé.',
            'subscription' => $this->subscriptions->cancel($subscription),
        ]);
    }
}

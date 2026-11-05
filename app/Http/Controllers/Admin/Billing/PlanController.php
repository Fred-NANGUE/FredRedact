<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return SubscriptionPlan::withCount([
            'subscriptions',
            'subscriptions as abonnes_actifs_count' => fn ($q) => $q->where('statut', 'actif'),
        ])->orderBy('prix')->get();
    }

    public function show(SubscriptionPlan $plan)
    {
        return $plan->loadCount('subscriptions');
    }

    public function store(Request $request)
    {
        $plan = SubscriptionPlan::create($this->validated($request));

        return response()->json($plan, 201);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        // Les abonnements existants gardent leurs tokens/prix figés (copie à la souscription)
        $plan->update($this->validated($request, $plan));

        return $plan->fresh();
    }

    /** Supprime l'offre, ou la désactive si des abonnements y sont rattachés. */
    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['actif' => false]);

            return response()->json(['message' => 'Offre désactivée (des abonnements y sont rattachés).']);
        }

        $plan->delete();

        return response()->json(['message' => 'Offre supprimée.']);
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $sometimes = $plan ? 'sometimes' : 'required';

        return $request->validate([
            'nom'           => [$sometimes, 'string', 'max:50', Rule::unique('subscription_plans', 'nom')->ignore($plan?->id)],
            'description'   => ['nullable', 'string'],
            'prix'          => [$sometimes, 'integer', 'min:0', 'max:99999999'],
            'tokens_inclus' => [$sometimes, 'integer', 'min:0'],
            'duree_jours'   => ['nullable', 'integer', 'min:1'],
            'actif'         => ['sometimes', 'boolean'],
        ]);
    }
}

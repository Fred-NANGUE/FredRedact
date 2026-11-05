<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /** Liste filtrable : ?statut=actif&plan_id=2&q=aicha */
    public function index(Request $request)
    {
        return Subscription::with(['user:id,name,email', 'plan:id,nom,prix'])
            ->when($request->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($request->query('plan_id'), fn ($q, $v) => $q->where('plan_id', $v))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('user', fn ($u) => $u
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%"));
            })
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));
    }

    public function show(Subscription $subscription)
    {
        return $subscription->load(['user:id,name,email', 'plan', 'tokenUsages' => fn ($q) => $q->latest('id')->limit(50)]);
    }

    /** L'admin attribue une offre à un utilisateur (paiement considéré confirmé). */
    public function assign(Request $request, User $user)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'mode_paiement' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
        ]);

        $subscription = $this->subscriptions->subscribe(
            $user,
            SubscriptionPlan::findOrFail($data['plan_id']),
            ['confirmed' => true, 'mode_paiement' => $data['mode_paiement'] ?? 'admin', 'transaction_id' => $data['transaction_id'] ?? null]
        );

        return response()->json($subscription, 201);
    }

    public function activate(Request $request, Subscription $subscription)
    {
        $request->validate(['transaction_id' => ['nullable', 'string', 'max:100']]);

        return $this->subscriptions->activate($subscription->load('plan'), $request->input('transaction_id'));
    }

    public function suspend(Subscription $subscription)
    {
        return $this->subscriptions->suspend($subscription->load('plan'));
    }

    public function reactivate(Subscription $subscription)
    {
        return $this->subscriptions->reactivate($subscription->load('plan'));
    }

    public function cancel(Subscription $subscription)
    {
        return $this->subscriptions->cancel($subscription->load('plan'));
    }
}

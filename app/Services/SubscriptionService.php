<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(private TokenService $tokens)
    {
    }

    public function current(User $user): ?Subscription
    {
        return $this->tokens->currentSubscription($user);
    }

    /* ------------------------------------------------------------------ */
    /*  Souscription                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Souscrit à une offre.
     * - offre gratuite : interdite ici (attribuée automatiquement, une seule fois)
     * - offre payante  : "en_attente" tant que le paiement n'est pas confirmé
     *
     * $payment : mode_paiement, transaction_id, confirmed (bool)
     */
    public function subscribe(User $user, SubscriptionPlan $plan, array $payment = []): Subscription
    {
        if (! $plan->actif) {
            throw ValidationException::withMessages(['plan_id' => "Cette offre n'est plus disponible."]);
        }

        if ($plan->isFree()) {
            throw ValidationException::withMessages(['plan_id' => "L'offre Free est attribuée automatiquement."]);
        }

        return DB::transaction(function () use ($user, $plan, $payment) {
            $this->lockUser($user->id);

            $confirmed = ! $plan->isPaid()
                || ($payment['confirmed'] ?? false)
                || (! ($payment['force_pending'] ?? false) && config('billing.auto_confirm_payments'));

            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'date_debut'     => today(),
                'date_fin'       => $plan->duree_jours ? today()->addDays($plan->duree_jours) : null,
                'tokens_alloues' => $plan->tokens_inclus,
                'prix_paye'      => 0,
                'devise'         => $payment['devise'] ?? 'XAF',
                'statut'         => Subscription::EN_ATTENTE,
                'mode_paiement'  => $payment['mode_paiement'] ?? null,
                'transaction_id' => $payment['transaction_id'] ?? null,
            ]);

            if ($confirmed) {
                $this->finalizeActivation($subscription, $payment);
            }

            return $subscription->fresh('plan');
        });
    }

    /** Confirme le paiement d'un abonnement "en_attente" et l'active. */
    public function activate(Subscription $subscription, ?string $transactionId = null, array $payment = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $transactionId, $payment) {
            $this->lockUser($subscription->user_id);

            $subscription = Subscription::with('plan')->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->statut === Subscription::ACTIF && $transactionId === $subscription->transaction_id) {
                return $subscription->fresh('plan');
            }
            if ($subscription->statut !== Subscription::EN_ATTENTE) {
                throw ValidationException::withMessages(['subscription' => "Seul un abonnement en attente peut être activé."]);
            }

            $plan = $subscription->plan;
            $subscription->fill([
                'date_debut'     => today(),
                'date_fin'       => $plan->duree_jours ? today()->addDays($plan->duree_jours) : null,
                'tokens_alloues' => $plan->tokens_inclus,
                'transaction_id' => $transactionId ?? $subscription->transaction_id,
                'prix_paye'      => $payment['prix_paye'] ?? $subscription->prix_paye,
                'devise'         => $payment['devise'] ?? $subscription->devise,
            ])->save();

            $this->finalizeActivation($subscription, $payment);

            return $subscription->fresh('plan');
        });
    }

    /** Renouvelle : souscrit de nouveau à la même offre que $subscription. */
    public function renew(Subscription $subscription, array $payment = []): Subscription
    {
        return $this->subscribe($subscription->user, $subscription->plan, $payment);
    }

    /* ------------------------------------------------------------------ */
    /*  Cycle de vie                                                       */
    /* ------------------------------------------------------------------ */

    public function cancel(Subscription $subscription): Subscription
    {
        if (! in_array($subscription->statut, [Subscription::ACTIF, Subscription::SUSPENDU, Subscription::EN_ATTENTE], true)) {
            throw ValidationException::withMessages(['subscription' => "Cet abonnement ne peut plus être annulé."]);
        }
        if ($subscription->plan->isFree()) {
            throw ValidationException::withMessages(['subscription' => "L'offre Free ne peut pas être annulée."]);
        }

        return DB::transaction(function () use ($subscription) {
            $this->lockUser($subscription->user_id);

            $hadTokens = in_array($subscription->statut, [Subscription::ACTIF, Subscription::SUSPENDU], true);
            $subscription->update(['statut' => Subscription::ANNULE]);

            if ($hadTokens) {
                $this->tokens->retireBalance($subscription->user_id, 'Annulation - ' . $subscription->plan->nom, $subscription->id);
            }
            $this->ensureFreeSubscription($subscription->user);

            return $subscription->fresh('plan');
        });
    }

    public function suspend(Subscription $subscription): Subscription
    {
        if ($subscription->statut !== Subscription::ACTIF) {
            throw ValidationException::withMessages(['subscription' => "Seul un abonnement actif peut être suspendu."]);
        }
        $subscription->update(['statut' => Subscription::SUSPENDU]);

        return $subscription->fresh('plan');
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        if ($subscription->statut !== Subscription::SUSPENDU) {
            throw ValidationException::withMessages(['subscription' => "Seul un abonnement suspendu peut être réactivé."]);
        }
        $subscription->update(['statut' => Subscription::ACTIF]);

        // S'il est arrivé à échéance pendant la suspension, on l'expire tout de suite
        if ($subscription->date_fin && $subscription->date_fin->lt(today())) {
            $this->expire($subscription);
        }

        return $subscription->fresh('plan');
    }

    /** Expire un abonnement : retire le solde restant et bascule sur Free. */
    public function expire(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $this->lockUser($subscription->user_id);

            $subscription->update(['statut' => Subscription::EXPIRE]);
            $this->tokens->retireBalance($subscription->user_id, 'Expiration - ' . $subscription->plan->nom, $subscription->id);
            $this->ensureFreeSubscription($subscription->user);

            return $subscription->fresh('plan');
        });
    }

    /** Appelé chaque jour par le planificateur. Retourne le nombre d'abonnements expirés. */
    public function expireDue(): int
    {
        $count = 0;

        Subscription::echus()->with(['plan', 'user'])->chunkById(100, function ($subscriptions) use (&$count) {
            foreach ($subscriptions as $subscription) {
                $this->expire($subscription);
                $count++;
            }
        });

        return $count;
    }

    /* ------------------------------------------------------------------ */
    /*  Offre gratuite                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Garantit que l'utilisateur a un abonnement actif.
     * À appeler à l'inscription (voir le listener AssignFreePlan).
     * Les tokens Free ne sont accordés qu'UNE seule fois par utilisateur.
     */
    public function ensureFreeSubscription(User $user): ?Subscription
    {
        $existing = Subscription::where('user_id', $user->id)->utilisable()->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        $plan = SubscriptionPlan::where('nom', config('billing.free_plan_name'))->first();
        if (! $plan) {
            Log::warning("Offre gratuite '" . config('billing.free_plan_name') . "' introuvable.");
            return null;
        }

        return DB::transaction(function () use ($user, $plan) {
            $this->lockUser($user->id);

            $alreadyHadFree = Subscription::where('user_id', $user->id)->where('plan_id', $plan->id)->exists();

            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'date_debut'     => today(),
                'date_fin'       => null,
                'tokens_alloues' => $alreadyHadFree ? 0 : $plan->tokens_inclus,
                'prix_paye'      => 0,
                'devise'         => 'XAF',
                'statut'         => Subscription::ACTIF,
                'mode_paiement'  => 'free',
            ]);

            if ($subscription->tokens_alloues > 0) {
                $this->tokens->credit($user, $subscription->tokens_alloues, 'allocation', [
                    'subscription_id' => $subscription->id,
                    'description'     => 'Allocation initiale - ' . $plan->nom,
                ]);
            }

            return $subscription;
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Interne                                                            */
    /* ------------------------------------------------------------------ */

    /** Active la souscription : remplace les anciennes, alloue les tokens. */
    private function finalizeActivation(Subscription $subscription, array $payment = []): void
    {
        $plan = $subscription->plan ?? $subscription->load('plan')->plan;

        // Remplace les autres abonnements actifs ou suspendus
        $others = Subscription::where('user_id', $subscription->user_id)
            ->whereIn('statut', [Subscription::ACTIF, Subscription::SUSPENDU])
            ->where('id', '!=', $subscription->id)
            ->get();

        foreach ($others as $old) {
            $old->update(['statut' => Subscription::ANNULE]);
        }
        if ($others->isNotEmpty() && ! config('billing.carry_over_on_change')) {
            $this->tokens->retireBalance($subscription->user_id, "Changement d'offre", $others->last()->id);
        }

        $subscription->update([
            'statut'    => Subscription::ACTIF,
            'prix_paye' => $payment['prix_paye'] ?? $plan->prix,
            'devise'    => $payment['devise'] ?? $subscription->devise ?? 'XAF',
        ]);

        if ($subscription->tokens_alloues > 0) {
            $this->tokens->credit($subscription->user_id, $subscription->tokens_alloues, 'allocation', [
                'subscription_id' => $subscription->id,
                'description'     => 'Allocation - ' . $plan->nom,
            ]);
        }
    }

    private function lockUser(int $userId): void
    {
        DB::table('users')->where('id', $userId)->lockForUpdate()->value('id');
    }
}

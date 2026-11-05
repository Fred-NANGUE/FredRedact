<?php

namespace App\Services;

use App\Exceptions\Billing\InsufficientTokensException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Models\Subscription;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Grand livre des tokens.
 * Règle : quantite > 0 = crédit, quantite < 0 = consommation.
 * Le solde n'est jamais stocké : il vaut SUM(quantite).
 */
class TokenService
{
    /* ------------------------------------------------------------------ */
    /*  Lecture                                                            */
    /* ------------------------------------------------------------------ */

    public function balance(User|int $user): int
    {
        return (int) (DB::table('v_user_token_balance')
            ->where('user_id', $this->id($user))
            ->value('tokens_restants') ?? 0);
    }

    /** Total consommé par l'IA (hors ajustements administratifs). */
    public function consumed(User|int $user): int
    {
        return (int) -TokenUsage::where('user_id', $this->id($user))
            ->whereIn('type_operation', TokenUsage::CONSUMPTION_TYPES)
            ->where('quantite', '<', 0)
            ->sum('quantite');
    }

    public function currentSubscription(User|int $user): ?Subscription
    {
        return Subscription::where('user_id', $this->id($user))
            ->utilisable()
            ->latest('id')
            ->first();
    }

    public function hasActiveSubscription(User|int $user): bool
    {
        return $this->currentSubscription($user) !== null;
    }

    /** Vrai si l'utilisateur a un abonnement utilisable et au moins $needed tokens. */
    public function canConsume(User|int $user, int $needed = 1): bool
    {
        return $this->hasActiveSubscription($user) && $this->balance($user) >= $needed;
    }

    /* ------------------------------------------------------------------ */
    /*  Débit                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Débite $tokens du solde.
     *
     * @param array $context rapport_id, generation_session_id, description
     * @param bool  $force   true = enregistre la consommation même si le solde est insuffisant
     *                       (à utiliser quand l'appel IA a DÉJÀ eu lieu et que les tokens sont dépensés).
     *
     * @throws InsufficientTokensException
     * @throws NoActiveSubscriptionException
     */
    public function consume(User|int $user, int $tokens, string $type, array $context = [], bool $force = false): TokenUsage
    {
        if ($tokens <= 0) {
            throw new InvalidArgumentException('Le nombre de tokens à consommer doit être positif.');
        }
        if (! in_array($type, TokenUsage::CONSUMPTION_TYPES, true)) {
            throw new InvalidArgumentException("Type d'opération invalide : {$type}");
        }

        $userId = $this->id($user);

        return DB::transaction(function () use ($userId, $tokens, $type, $context, $force) {
            $this->lockUser($userId);   // sérialise les débits concurrents d'un même utilisateur

            // Idempotence : une session IA n'est débitée qu'une seule fois
            if (! empty($context['generation_session_id'])) {
                $existing = TokenUsage::where('generation_session_id', $context['generation_session_id'])
                    ->where('quantite', '<', 0)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $subscription = $this->currentSubscription($userId);
            if (! $subscription && ! $force) {
                throw new NoActiveSubscriptionException();
            }

            $balance = $this->balance($userId);
            if (! $force && $balance < $tokens) {
                throw new InsufficientTokensException($tokens, $balance);
            }

            return TokenUsage::create([
                'user_id'               => $userId,
                'subscription_id'       => $subscription?->id,
                'rapport_id'            => $context['rapport_id'] ?? null,
                'generation_session_id' => $context['generation_session_id'] ?? null,
                'quantite'              => -$tokens,
                'type_operation'        => $type,
                'description'           => $context['description'] ?? null,
            ]);
        });
    }

    /**
     * Point d'entrée pour les Membres 3 et 4 :
     * débite automatiquement les tokens d'une ligne de generation_sessions.
     * Ne débite rien si l'appel IA a échoué.
     */
    public function consumeForSession(int $sessionId, bool $force = true): ?TokenUsage
    {
        $session = DB::table('generation_sessions')->where('id', $sessionId)->first();

        if (! $session || $session->statut !== 'succes' || (int) $session->tokens_consommes <= 0) {
            return null;
        }

        return $this->consume(
            (int) $session->user_id,
            (int) $session->tokens_consommes,
            $session->type_operation,
            [
                'rapport_id'            => $session->rapport_id,
                'generation_session_id' => $sessionId,
                'description'           => "IA ({$session->modele}) - {$session->type_operation}",
            ],
            $force
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Crédit / ajustement / retrait                                      */
    /* ------------------------------------------------------------------ */

    /** Ajoute des tokens (allocation, recharge). */
    public function credit(User|int $user, int $tokens, string $type = 'recharge', array $context = []): TokenUsage
    {
        if ($tokens <= 0) {
            throw new InvalidArgumentException('Le crédit doit être positif.');
        }
        if (! in_array($type, ['allocation', 'recharge', 'ajustement'], true)) {
            throw new InvalidArgumentException("Type de crédit invalide : {$type}");
        }

        return $this->record($this->id($user), $tokens, $type, $context);
    }

    /** Ajustement administratif signé (positif ou négatif). */
    public function adjust(User|int $user, int $delta, string $description, array $context = []): TokenUsage
    {
        if ($delta === 0) {
            throw new InvalidArgumentException("L'ajustement ne peut pas être nul.");
        }

        return $this->record($this->id($user), $delta, 'ajustement', $context + ['description' => $description]);
    }

    /**
     * Retire tout le solde positif restant (fin, annulation ou remplacement d'offre).
     * Retourne le nombre de tokens retirés.
     */
    public function retireBalance(User|int $user, string $reason, ?int $subscriptionId = null): int
    {
        $userId = $this->id($user);

        return DB::transaction(function () use ($userId, $reason, $subscriptionId) {
            $this->lockUser($userId);
            $balance = $this->balance($userId);

            if ($balance > 0) {
                $this->record($userId, -$balance, 'ajustement', [
                    'subscription_id' => $subscriptionId,
                    'description'     => $reason,
                ]);
                return $balance;
            }
            return 0;
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Interne                                                            */
    /* ------------------------------------------------------------------ */

    private function record(int $userId, int $quantite, string $type, array $context): TokenUsage
    {
        return TokenUsage::create([
            'user_id'               => $userId,
            'subscription_id'       => $context['subscription_id'] ?? null,
            'rapport_id'            => $context['rapport_id'] ?? null,
            'generation_session_id' => $context['generation_session_id'] ?? null,
            'quantite'              => $quantite,
            'type_operation'        => $type,
            'description'           => $context['description'] ?? null,
        ]);
    }

    private function lockUser(int $userId): void
    {
        DB::table('users')->where('id', $userId)->lockForUpdate()->value('id');
    }

    private function id(User|int $user): int
    {
        return $user instanceof User ? $user->id : $user;
    }
}

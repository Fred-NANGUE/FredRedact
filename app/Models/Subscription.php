<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const ACTIF      = 'actif';
    public const EXPIRE     = 'expire';
    public const SUSPENDU   = 'suspendu';
    public const ANNULE     = 'annule';
    public const EN_ATTENTE = 'en_attente';

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id', 'plan_id', 'date_debut', 'date_fin', 'tokens_alloues',
        'prix_paye', 'devise', 'statut', 'mode_paiement', 'transaction_id',
    ];

    protected $casts = [
        'date_debut'     => 'date',
        'date_fin'       => 'date',
        'tokens_alloues' => 'integer',
        'prix_paye'      => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function tokenUsages(): HasMany
    {
        return $this->hasMany(TokenUsage::class, 'subscription_id');
    }

    /** Abonnement actif ET non échu. */
    public function scopeUtilisable(Builder $query): Builder
    {
        return $query->where('statut', self::ACTIF)
            ->where(fn (Builder $q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()->toDateString()));
    }

    /** Abonnements marqués actifs mais dont la date de fin est dépassée. */
    public function scopeEchus(Builder $query): Builder
    {
        return $query->where('statut', self::ACTIF)
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', today()->toDateString());
    }

    public function isUtilisable(): bool
    {
        return $this->statut === self::ACTIF
            && ($this->date_fin === null || $this->date_fin->gte(today()));
    }
}

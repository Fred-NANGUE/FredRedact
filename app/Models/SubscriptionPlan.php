<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = ['nom', 'description', 'prix', 'tokens_inclus', 'duree_jours', 'actif'];

    protected $casts = [
        'prix'          => 'decimal:2',
        'tokens_inclus' => 'integer',
        'duree_jours'   => 'integer',
        'actif'         => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function isFree(): bool
    {
        return strcasecmp($this->nom, config('billing.free_plan_name')) === 0;
    }

    public function isPaid(): bool
    {
        return (float) $this->prix > 0;
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\Subscription;
use App\Models\TokenUsage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * À ajouter dans App\Models\User :  use HasSubscriptions;
 */
trait HasSubscriptions
{
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->ofMany('id', 'max', fn ($query) => $query->utilisable());
    }

    public function tokenUsages(): HasMany
    {
        return $this->hasMany(TokenUsage::class);
    }

    public function tokenBalance(): int
    {
        return app(\App\Services\TokenService::class)->balance($this);
    }
}

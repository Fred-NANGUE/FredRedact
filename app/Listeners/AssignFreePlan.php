<?php

namespace App\Listeners;

use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;

/**
 * Attribue l'offre Free et ses tokens dès l'inscription.
 * Déclenché par l'événement Registered (event(new Registered($user)) côté Membre 1).
 */
class AssignFreePlan
{
    public function __construct(private SubscriptionService $subscriptions)
    {
    }

    public function handle(Registered $event): void
    {
        $this->subscriptions->ensureFreeSubscription($event->user);
    }
}

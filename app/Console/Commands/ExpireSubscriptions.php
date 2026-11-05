<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire les abonnements arrivés à échéance et bascule les utilisateurs sur l\'offre Free';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();
        $this->info("{$count} abonnement(s) expiré(s).");

        return self::SUCCESS;
    }
}

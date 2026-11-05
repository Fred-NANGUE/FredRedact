<?php

namespace App\Exceptions\Billing;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class NoActiveSubscriptionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Aucun abonnement actif. Souscrivez à une offre pour continuer.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'no_active_subscription',
        ], 403);
    }
}

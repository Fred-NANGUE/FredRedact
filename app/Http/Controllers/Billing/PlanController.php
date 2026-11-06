<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\PayPalService;

class PlanController extends Controller
{
    /** Catalogue public des offres actives (page tarifs). */
    public function index(PayPalService $paypal)
    {
        return SubscriptionPlan::actif()->orderBy('prix')->get()->map(function (SubscriptionPlan $plan) use ($paypal) {
            $plan->setAttribute('paypal_price_usd', $paypal->amountInUsd($plan->prix));
            $plan->setAttribute('paypal_available', $paypal->isConfigured());
            $plan->setAttribute('paypal_xaf_per_usd', $paypal->xafPerUsd());

            return $plan;
        });
    }
}

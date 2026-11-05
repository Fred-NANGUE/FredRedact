<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;

class PlanController extends Controller
{
    /** Catalogue public des offres actives (page tarifs). */
    public function index()
    {
        return SubscriptionPlan::actif()->orderBy('prix')->get();
    }
}

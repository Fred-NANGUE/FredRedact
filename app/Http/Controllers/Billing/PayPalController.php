<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PayPalService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalController extends Controller
{
    public function checkout(Request $request, PayPalService $paypal, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);
        if (! $paypal->isConfigured()) {
            return response()->json(['message' => 'PayPal Sandbox n’est pas configuré. Contactez l’administrateur.'], 503);
        }

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $subscription = $subscriptions->subscribe($request->user(), $plan, [
            'force_pending' => true,
            'mode_paiement' => 'paypal',
            'devise' => 'USD',
            'transaction_id' => 'FR'.Str::upper((string) Str::ulid()),
        ]);

        try {
            $order = $paypal->createOrder($subscription);
        } catch (RuntimeException $exception) {
            $subscriptions->cancel($subscription);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'message' => 'Redirection vers le paiement sécurisé PayPal Sandbox.',
            'approval_url' => $order['approval_url'],
        ], 201);
    }

    public function capture(Request $request, PayPalService $paypal, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'order_id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9-]+$/'],
            'subscription_id' => ['required', 'integer'],
        ]);
        $subscription = Subscription::with('plan')
            ->where('id', $data['subscription_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($subscription->statut === Subscription::ACTIF && $subscription->transaction_id === $data['order_id']) {
            return response()->json(['message' => 'Paiement déjà confirmé.', 'subscription' => $subscription]);
        }
        if ($subscription->statut !== Subscription::EN_ATTENTE || $subscription->mode_paiement !== 'paypal') {
            return response()->json(['message' => 'Cet abonnement PayPal n’est plus en attente.'], 409);
        }

        try {
            $amount = $paypal->captureOrder($data['order_id'], $subscription);
        } catch (RuntimeException $exception) {
            Log::warning('PayPal payment could not be verified and captured.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $subscription = $subscriptions->activate($subscription, $data['order_id'], [
            'prix_paye' => $amount,
            'devise' => 'USD',
        ]);

        return response()->json(['message' => 'Paiement PayPal confirmé.', 'subscription' => $subscription]);
    }
}

<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\CinetPayService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CinetPayController extends Controller
{
    public function checkout(Request $request, CinetPayService $cinetPay, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'mode_paiement' => ['required', 'in:orange_money,mtn_mobile_money'],
            'customer_phone' => ['required', 'string', 'regex:/^(?:\+?237)?6\d{8}$/'],
        ]);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        if (! $cinetPay->isConfigured()) {
            return response()->json([
                'message' => 'Le paiement en ligne n’est pas encore configuré. Contactez l’administrateur.',
            ], 503);
        }

        $subscription = $subscriptions->subscribe($request->user(), $plan, [
            'force_pending' => true,
            'mode_paiement' => $data['mode_paiement'],
            'transaction_id' => 'FR'.Str::upper((string) Str::ulid()),
        ]);
        $phoneNumber = preg_replace('/\D/', '', $data['customer_phone']);
        if (strlen($phoneNumber) === 9) {
            $phoneNumber = '237'.$phoneNumber;
        }

        try {
            $checkoutUrl = $cinetPay->createCheckout($subscription, $phoneNumber);
        } catch (RuntimeException $exception) {
            $subscriptions->cancel($subscription);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'message' => 'Redirection vers le paiement sécurisé CinetPay.',
            'checkout_url' => $checkoutUrl,
            'subscription' => $subscription,
        ], 201);
    }

    public function notify(Request $request, CinetPayService $cinetPay, SubscriptionService $subscriptions)
    {
        $transactionId = $request->input('cpm_trans_id', $request->input('transaction_id'));
        if (! is_string($transactionId) || $transactionId === '') {
            return response()->json(['message' => 'Référence de transaction manquante.'], 400);
        }

        $subscription = Subscription::with(['plan', 'user'])
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Abonnement introuvable.'], 404);
        }

        if ($subscription->statut === Subscription::ACTIF) {
            return response()->json(['message' => 'Paiement déjà confirmé.']);
        }

        if ($subscription->statut !== Subscription::EN_ATTENTE) {
            return response()->json(['message' => 'Cet abonnement n’est plus en attente.'], 409);
        }

        try {
            $accepted = $cinetPay->isPaymentAccepted(
                $transactionId,
                (int) round((float) $subscription->plan->prix),
            );
        } catch (RuntimeException $exception) {
            Log::warning('CinetPay callback could not verify payment.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        if (! $accepted) {
            return response()->json(['message' => 'Le paiement n’a pas été confirmé par CinetPay.'], 422);
        }

        $subscriptions->activate($subscription, $transactionId);

        return response()->json(['message' => 'Paiement confirmé.']);
    }
}

<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CinetPayService
{
    public function isConfigured(): bool
    {
        return filled(config('services.cinetpay.api_key'))
            && filled(config('services.cinetpay.site_id'));
    }

    public function createCheckout(Subscription $subscription, string $phoneNumber): string
    {
        $this->ensureConfigured();

        $user = $subscription->user;
        $payload = [
            'apikey' => config('services.cinetpay.api_key'),
            'site_id' => config('services.cinetpay.site_id'),
            'transaction_id' => $subscription->transaction_id,
            'amount' => (int) round((float) $subscription->plan->prix),
            'currency' => 'XAF',
            'description' => 'Abonnement '.$subscription->plan->nom.' - FredRedact',
            'customer_id' => (string) $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone_number' => $phoneNumber,
            'customer_country' => 'CM',
            'channels' => 'MOBILE_MONEY',
            'lang' => 'fr',
            'notify_url' => rtrim(config('app.url'), '/').'/api/billing/cinetpay/notify',
            'return_url' => rtrim(config('services.frontend_url'), '/').'/?payment=return',
            'metadata' => (string) $subscription->id,
        ];
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->post(rtrim(config('services.cinetpay.base_url'), '/').'/v2/payment', $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('CinetPay checkout request failed to connect.', ['subscription_id' => $subscription->id]);
            throw new RuntimeException('Le service de paiement est momentanément inaccessible.', previous: $exception);
        }

        $body = $response->json();
        $paymentUrl = data_get($body, 'data.payment_url');
        if (! $response->successful() || data_get($body, 'code') !== '201' || ! is_string($paymentUrl) || ! filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            Log::warning('CinetPay rejected checkout initialization.', [
                'subscription_id' => $subscription->id,
                'http_status' => $response->status(),
                'provider_code' => data_get($body, 'code'),
            ]);
            throw new RuntimeException('CinetPay n’a pas pu démarrer le paiement. Réessayez ou contactez l’administrateur.');
        }

        return $paymentUrl;
    }

    public function isPaymentAccepted(string $transactionId, int $amount): bool
    {
        $this->ensureConfigured();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(rtrim(config('services.cinetpay.base_url'), '/').'/v2/payment/check', [
                    'apikey' => config('services.cinetpay.api_key'),
                    'site_id' => config('services.cinetpay.site_id'),
                    'transaction_id' => $transactionId,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('CinetPay payment verification could not connect.', ['transaction_id' => $transactionId]);
            throw new RuntimeException('La vérification du paiement est temporairement indisponible.', previous: $exception);
        }

        $body = $response->json();
        $details = data_get($body, 'data');

        return $response->successful()
            && data_get($body, 'code') === '00'
            && data_get($details, 'status') === 'ACCEPTED'
            && (int) data_get($details, 'amount') === $amount
            && data_get($details, 'currency') === 'XAF'
            && (string) data_get($details, 'transaction_id', data_get($details, 'cpm_trans_id')) === $transactionId;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('CinetPay n’est pas configuré. Ajoutez CINETPAY_API_KEY et CINETPAY_SITE_ID au fichier .env.');
        }
    }
}

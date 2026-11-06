<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalService
{
    private const API_URL = 'https://api-m.sandbox.paypal.com';

    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'))
            && (float) config('services.paypal.xaf_per_usd') > 0;
    }

    public function amountInUsd(float|int|string $xafAmount): string
    {
        $rate = $this->xafPerUsd();
        return number_format(round(((float) $xafAmount / $rate) * 100) / 100, 2, '.', '');
    }

    public function xafPerUsd(): float
    {
        $rate = (float) config('services.paypal.xaf_per_usd');
        if ($rate <= 0) {
            throw new RuntimeException('Le taux XAF/USD PayPal doit être supérieur à zéro.');
        }

        return $rate;
    }

    public function createOrder(Subscription $subscription): array
    {
        $this->ensureConfigured();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $subscription->id,
                'custom_id' => (string) $subscription->id,
                'invoice_id' => $subscription->transaction_id,
                'description' => 'Abonnement '.$subscription->plan->nom.' - FredRedact',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $this->amountInUsd($subscription->plan->prix),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'FredRedact',
                'user_action' => 'PAY_NOW',
                'return_url' => rtrim(config('services.frontend_url'), '/').'?payment=paypal-return&subscription_id='.$subscription->id,
                'cancel_url' => rtrim(config('services.frontend_url'), '/').'?payment=paypal-cancel&subscription_id='.$subscription->id,
            ],
        ];

        $response = $this->request('post', '/v2/checkout/orders', $payload);
        $orderId = data_get($response, 'id');
        $approvalLink = collect(data_get($response, 'links', []))
            ->first(fn ($link) => in_array($link['rel'] ?? null, ['approve', 'payer-action'], true));
        $approvalUrl = $approvalLink['href'] ?? null;

        if (! is_string($orderId) || ! is_string($approvalUrl)
            || parse_url($approvalUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($approvalUrl, PHP_URL_HOST) !== 'www.sandbox.paypal.com') {
            Log::warning('PayPal returned an invalid order approval link.', [
                'subscription_id' => $subscription->id,
            ]);
            throw new RuntimeException('PayPal n’a pas pu démarrer le paiement. Réessayez ou contactez l’administrateur.');
        }

        return ['id' => $orderId, 'approval_url' => $approvalUrl];
    }

    public function captureOrder(string $orderId, Subscription $subscription): string
    {
        $this->ensureConfigured();

        $order = $this->request('get', '/v2/checkout/orders/'.rawurlencode($orderId));
        $expectedAmount = $this->amountInUsd($subscription->plan->prix);
        $this->assertOrderMatches($order, $orderId, $subscription, $expectedAmount);

        if (data_get($order, 'status') === 'COMPLETED') {
            $capture = data_get($order, 'purchase_units.0.payments.captures.0');
        } elseif (data_get($order, 'status') === 'APPROVED') {
            $captured = $this->request(
                'post',
                '/v2/checkout/orders/'.rawurlencode($orderId).'/capture',
                [],
                'fredredact-capture-'.$subscription->id,
            );
            $this->assertOrderIdentity($captured, $orderId, $subscription);
            if (data_get($captured, 'status') !== 'COMPLETED') {
                throw new RuntimeException('PayPal n’a pas confirmé la capture du paiement.');
            }
            $capture = data_get($captured, 'purchase_units.0.payments.captures.0');
        } else {
            throw new RuntimeException('La commande PayPal n’a pas été approuvée.');
        }

        if (data_get($capture, 'status') !== 'COMPLETED'
            || data_get($capture, 'amount.currency_code') !== 'USD'
            || $this->normalizeAmount(data_get($capture, 'amount.value')) !== $expectedAmount) {
            throw new RuntimeException('Le montant ou le statut de la capture PayPal ne correspond pas à la commande.');
        }

        return $expectedAmount;
    }

    private function assertOrderMatches(array $order, string $orderId, Subscription $subscription, string $expectedAmount): void
    {
        $this->assertOrderIdentity($order, $orderId, $subscription);
        $purchaseUnit = data_get($order, 'purchase_units.0');
        if (data_get($purchaseUnit, 'amount.currency_code') !== 'USD'
            || $this->normalizeAmount(data_get($purchaseUnit, 'amount.value')) !== $expectedAmount) {
            throw new RuntimeException('Les détails de la commande PayPal ne correspondent pas à cet abonnement.');
        }
    }

    private function assertOrderIdentity(array $order, string $orderId, Subscription $subscription): void
    {
        if (data_get($order, 'id') !== $orderId
            || (string) data_get($order, 'purchase_units.0.custom_id') !== (string) $subscription->id) {
            throw new RuntimeException('Les détails de la commande PayPal ne correspondent pas à cet abonnement.');
        }
    }

    private function normalizeAmount(mixed $amount): ?string
    {
        return is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null;
    }

    private function request(string $method, string $path, array $payload = [], ?string $requestId = null): array
    {
        $this->ensureConfigured();

        try {
            $token = Http::acceptJson()
                ->asForm()
                ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
                ->timeout(15)
                ->post(self::API_URL.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $token->successful() || ! is_string($token->json('access_token'))) {
                Log::warning('PayPal OAuth authentication failed.', ['http_status' => $token->status()]);
                throw new RuntimeException('L’authentification PayPal a échoué. Vérifiez la configuration Sandbox.');
            }

            $request = Http::acceptJson()
                ->asJson()
                ->withToken($token->json('access_token'))
                ->timeout(20);

            if ($requestId !== null) {
                $request = $request->withHeaders(['PayPal-Request-Id' => $requestId]);
            }

            $response = $method === 'get'
                ? $request->get(self::API_URL.$path)
                : $request->post(self::API_URL.$path, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('PayPal API request failed to connect.', ['path' => $path]);
            throw new RuntimeException('Le service PayPal est momentanément inaccessible.', previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            Log::warning('PayPal API rejected a request.', [
                'path' => $path,
                'http_status' => $response->status(),
            ]);
            throw new RuntimeException('PayPal n’a pas pu traiter cette opération. Réessayez ou contactez l’administrateur.');
        }

        return $response->json();
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PayPal Sandbox n’est pas configuré. Ajoutez PAYPAL_CLIENT_ID et PAYPAL_CLIENT_SECRET au fichier .env.');
        }
    }
}

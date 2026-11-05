<?php

namespace App\Http\Middleware;

use App\Exceptions\Billing\InsufficientTokensException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque une route si l'utilisateur n'a pas d'abonnement actif ou pas assez de tokens.
 *
 * Utilisation :
 *   ->middleware(CheckTokens::class)            // minimum = config('billing.default_min_tokens')
 *   ->middleware(CheckTokens::class . ':2000')  // minimum = 2000 tokens
 */
class CheckTokens
{
    public function __construct(private TokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next, ?string $minimum = null): Response
    {
        $user = $request->user();
        abort_if(! $user, 401, 'Non authentifié.');

        $needed = (int) ($minimum ?? config('billing.default_min_tokens'));

        if (! $this->tokens->hasActiveSubscription($user)) {
            throw new NoActiveSubscriptionException();
        }

        $balance = $this->tokens->balance($user);
        if ($balance < $needed) {
            throw new InsufficientTokensException($needed, $balance);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route aux administrateurs (users.role = 'admin').
 * Si le Membre 1 a déjà un middleware équivalent, remplacez-le dans routes/billing.php.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Accès réservé aux administrateurs.');

        return $next($request);
    }
}

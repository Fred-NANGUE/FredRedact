<?php

namespace App\Exceptions\Billing;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientTokensException extends RuntimeException
{
    public function __construct(public int $required, public int $balance)
    {
        parent::__construct("Solde de tokens insuffisant ({$balance} disponibles, {$required} requis).");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'  => $this->getMessage(),
            'code'     => 'insufficient_tokens',
            'required' => $this->required,
            'balance'  => $this->balance,
        ], 402);
    }
}

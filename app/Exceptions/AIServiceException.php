<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AIServiceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 503, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

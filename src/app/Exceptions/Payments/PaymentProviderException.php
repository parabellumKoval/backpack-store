<?php

namespace Backpack\Store\app\Exceptions\Payments;

use RuntimeException;
use Throwable;

class PaymentProviderException extends RuntimeException
{
    protected array $context;

    public function __construct(string $message, int $code = 422, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public function context(): array
    {
        return $this->context;
    }
}

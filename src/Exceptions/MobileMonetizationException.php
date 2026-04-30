<?php

namespace Lemoba\MobileMonetization\Exceptions;

use RuntimeException;

class MobileMonetizationException extends RuntimeException
{
    public function __construct(string $message, int $code = 400, protected mixed $context = null)
    {
        parent::__construct($message, $code);
    }

    public function context(): mixed
    {
        return $this->context;
    }
}

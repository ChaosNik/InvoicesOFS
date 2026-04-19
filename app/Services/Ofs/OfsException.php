<?php

namespace App\Services\Ofs;

use RuntimeException;
use Throwable;

class OfsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?array $context = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

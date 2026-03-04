<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Exceptions;

class FitConnectException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $step,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $description = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}

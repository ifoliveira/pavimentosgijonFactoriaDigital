<?php

namespace App\Integration\BudgetFlow;

final class BudgetFlowException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        public readonly array $payload = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

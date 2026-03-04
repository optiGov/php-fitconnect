<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\DTOs\Incoming;

use OptiGov\FitConnect\Enums\FitConnectEventState;

readonly class SubmissionStatus
{
    /** @param array<mixed> $problems */
    public function __construct(
        public ?FitConnectEventState $state,
        public ?string $issuer = null,
        public ?string $issuedAt = null,
        public array $problems = [],
    ) {
    }
}

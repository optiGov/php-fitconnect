<?php

namespace OptiGov\FitConnect\DTOs\Incoming;

use OptiGov\FitConnect\Enums\FitConnectEventState;

readonly class SubmissionStatus
{
    public function __construct(
        public ?FitConnectEventState $state,
        public ?string $issuer = null,
        public ?string $issuedAt = null,
        public array $problems = [],
    ) {}
}

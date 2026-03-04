<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Config;

class Endpoints
{
    public function __construct(
        public readonly string $token,
        public readonly string $submission,
        public readonly string $destination,
        public readonly string $routing,
    ) {
    }
}

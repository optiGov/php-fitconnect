<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Config;

readonly class Endpoints
{
    public function __construct(
        public string $token,
        public string $submission,
        public string $destination,
        public string $routing,
    ) {
    }
}

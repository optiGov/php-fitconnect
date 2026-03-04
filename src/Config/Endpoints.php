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

    public static function test(): self
    {
        return new self(
            token: 'https://auth-testing.fit-connect.fitko.dev/token',
            submission: 'https://test.fit-connect.fitko.dev/submission-api',
            destination: 'https://test.fit-connect.fitko.dev/destination-api',
            routing: 'https://routing-api-testing.fit-connect.fitko.dev',
        );
    }

    public static function stage(): self
    {
        return new self(
            token: 'https://auth-refz.fit-connect.fitko.net/token',
            submission: 'https://stage.fit-connect.fitko.net/submission-api',
            destination: 'https://stage.fit-connect.fitko.net/destination-api',
            routing: 'https://routing-api-testing.fit-connect.fitko.dev',
        );
    }

    public static function prod(): self
    {
        return new self(
            token: 'https://auth-prod.fit-connect.fitko.net/token',
            submission: 'https://prod.fit-connect.fitko.net/submission-api',
            destination: 'https://prod.fit-connect.fitko.net/destination-api',
            routing: 'https://routing-api-prod.fit-connect.fitko.net',
        );
    }
}

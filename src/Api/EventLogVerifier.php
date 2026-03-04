<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Api;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionStatus;
use OptiGov\FitConnect\Enums\FitConnectEventState;

readonly class EventLogVerifier
{
    public function __construct(
        private ApiClient $apiClient,
    ) {
    }

    public function verify(string $jwt): SubmissionStatus
    {
        $payload = $this->decodeAndVerifySetJwt($jwt);

        $eventUrl = array_key_first($payload['events']);
        $state = FitConnectEventState::tryFrom($eventUrl);
        $eventData = $payload['events'][$eventUrl] ?? [];

        return new SubmissionStatus(
            state: $state,
            issuer: $payload['iss'] ?? null,
            issuedAt: isset($payload['iat']) ? date('c', $payload['iat']) : null,
            problems: $eventData['problems'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    private function decodeAndVerifySetJwt(string $jwt): array
    {
        $jws = new CompactSerializer()->unserialize($jwt);

        $header = $jws->getSignature(0)->getProtectedHeader();

        if (($header['typ'] ?? null) !== 'secevent+jwt') {
            throw new \InvalidArgumentException('Invalid SET: wrong typ header');
        }

        if (($header['alg'] ?? null) !== 'PS512') {
            throw new \InvalidArgumentException('Invalid SET: wrong alg header');
        }

        $kid = $header['kid'] ?? throw new \InvalidArgumentException('Invalid SET: missing kid');

        $payload = json_decode($jws->getPayload(), true, flags: JSON_THROW_ON_ERROR);

        $this->validateEventPayload($payload);

        $jwk = $this->fetchSigningKey($kid, $payload['iss']);

        $verifier = new JWSVerifier(new AlgorithmManager([new PS512]));
        if (! $verifier->verifyWithKey($jws, $jwk, 0)) {
            throw new \InvalidArgumentException('Invalid SET: signature verification failed');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function validateEventPayload(array $payload): void
    {
        foreach (['jti', 'iss', 'iat', 'sub', 'txn', 'events'] as $claim) {
            if (! isset($payload[$claim])) {
                throw new \InvalidArgumentException("Invalid SET: missing claim '{$claim}'");
            }
        }
        if (count($payload['events']) !== 1) {
            throw new \InvalidArgumentException('Invalid SET: events must contain exactly one entry');
        }
    }

    /**
     * Resolve the signing key based on the issuer type.
     *
     * - Issuer starts with "http" -> submission service -> fetch from /.well-known/jwks.json
     * - Issuer is a UUID -> destination -> fetch from /v2/destinations/{iss}/keys/{kid}
     */
    private function fetchSigningKey(string $kid, string $issuer): JWK
    {
        if (str_starts_with($issuer, 'http')) {
            return $this->fetchSubmissionServiceSigningKey($kid);
        }

        return $this->apiClient->fetchDestinationSigningKey($issuer, $kid);
    }

    private function fetchSubmissionServiceSigningKey(string $kid): JWK
    {
        $keys = $this->apiClient->fetchSubmissionServiceJwks();

        foreach ($keys as $keyData) {
            if (($keyData['kid'] ?? null) === $kid) {
                return new JWK($keyData);
            }
        }

        throw new \InvalidArgumentException("Invalid SET: unknown kid '{$kid}'");
    }
}

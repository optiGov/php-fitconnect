<?php

namespace OptiGov\FitConnect\FitConnect;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\DTOs\Incoming\DestinationInfo;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionStatus;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\Enums\FitConnectEventState;
use OptiGov\FitConnect\Exceptions\FitConnectException;
use OptiGov\FitConnect\FitConnect\Fluent\SubmissionBuilder as FluentSubmissionBuilder;

class Client
{
    private ?string $accessToken = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly Encryptor $encryptor,
    ) {}

    public function submission(): FluentSubmissionBuilder
    {
        return new FluentSubmissionBuilder($this);
    }

    public function sendSubmission(FitConnectSubmission $submission, string $destinationId): SubmissionResult
    {
        $jwk = $this->getJwkForDestination($destinationId);

        return $this->submit($submission, $jwk, $destinationId);
    }

    public function getDestination(string $destinationId): DestinationInfo
    {
        $this->authenticate();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get($this->endpoint('destination')."/v2/destinations/{$destinationId}");

        if (! $response->successful()) {
            $this->throwApiException('destination', $response);
        }

        return DestinationInfo::fromArray($response->json());
    }

    public function getLastSubmissionEventLog(string $submissionId): SubmissionStatus
    {
        $logs = $this->getSubmissionEventLogs($submissionId);

        if (empty($logs)) {
            throw new FitConnectException(
                message: 'FitConnect API error: no events found for submission',
                step: 'events',
                statusCode: 0,
            );
        }

        return end($logs);
    }

    /**
     * @return SubmissionStatus[]
     */
    public function getSubmissionEventLogs(string $submissionId): array
    {
        $this->authenticate();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get($this->endpoint('submission')."/v2/submissions/{$submissionId}/events", [
                'limit' => 100,
                'offset' => 0,
            ]);

        if (! $response->successful()) {
            $this->throwApiException('events', $response);
        }

        $jwtTokens = $response->json('eventLog', []);

        return array_map(fn (string $jwt) => $this->parseEventJwt($jwt), $jwtTokens);
    }

    public function getJwkForDestination(string $destinationId): JWK
    {
        $this->authenticate();

        return $this->fetchDestinationKey($destinationId);
    }

    public function submit(FitConnectSubmission $submission, JWK $jwk, string $destinationId): SubmissionResult
    {
        // 1. Prepare attachments: build metadata, encrypt
        $attachmentMeta = [];
        $encryptedAttachments = [];

        foreach ($submission->attachments as $attachment) {
            $attachmentMeta[] = [
                'filename' => $attachment->filename,
                'purpose' => 'attachment',
                'attachmentId' => $attachment->id,
                'description' => $attachment->filename,
                'mimeType' => $attachment->mimeType,
                'hash' => [
                    'type' => 'sha512',
                    'content' => hash('sha512', $attachment->content),
                ],
            ];

            $encryptedAttachments[$attachment->id] = $this->encryptor->encrypt($attachment->content, $jwk);
        }

        // 2. Build + encrypt metadata
        $metadata = [
            '$schema' => 'https://schema.fitko.de/fit-connect/metadata/2.1.0/metadata.schema.json',
            'contentStructure' => [
                'data' => [
                    'submissionSchema' => [
                        'schemaUri' => $submission->schemaUri,
                        'mimeType' => 'application/json',
                    ],
                    'hash' => [
                        'content' => hash('sha512', $submission->data),
                        'type' => 'sha512',
                    ],
                ],
                'attachments' => $attachmentMeta,
            ],
        ];

        $encryptedMetadata = $this->encryptor->encrypt(
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $jwk,
        );

        // 3. Encrypt data
        $encryptedData = $this->encryptor->encrypt($submission->data, $jwk);

        // 4. Announce
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->post($this->endpoint('submission').'/v2/submissions', [
                'destinationId' => $destinationId,
                'announcedAttachments' => array_map(fn ($attachment) => $attachment->id, $submission->attachments),
                'publicService' => [
                    'identifier' => $submission->serviceIdentifier,
                    'name' => $submission->serviceName,
                ],
            ]);

        if (! $response->successful()) {
            $this->throwApiException('announce', $response);
        }

        $submissionData = $response->json();
        $submissionId = $submissionData['submissionId'];

        // 5. Upload attachments
        foreach ($encryptedAttachments as $attachmentId => $encryptedContent) {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Content-Type' => 'application/jose'])
                ->withBody($encryptedContent, 'application/jose')
                ->put($this->endpoint('submission')."/v2/submissions/{$submissionId}/attachments/{$attachmentId}");

            if (! $response->successful()) {
                $this->throwApiException('upload', $response);
            }
        }

        // 6. Submit
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->put($this->endpoint('submission')."/v2/submissions/{$submissionId}", [
                'encryptedMetadata' => $encryptedMetadata,
                'encryptedData' => $encryptedData,
            ]);

        if (! $response->successful()) {
            $this->throwApiException('submit', $response);
        }

        return new SubmissionResult(
            submissionId: $submissionId,
            caseId: $submissionData['caseId'] ?? '',
            destinationId: $destinationId,
            attachmentIds: array_map(fn ($attachment) => $attachment->id, $submission->attachments),
        );
    }

    private function parseEventJwt(string $jwt): SubmissionStatus
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

    private function authenticate(): void
    {
        $cached = Cache::store('array')->get('fitconnect_access_token');
        if ($cached) {
            $this->accessToken = $cached;

            return;
        }

        $response = Http::asForm()->post($this->endpoint('token'), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        if (! $response->successful()) {
            $this->throwApiException('auth', $response);
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        Cache::store('array')->put('fitconnect_access_token', $this->accessToken, $data['expires_in']);
    }

    private function fetchDestinationKey(string $destinationId): JWK
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get($this->endpoint('destination')."/v2/destinations/{$destinationId}/keys");

        if (! $response->successful()) {
            $this->throwApiException('key_fetch', $response);
        }

        $keyData = $response->json('keys.0');

        return JWKFactory::createFromValues(['use' => 'enc'] + $keyData);
    }

    private function endpoint(string $type): string
    {
        $url = $this->config['endpoints'][$type] ?? null;

        if ($url === null) {
            throw new \RuntimeException("FitConnect: no endpoint configured for '{$type}'");
        }

        return $url;
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

        $payload = json_decode($jws->getPayload(), true) ?: [];

        $this->validateEventPayload($payload);

        $jwk = $this->fetchSigningKey($kid, $payload['iss']);

        $verifier = new JWSVerifier(new AlgorithmManager([new PS512]));
        if (! $verifier->verifyWithKey($jws, $jwk, 0)) {
            throw new \InvalidArgumentException('Invalid SET: signature verification failed');
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
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
     * - Issuer starts with "http" → submission service → fetch from /.well-known/jwks.json
     * - Issuer is a UUID → destination → fetch from /v2/destinations/{iss}/keys/{kid}
     */
    private function fetchSigningKey(string $kid, string $issuer): JWK
    {
        if (str_starts_with($issuer, 'http')) {
            return $this->fetchSubmissionServiceSigningKey($kid);
        }

        return $this->fetchDestinationSigningKey($issuer, $kid);
    }

    private function fetchSubmissionServiceSigningKey(string $kid): JWK
    {
        $cached = Cache::store('array')->get('fitconnect_jwks');

        if ($cached === null) {
            $response = Http::get($this->endpoint('submission').'/.well-known/jwks.json');
            if (! $response->successful()) {
                $this->throwApiException('jwks', $response);
            }
            $cached = $response->json('keys', []);
            Cache::store('array')->put('fitconnect_jwks', $cached);
        }

        foreach ($cached as $keyData) {
            if (($keyData['kid'] ?? null) === $kid) {
                return JWKFactory::createFromValues($keyData);
            }
        }

        throw new \InvalidArgumentException("Invalid SET: unknown kid '{$kid}'");
    }

    private function fetchDestinationSigningKey(string $destinationId, string $kid): JWK
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get($this->endpoint('submission')."/v2/destinations/{$destinationId}/keys/{$kid}");

        if (! $response->successful()) {
            $this->throwApiException('signing_key_fetch', $response);
        }

        return JWKFactory::createFromValues($response->json());
    }

    private function throwApiException(string $step, Response $response): never
    {
        $body = $response->json() ?? [];

        throw new FitConnectException(
            message: "FitConnect API error during {$step}: HTTP {$response->status()}",
            step: $step,
            statusCode: $response->status(),
            errorCode: $body['errorCode'] ?? null,
            description: $body['description'] ?? $body['message'] ?? null,
        );
    }
}

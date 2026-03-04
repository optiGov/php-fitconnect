<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\FitConnect;

use GuzzleHttp\Client as HttpClient;
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
use Psr\Http\Message\ResponseInterface;

class Client
{
    private ?string $accessToken = null;

    private HttpClient $httpClient;

    /** @var array<string, mixed>|null */
    private ?array $jwksCache = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly Encryptor $encryptor,
        ?HttpClient $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new HttpClient(['http_errors' => false]);
    }

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

        $response = $this->httpClient->request('GET', $this->endpoint('destination')."/v2/destinations/{$destinationId}", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('destination', $response);
        }

        return DestinationInfo::fromArray($this->jsonDecode($response));
    }

    public function getLastSubmissionEventLog(string $submissionId): SubmissionStatus
    {
        $logs = $this->getSubmissionEventLogs($submissionId);

        if (empty($logs)) {
            throw new FitConnectException(message: 'FitConnect API error: no events found for submission', step: 'events', statusCode: 0);
        }

        return end($logs);
    }

    /**
     * @return SubmissionStatus[]
     */
    public function getSubmissionEventLogs(string $submissionId): array
    {
        $this->authenticate();

        $response = $this->httpClient->request('GET', $this->endpoint('submission')."/v2/submissions/{$submissionId}/events", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
            'query' => [
                'limit' => 100,
                'offset' => 0,
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('events', $response);
        }

        $data = $this->jsonDecode($response);
        $jwtTokens = $data['eventLog'] ?? [];

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
        $response = $this->httpClient->request('POST', $this->endpoint('submission').'/v2/submissions', [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
            'json' => [
                'destinationId' => $destinationId,
                'announcedAttachments' => array_map(fn ($attachment) => $attachment->id, $submission->attachments),
                'publicService' => [
                    'identifier' => $submission->serviceIdentifier,
                    'name' => $submission->serviceName,
                ],
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('announce', $response);
        }

        $submissionData = $this->jsonDecode($response);
        $submissionId = $submissionData['submissionId'];

        // 5. Upload attachments
        foreach ($encryptedAttachments as $attachmentId => $encryptedContent) {
            $response = $this->httpClient->request('PUT', $this->endpoint('submission')."/v2/submissions/{$submissionId}/attachments/{$attachmentId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/jose',
                ],
                'body' => $encryptedContent,
            ]);

            if (! $this->isSuccessful($response)) {
                $this->throwApiException('upload', $response);
            }
        }

        // 6. Submit
        $response = $this->httpClient->request('PUT', $this->endpoint('submission')."/v2/submissions/{$submissionId}", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
            'json' => [
                'encryptedMetadata' => $encryptedMetadata,
                'encryptedData' => $encryptedData,
            ],
        ]);

        if (! $this->isSuccessful($response)) {
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
        if ($this->accessToken) {
            return;
        }

        $response = $this->httpClient->request('POST', $this->endpoint('token'), [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('auth', $response);
        }

        $data = $this->jsonDecode($response);
        $this->accessToken = $data['access_token'];
    }

    private function fetchDestinationKey(string $destinationId): JWK
    {
        $response = $this->httpClient->request('GET', $this->endpoint('destination')."/v2/destinations/{$destinationId}/keys", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('key_fetch', $response);
        }

        $data = $this->jsonDecode($response);
        $keyData = $data['keys'][0];

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
     * - Issuer starts with "http" -> submission service -> fetch from /.well-known/jwks.json
     * - Issuer is a UUID -> destination -> fetch from /v2/destinations/{iss}/keys/{kid}
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
        if ($this->jwksCache === null) {
            $response = $this->httpClient->request('GET', $this->endpoint('submission').'/.well-known/jwks.json');
            if (! $this->isSuccessful($response)) {
                $this->throwApiException('jwks', $response);
            }
            $data = $this->jsonDecode($response);
            $this->jwksCache = $data['keys'] ?? [];
        }

        foreach ($this->jwksCache as $keyData) {
            if (($keyData['kid'] ?? null) === $kid) {
                return JWKFactory::createFromValues($keyData);
            }
        }

        throw new \InvalidArgumentException("Invalid SET: unknown kid '{$kid}'");
    }

    private function fetchDestinationSigningKey(string $destinationId, string $kid): JWK
    {
        $response = $this->httpClient->request('GET', $this->endpoint('submission')."/v2/destinations/{$destinationId}/keys/{$kid}", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('signing_key_fetch', $response);
        }

        return JWKFactory::createFromValues($this->jsonDecode($response));
    }

    private function isSuccessful(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    /** @return array<string, mixed> */
    private function jsonDecode(ResponseInterface $response): array
    {
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    private function throwApiException(string $step, ResponseInterface $response): never
    {
        $body = json_decode((string) $response->getBody(), true) ?? [];

        throw new FitConnectException(message: "FitConnect API error during {$step}: HTTP {$response->getStatusCode()}", step: $step, statusCode: $response->getStatusCode(), errorCode: $body['errorCode'] ?? null, description: $body['description'] ?? $body['message'] ?? null);
    }
}

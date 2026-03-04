<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Api;

use GuzzleHttp\Client as HttpClient;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use OptiGov\FitConnect\Config\FitConnectConfig;
use OptiGov\FitConnect\DTOs\Incoming\DestinationInfo;
use OptiGov\FitConnect\Exceptions\FitConnectException;
use Psr\Http\Message\ResponseInterface;

class ApiClient
{
    private ?string $accessToken = null;

    private HttpClient $httpClient;

    /** @var array<string, mixed>|null */
    private ?array $jwksCache = null;

    public function __construct(
        private readonly FitConnectConfig $config,
        ?HttpClient $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new HttpClient(['http_errors' => false]);
    }

    public function authenticate(): void
    {
        if ($this->accessToken) {
            return;
        }

        $response = $this->httpClient->request('POST', $this->endpoint('token'), [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('auth', $response);
        }

        $data = $this->jsonDecode($response);
        $this->accessToken = $data['access_token'];
    }

    public function getDestination(string $destinationId): DestinationInfo
    {
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

    public function fetchDestinationKeys(string $destinationId): JWK
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

    public function fetchDestinationSigningKey(string $destinationId, string $kid): JWK
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

    /** @return array<array<string, mixed>> */
    public function fetchSubmissionServiceJwks(): array
    {
        if ($this->jwksCache === null) {
            $response = $this->httpClient->request('GET', $this->endpoint('submission').'/.well-known/jwks.json');
            if (! $this->isSuccessful($response)) {
                $this->throwApiException('jwks', $response);
            }
            $data = $this->jsonDecode($response);
            $this->jwksCache = $data['keys'] ?? [];
        }

        return $this->jwksCache;
    }

    /**
     * @param string[] $attachmentIds
     *
     * @return array<string, mixed>
     */
    public function announceSubmission(string $destinationId, array $attachmentIds, string $serviceIdentifier, string $serviceName): array
    {
        $response = $this->httpClient->request('POST', $this->endpoint('submission').'/v2/submissions', [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
            'json' => [
                'destinationId' => $destinationId,
                'announcedAttachments' => $attachmentIds,
                'publicService' => [
                    'identifier' => $serviceIdentifier,
                    'name' => $serviceName,
                ],
            ],
        ]);

        if (! $this->isSuccessful($response)) {
            $this->throwApiException('announce', $response);
        }

        return $this->jsonDecode($response);
    }

    public function uploadAttachment(string $submissionId, string $attachmentId, string $encryptedContent): void
    {
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

    public function submitSubmission(string $submissionId, string $encryptedMetadata, string $encryptedData): void
    {
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
    }

    /**
     * @return string[]
     */
    public function fetchSubmissionEvents(string $submissionId): array
    {
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

        return $data['eventLog'] ?? [];
    }

    private function endpoint(string $type): string
    {
        return match ($type) {
            'token' => $this->config->endpoints->token,
            'submission' => $this->config->endpoints->submission,
            'destination' => $this->config->endpoints->destination,
            default => throw new \RuntimeException("FitConnect: unknown endpoint type '{$type}'"),
        };
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

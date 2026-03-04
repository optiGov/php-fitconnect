<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Client;

use GuzzleHttp\Client as HttpClient;
use Jose\Component\Core\JWK;
use OptiGov\FitConnect\Api\ApiClient;
use OptiGov\FitConnect\Api\EventLogVerifier;
use OptiGov\FitConnect\Config\FitConnectConfig;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\DTOs\Incoming\DestinationInfo;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionStatus;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\Exceptions\FitConnectException;
use OptiGov\FitConnect\Fluent\Submission as FluentSubmission;

class SenderClient
{
    private readonly ApiClient $apiClient;

    private readonly EventLogVerifier $eventLogVerifier;

    public function __construct(
        FitConnectConfig $config,
        private readonly Encryptor $encryptor,
        ?HttpClient $httpClient = null,
    ) {
        $this->apiClient = new ApiClient($config, $httpClient);
        $this->eventLogVerifier = new EventLogVerifier($this->apiClient);
    }

    public function submission(): FluentSubmission
    {
        return new FluentSubmission($this);
    }

    public function sendSubmission(FitConnectSubmission $submission, string $destinationId): SubmissionResult
    {
        $jwk = $this->getJwkForDestination($destinationId);

        return $this->submit($submission, $jwk, $destinationId);
    }

    public function getDestination(string $destinationId): DestinationInfo
    {
        $this->apiClient->authenticate();

        return $this->apiClient->getDestination($destinationId);
    }

    public function getJwkForDestination(string $destinationId): JWK
    {
        $this->apiClient->authenticate();

        return $this->apiClient->fetchDestinationKeys($destinationId);
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
        $this->apiClient->authenticate();

        $jwtTokens = $this->apiClient->fetchSubmissionEvents($submissionId);

        return array_map(fn (string $jwt) => $this->eventLogVerifier->verify($jwt), $jwtTokens);
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
        $submissionData = $this->apiClient->announceSubmission(
            $destinationId,
            array_map(fn ($attachment) => $attachment->id, $submission->attachments),
            $submission->serviceIdentifier,
            $submission->serviceName,
        );

        $submissionId = $submissionData['submissionId'];

        // 5. Upload attachments
        foreach ($encryptedAttachments as $attachmentId => $encryptedContent) {
            $this->apiClient->uploadAttachment($submissionId, $attachmentId, $encryptedContent);
        }

        // 6. Submit
        $this->apiClient->submitSubmission($submissionId, $encryptedMetadata, $encryptedData);

        return new SubmissionResult(
            submissionId: $submissionId,
            caseId: $submissionData['caseId'] ?? '',
            destinationId: $destinationId,
            attachmentIds: array_map(fn ($attachment) => $attachment->id, $submission->attachments),
        );
    }
}

<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Client\Zbp;

use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;

class EnvelopeBuilder
{
    private const MESSAGE_SERVICE_IDENTIFIER = 'urn:schema-fitko-de:fit-connect:id.bund.de:message_v6';

    private const STATE_SERVICE_IDENTIFIER = 'urn:schema-fitko-de:fit-connect:id.bund.de:status_v6';

    private const SCHEMA_URI = 'https://schema.fitko.de/fit-connect/id.bund.de/message_v6/1.0.0/zbp-message.schema.json';

    public function __construct(
        private readonly Signer $signer,
    ) {
    }

    public function fromMessage(ZbpMessage $message): FitConnectSubmission
    {
        $payloadJson = json_encode($this->serializeMessage($message), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new FitConnectSubmission(
            data: $this->buildSignedEnvelope($payloadJson),
            schemaUri: self::SCHEMA_URI,
            serviceIdentifier: self::MESSAGE_SERVICE_IDENTIFIER,
            serviceName: $message->service,
            attachments: $message->attachments,
        );
    }

    public function fromState(ZbpState $state): FitConnectSubmission
    {
        $payloadJson = json_encode($this->serializeState($state), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new FitConnectSubmission(
            data: $this->buildSignedEnvelope($payloadJson),
            schemaUri: self::SCHEMA_URI,
            serviceIdentifier: self::STATE_SERVICE_IDENTIFIER,
            serviceName: 'ZBP State Forwarding',
        );
    }

    private function buildSignedEnvelope(string $contentJson): string
    {
        return json_encode([
            'content' => $contentJson,
            'sha512sum' => $this->signer->sign($contentJson),
            'authorToken' => $this->signer->buildAuthorJwt(),
            'authorCertificate' => $this->signer->getCertificatePem(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function serializeMessage(ZbpMessage $message): array
    {
        $payload = [
            'stork_qaa_level' => $message->storkQaaLevel->value,
            'mailboxUuid' => $message->mailboxUuid,
            'sender' => $message->sender,
            'title' => $message->title,
            'content' => $message->content,
            'service' => $message->service,
        ];

        if ($message->applicationId !== null) {
            $payload['applicationId'] = $message->applicationId;
        }

        if ($message->retrievalConfirmationAddress !== null) {
            $payload['retrievalConfirmationAddress'] = $message->retrievalConfirmationAddress;
        }

        if ($message->replyAddress !== null) {
            $payload['replyAddress'] = $message->replyAddress;
        }

        if ($message->reference !== null) {
            $payload['reference'] = $message->reference;
        }

        if ($message->senderUrl !== null) {
            $payload['senderUrl'] = $message->senderUrl;
        }

        if (! empty($message->attachments)) {
            $payload['attachments'] = array_map(
                fn (Attachment $attachment) => [
                    'filename' => $attachment->filename,
                    'sha512sum' => hash('sha512', $attachment->content),
                    'contentLength' => strlen($attachment->content),
                ],
                $message->attachments,
            );
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function serializeState(ZbpState $state): array
    {
        $payload = [
            'applicationId' => $state->applicationId,
            'status' => $state->status->value,
            'publicServiceName' => ['de' => $state->publicServiceName],
            'senderName' => $state->senderName,
            'createdDate' => $state->createdDate ?? gmdate('Y-m-d\TH:i:s.000\Z'),
        ];

        if ($state->statusDetails !== null) {
            $payload['statusDetails'] = ['de' => $state->statusDetails];
        }

        if ($state->additionalInformation !== null) {
            $payload['additionalInformation'] = ['de' => $state->additionalInformation];
        }

        if ($state->reference !== null) {
            $payload['reference'] = $state->reference;
        }

        return $payload;
    }
}

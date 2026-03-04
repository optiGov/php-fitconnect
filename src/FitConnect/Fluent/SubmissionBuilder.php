<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\FitConnect\Fluent;

use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\FitConnect\Client;

class SubmissionBuilder
{
    private string $dataJson = '';

    private string $schemaUri = '';

    private string $serviceIdentifier = '';

    private string $serviceName = '';

    /** @var array<Attachment> */
    private array $attachments = [];

    private string $destinationId = '';

    public function __construct(private readonly Client $client)
    {
    }

    public function data(string $dataJson): self
    {
        $this->dataJson = $dataJson;

        return $this;
    }

    public function schema(string $schemaUri): self
    {
        $this->schemaUri = $schemaUri;

        return $this;
    }

    public function serviceType(string $identifier, string $name): self
    {
        $this->serviceIdentifier = $identifier;
        $this->serviceName = $name;

        return $this;
    }

    public function destinationId(string $destinationId): self
    {
        $this->destinationId = $destinationId;

        return $this;
    }

    public function attach(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function send(): SubmissionResult
    {
        $submission = new FitConnectSubmission(
            data: $this->dataJson,
            schemaUri: $this->schemaUri,
            serviceIdentifier: $this->serviceIdentifier,
            serviceName: $this->serviceName,
            attachments: $this->attachments,
        );

        return $this->client->sendSubmission($submission, $this->destinationId);
    }
}

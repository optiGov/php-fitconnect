<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Fluent\Zbp;

use OptiGov\FitConnect\Client\ZbpClient;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;

class State
{
    private string $applicationId;

    private ZbpSubmissionState $status;

    private string $publicServiceName;

    private string $senderName;

    private ?string $statusDetails = null;

    private ?string $additionalInformation = null;

    private ?string $reference = null;

    private ?string $createdDate = null;

    public function __construct(
        private readonly ZbpClient $client,
    ) {
    }

    public function applicationId(string $applicationId): self
    {
        $this->applicationId = $applicationId;

        return $this;
    }

    public function status(ZbpSubmissionState $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function publicServiceName(string $name): self
    {
        $this->publicServiceName = $name;

        return $this;
    }

    public function senderName(string $name): self
    {
        $this->senderName = $name;

        return $this;
    }

    public function statusDetails(string $details): self
    {
        $this->statusDetails = $details;

        return $this;
    }

    public function additionalInformation(string $info): self
    {
        $this->additionalInformation = $info;

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function createdDate(string $date): self
    {
        $this->createdDate = $date;

        return $this;
    }

    public function send(): SubmissionResult
    {
        $state = new ZbpState(
            applicationId: $this->applicationId,
            status: $this->status,
            publicServiceName: $this->publicServiceName,
            senderName: $this->senderName,
            statusDetails: $this->statusDetails,
            additionalInformation: $this->additionalInformation,
            reference: $this->reference,
            createdDate: $this->createdDate,
        );

        return $this->client->sendState($state);
    }
}

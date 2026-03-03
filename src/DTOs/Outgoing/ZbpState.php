<?php

namespace OptiGov\FitConnect\DTOs\Outgoing;

use OptiGov\FitConnect\Enums\ZbpSubmissionState;
use OptiGov\FitConnect\Traits\ValidateHelper;

readonly class ZbpState
{
    use ValidateHelper;

    public function __construct(
        public string $applicationId,
        public ZbpSubmissionState $status,
        public string $publicServiceName,
        public string $senderName,
        public ?string $statusDetails = null,
        public ?string $additionalInformation = null,
        public ?string $reference = null,
        public ?string $createdDate = null,
    ) {
        self::assertUuid($this->applicationId, 'applicationId');
        self::assertLength($this->publicServiceName, 'publicServiceName', 1, 100);
        self::assertLength($this->senderName, 'senderName', 1, 100);

        if ($this->statusDetails !== null) {
            self::assertLength($this->statusDetails, 'statusDetails', 1, 50);
        }
        if ($this->additionalInformation !== null) {
            self::assertLength($this->additionalInformation, 'additionalInformation', 1, 100);
        }
        if ($this->reference !== null) {
            self::assertLength($this->reference, 'reference', 0, 50);
        }
        if ($this->createdDate !== null) {
            self::assertDateTime($this->createdDate, 'createdDate');
        }
    }
}

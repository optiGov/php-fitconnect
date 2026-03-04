<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\DTOs\Outgoing;

readonly class FitConnectSubmission
{
    /**
     * @param Attachment[] $attachments
     */
    public function __construct(
        public string $data,
        public string $schemaUri,
        public string $serviceIdentifier,
        public string $serviceName,
        public array $attachments = [],
    ) {
    }
}

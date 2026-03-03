<?php

namespace OptiGov\FitConnect\DTOs\Incoming;

readonly class DestinationInfo
{
    public function __construct(
        public string $destinationId,
        public string $name,
        public string $status,
        public array $metadataVersions,
        public array $publicServices,
        public ?string $encryptionKid,
        public ?array $contactInformation,
        public ?array $callback,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            destinationId: $data['destinationId'] ?? '',
            name: $data['name'] ?? '',
            status: $data['status'] ?? '',
            metadataVersions: $data['metadataVersions'] ?? [],
            publicServices: $data['publicServices'] ?? [],
            encryptionKid: $data['encryptionKid'] ?? null,
            contactInformation: $data['contactInformation'] ?? null,
            callback: $data['callback'] ?? null,
        );
    }
}

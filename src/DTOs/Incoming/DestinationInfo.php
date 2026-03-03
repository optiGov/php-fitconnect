<?php

namespace OptiGov\FitConnect\DTOs\Incoming;

readonly class DestinationInfo
{
    /**
     * @param  array<string>  $metadataVersions
     * @param  array<mixed>  $publicServices
     * @param  array<string, mixed>|null  $contactInformation
     * @param  array<string, mixed>|null  $callback
     */
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

    /** @param array<string, mixed> $data */
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

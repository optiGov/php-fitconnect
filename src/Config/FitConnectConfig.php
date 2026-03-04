<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Config;

readonly class FitConnectConfig
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public Endpoints $endpoints,
        public ?string $zbpDestinationId = null,
        public ?string $zbpSigningKey = null,
        public ?string $zbpCertificate = null,
    ) {
        $zbpRequired = [
            $this->zbpDestinationId !== null,
            $this->zbpSigningKey !== null,
            $this->zbpCertificate !== null,
        ];

        if (count(array_unique($zbpRequired)) !== 1) {
            throw new \InvalidArgumentException('zbpDestinationId, zbpSigningKey and zbpCertificate must all be provided or all be null');
        }
    }
}

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
        if (($this->zbpSigningKey === null) !== ($this->zbpCertificate === null)) {
            throw new \InvalidArgumentException('zbpSigningKey and zbpCertificate must both be provided or both be null');
        }
    }
}

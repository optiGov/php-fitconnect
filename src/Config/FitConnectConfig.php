<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Config;

class FitConnectConfig
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly Endpoints $endpoints,
        public readonly ?string $zbpDestinationId = null,
        public readonly ?string $zbpSigningKey = null,
        public readonly ?string $zbpCertificate = null,
    ) {
        if (($this->zbpSigningKey === null) !== ($this->zbpCertificate === null)) {
            throw new \InvalidArgumentException('zbpSigningKey and zbpCertificate must both be provided or both be null');
        }
    }
}

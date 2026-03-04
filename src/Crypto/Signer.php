<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Crypto;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use OptiGov\FitConnect\Exceptions\FitConnectException;

readonly class Signer
{
    private string $commonName;

    private JWK $signingJwk;

    private JWSBuilder $jwsBuilder;

    public function __construct(
        private string $privateKeyPem,
        private string $certificatePem,
    ) {
        $cert = openssl_x509_parse($this->certificatePem);
        $this->commonName = $cert['subject']['CN'];

        $this->signingJwk = JWKFactory::createFromKey(
            $this->privateKeyPem,
            null,
            ['alg' => 'RS512', 'use' => 'sig'],
        );

        $this->jwsBuilder = new JWSBuilder(new AlgorithmManager([new RS512]));
    }

    public function sign(string $content): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem);

        $signature = null;
        if (! openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
            throw new FitConnectException('Signing failed: '.openssl_error_string(), step: 'sign', statusCode: 0);
        }

        return base64_encode($signature);
    }

    public function buildAuthorJwt(): string
    {
        $now = time();
        $payload = json_encode([
            'iat' => $now,
            'exp' => $now + (30 * 60),
            'signer' => $this->commonName,
            'roles' => ['THIRD_PARTY'],
        ]);

        $jws = $this->jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($this->signingJwk, ['typ' => 'JWT', 'alg' => 'RS512'])
            ->build()
        ;

        return new CompactSerializer()->serialize($jws, 0);
    }

    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }
}

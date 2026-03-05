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

        if (! $cert) {
            throw new \RuntimeException('Cannot parse certificate: '.openssl_error_string());
        }

        $commonName = $cert['subject']['CN'] ?? null;
        if (! is_string($commonName) || $commonName === '') {
            throw new \RuntimeException('Certificate subject must contain a CN (Common Name)');
        }
        $this->commonName = $commonName;

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

        if (! $privateKey) {
            throw new \InvalidArgumentException('Invalid private key: '.openssl_error_string());
        }

        $signature = '';
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
        ], JSON_THROW_ON_ERROR);

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

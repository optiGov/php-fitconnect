<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

trait TestKeys
{
    protected string $privateKeyPem;

    protected string $publicKeyPem;

    protected string $certificatePem;

    protected JWK $encryptionJwk;

    protected JWK $encryptionPrivateJwk;

    protected string $encryptionKid;

    protected JWK $signingPrivateJwk;

    protected JWK $signingPublicJwk;

    protected string $signingKid;

    protected function setUpTestKeys(): void
    {
        // Generate RSA 2048 keypair for signing
        $signingKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKeyPem = '';
        openssl_pkey_export($signingKey, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($signingKey);
        $this->publicKeyPem = $details['key'];

        // Generate self-signed X.509 certificate (CN=TestSigner)
        $csr = openssl_csr_new(
            ['CN' => 'TestSigner', 'O' => 'Test', 'C' => 'DE'],
            $signingKey,
        );
        $cert = openssl_csr_sign($csr, null, $signingKey, 365);
        $certificatePem = '';
        openssl_x509_export($cert, $certificatePem);
        $this->certificatePem = $certificatePem;

        // Generate separate RSA keypair for JWE encryption (simulates destination key)
        $this->encryptionKid = 'test-kid-'.bin2hex(random_bytes(4));

        $this->encryptionPrivateJwk = JWKFactory::createRSAKey(2048, [
            'alg' => 'RSA-OAEP-256',
            'use' => 'enc',
            'kid' => $this->encryptionKid,
        ]);

        // Public JWK (without private components)
        $this->encryptionJwk = $this->encryptionPrivateJwk->toPublic();

        // Generate RSA keypair for SET signing (PS512, simulates FIT-Connect delivery service)
        $this->signingKid = 'set-kid-'.bin2hex(random_bytes(4));

        $this->signingPrivateJwk = JWKFactory::createRSAKey(2048, [
            'alg' => 'PS512',
            'use' => 'sig',
            'kid' => $this->signingKid,
        ]);

        $this->signingPublicJwk = $this->signingPrivateJwk->toPublic();
    }

    protected function buildSignedSetJwt(array $payload): string
    {
        $algorithmManager = new AlgorithmManager([new PS512]);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($this->signingPrivateJwk, [
                'alg' => 'PS512',
                'typ' => 'secevent+jwt',
                'kid' => $this->signingKid,
            ])
            ->build()
        ;

        return new CompactSerializer()->serialize($jws, 0);
    }
}

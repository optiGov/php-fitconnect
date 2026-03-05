<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit\Crypto;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\Tests\TestKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Signer::class)]
class SignerTest extends TestCase
{
    use TestKeys;

    private Signer $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestKeys();
        $this->signer = new Signer($this->privateKeyPem, $this->certificatePem);
    }

    public function testSignProducesVerifiableSignature(): void
    {
        $content = 'Hello, FIT-Connect!';
        $signature = $this->signer->sign($content);

        $decodedSignature = base64_decode($signature);
        $publicKey = openssl_pkey_get_public($this->publicKeyPem);

        $result = openssl_verify($content, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA512);
        $this->assertSame(1, $result);
    }

    public function testSignDifferentContentProducesDifferentSignature(): void
    {
        $sig1 = $this->signer->sign('content A');
        $sig2 = $this->signer->sign('content B');

        $this->assertNotSame($sig1, $sig2);
    }

    public function testBuildAuthorJwtHasCorrectClaims(): void
    {
        $jwt = $this->signer->buildAuthorJwt();

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame('TestSigner', $payload['signer']);
        $this->assertSame(['THIRD_PARTY'], $payload['roles']);
        $this->assertSame($payload['iat'] + 1800, $payload['exp']); // 30 min default
    }

    public function testThrowsWhenCertificateHasNoCN(): void
    {
        // Generate a certificate without CN (only O and C)
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['O' => 'Test', 'C' => 'DE'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 365);
        $certPem = '';
        openssl_x509_export($cert, $certPem);

        $keyPem = '';
        openssl_pkey_export($key, $keyPem);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CN');
        new Signer($keyPem, $certPem);
    }

    public function testThrowsWhenCertificateIsInvalid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse certificate');
        new Signer($this->privateKeyPem, 'not-a-certificate');
    }

    public function testBuildAuthorJwtIsValidRs512(): void
    {
        $jwt = $this->signer->buildAuthorJwt();

        $jwk = JWKFactory::createFromKey($this->publicKeyPem, null, [
            'alg' => 'RS512',
            'use' => 'sig',
        ]);

        $serializer = new CompactSerializer;
        $jws = $serializer->unserialize($jwt);

        $verifier = new JWSVerifier(new AlgorithmManager([new RS512]));
        $this->assertTrue($verifier->verifyWithKey($jws, $jwk, 0));
    }
}

<?php

namespace OptiGov\FitConnect\Tests\Unit\Crypto;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\Tests\TestKeys;
use PHPUnit\Framework\TestCase;

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

    public function test_sign_produces_verifiable_signature(): void
    {
        $content = 'Hello, FIT-Connect!';
        $signature = $this->signer->sign($content);

        $decodedSignature = base64_decode($signature);
        $publicKey = openssl_pkey_get_public($this->publicKeyPem);

        $result = openssl_verify($content, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA512);
        $this->assertSame(1, $result);
    }

    public function test_sign_different_content_produces_different_signature(): void
    {
        $sig1 = $this->signer->sign('content A');
        $sig2 = $this->signer->sign('content B');

        $this->assertNotSame($sig1, $sig2);
    }

    public function test_build_author_jwt_has_correct_claims(): void
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

    public function test_build_author_jwt_is_valid_rs512(): void
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

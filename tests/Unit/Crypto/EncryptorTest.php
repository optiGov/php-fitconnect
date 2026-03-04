<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit\Crypto;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\Tests\TestKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encryptor::class)]
class EncryptorTest extends TestCase
{
    use TestKeys;

    private Encryptor $encryptor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestKeys();
        $this->encryptor = new Encryptor;
    }

    public function testEncryptProducesJweCompactFormat(): void
    {
        $result = $this->encryptor->encrypt('test payload', $this->encryptionJwk);

        $parts = explode('.', $result);
        $this->assertCount(5, $parts, 'JWE compact serialization should have 5 parts');
    }

    public function testEncryptedPayloadIsDecryptable(): void
    {
        $plaintext = '{"message": "Hello, World!"}';

        $encrypted = $this->encryptor->encrypt($plaintext, $this->encryptionJwk);

        // Decrypt with private key
        $serializer = new CompactSerializer;
        $jwe = $serializer->unserialize($encrypted);

        $decrypter = new JWEDecrypter(
            new AlgorithmManager([new RSAOAEP256]),
            new AlgorithmManager([new A256GCM]),
        );

        $result = $decrypter->decryptUsingKey($jwe, $this->encryptionPrivateJwk, 0);
        $this->assertTrue($result);
        $this->assertSame($plaintext, $jwe->getPayload());
    }

    public function testEncryptSetsCorrectHeaders(): void
    {
        $encrypted = $this->encryptor->encrypt('test', $this->encryptionJwk);

        $parts = explode('.', $encrypted);
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        $this->assertSame('RSA-OAEP-256', $header['alg']);
        $this->assertSame('A256GCM', $header['enc']);
        $this->assertSame($this->encryptionKid, $header['kid']);
        $this->assertSame('application/json', $header['cty']);
    }
}

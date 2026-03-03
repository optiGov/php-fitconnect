<?php

namespace OptiGov\FitConnect\Crypto;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;

readonly class Encryptor
{
    private JWEBuilder $jweBuilder;

    private CompactSerializer $serializer;

    public function __construct()
    {
        $this->jweBuilder = new JWEBuilder(
            new AlgorithmManager([new RSAOAEP256]),
            new AlgorithmManager([new A256GCM]),
        );

        $this->serializer = new CompactSerializer;
    }

    public function encrypt(string $payload, JWK $key): string
    {
        $jwe = $this->jweBuilder
            ->create()
            ->withPayload($payload)
            ->withSharedProtectedHeader([
                'alg' => 'RSA-OAEP-256',
                'enc' => 'A256GCM',
                'kid' => $key->get('kid'),
                'cty' => 'application/json',
            ])
            ->addRecipient($key)
            ->build();

        return $this->serializer->serialize($jwe, 0);
    }
}

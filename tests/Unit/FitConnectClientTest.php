<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OptiGov\FitConnect\Client\SenderClient;
use OptiGov\FitConnect\Client\ZbpClient;
use OptiGov\FitConnect\Config\Endpoints;
use OptiGov\FitConnect\Config\FitConnectConfig;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\FitConnectEventState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;
use OptiGov\FitConnect\Exceptions\FitConnectException;
use OptiGov\FitConnect\Tests\TestKeys;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class FitConnectClientTest extends TestCase
{
    use TestKeys;

    private FitConnectConfig $config;

    /** @var array<int, array{request: RequestInterface, response: ResponseInterface}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestKeys();

        $this->config = new FitConnectConfig(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            endpoints: new Endpoints(
                token: 'https://auth-testing.example.com/token',
                submission: 'https://test.example.com/submission-api',
                destination: 'https://test.example.com/destination-api',
            ),
            zbpDestinationId: 'default-dest-id',
            zbpSigningKey: $this->privateKeyPem,
            zbpCertificate: $this->certificatePem,
        );

        $this->history = [];
    }

    public function testSendMessageFullFlow(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeKeyResponse()),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'submissionId' => 'sub-123',
                'caseId' => 'case-456',
                'destinationId' => 'default-dest-id',
            ])),
            new Response(200),
            new Response(200),
        ]);

        $zbpClient = $this->createZbpClient($mock);

        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test',
            title: 'Title',
            content: 'Content',
            service: 'Service',
            attachments: [new Attachment('test.pdf', 'pdf-bytes', 'application/pdf')],
        );

        $result = $zbpClient->sendMessage($message);

        $this->assertSame('sub-123', $result->submissionId);
        $this->assertSame('case-456', $result->caseId);
        $this->assertSame('default-dest-id', $result->destinationId);
        $this->assertCount(1, $result->attachmentIds);

        $this->assertCount(5, $this->history); // token + keys + announce + attachment + submit
    }

    public function testSendStateNoAttachments(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeKeyResponse()),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'submissionId' => 'sub-state-123',
                'caseId' => 'case-789',
            ])),
            new Response(200),
        ]);

        $zbpClient = $this->createZbpClient($mock);

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::PROCESSING,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $result = $zbpClient->sendState($state);

        $this->assertSame('sub-state-123', $result->submissionId);
        $this->assertEmpty($result->attachmentIds);

        // token + keys + announce + submit (no attachment upload)
        $this->assertCount(4, $this->history);
    }

    public function testGetSubmissionStatusReturnsLatestEventState(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'eventLog' => [
                    $this->fakeJwtEvent(FitConnectEventState::SUBMITTED),
                    $this->fakeJwtEvent(FitConnectEventState::ACCEPTED),
                ],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeJwksResponse()),
        ]);

        $client = $this->createClient($mock);

        $status = $client->getLastSubmissionEventLog('sub-123');

        $this->assertSame(FitConnectEventState::ACCEPTED, $status->state);
        $this->assertSame('https://submission-api.testing.fitko.dev', $status->issuer);
    }

    public function testUsesConfigDestinationIdAsDefault(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeKeyResponse()),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'submissionId' => 'sub-1',
                'caseId' => 'case-1',
            ])),
            new Response(200),
        ]);

        $zbpClient = $this->createZbpClient($mock);

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $result = $zbpClient->sendState($state);
        $this->assertSame('default-dest-id', $result->destinationId);
    }

    public function testSendAntragFullFlow(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeKeyResponse()),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'submissionId' => 'sub-antrag-1',
                'caseId' => 'case-antrag-1',
                'destinationId' => 'default-dest-id',
            ])),
            new Response(200),
            new Response(200),
        ]);

        $client = $this->createClient($mock);

        $submission = new FitConnectSubmission(
            data: json_encode(['F00000001' => 'value']),
            schemaUri: 'https://schema.fitko.de/fim/s00000114_1.1.schema.json',
            serviceIdentifier: 'urn:de:fim:leika:leistung:99400048079000',
            serviceName: 'Testantrag',
            attachments: [new Attachment('doc.pdf', 'pdf-bytes', 'application/pdf')],
        );

        $result = $client->sendSubmission($submission, 'default-dest-id');

        $this->assertSame('sub-antrag-1', $result->submissionId);
        $this->assertSame('case-antrag-1', $result->caseId);
        $this->assertSame('default-dest-id', $result->destinationId);
        $this->assertCount(1, $result->attachmentIds);

        // token + keys + announce + attachment + submit
        $this->assertCount(5, $this->history);
    }

    public function testSendAntragFluentBuilder(): void
    {
        $mock = new MockHandler([
            $this->tokenResponse(),
            new Response(200, ['Content-Type' => 'application/json'], $this->fakeKeyResponse()),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'submissionId' => 'sub-antrag-2',
                'caseId' => 'case-antrag-2',
                'destinationId' => 'default-dest-id',
            ])),
            new Response(200),
        ]);

        $client = $this->createClient($mock);

        $result = $client->submission()
            ->destinationId('default-dest-id')
            ->serviceType('urn:de:fim:leika:leistung:99400048079000', 'Testantrag')
            ->schema('https://schema.fitko.de/fim/s00000114_1.1.schema.json')
            ->data(json_encode(['F00000001' => 'value']))
            ->send()
        ;

        $this->assertSame('sub-antrag-2', $result->submissionId);

        // token + keys + announce + submit (no attachments)
        $this->assertCount(4, $this->history);
    }

    public function testThrowsFitConnectExceptionOnApiError(): void
    {
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'errorCode' => 'ZBP_401_001',
                'description' => 'Invalid credentials',
            ])),
        ]);

        $zbpClient = $this->createZbpClient($mock);

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        try {
            $zbpClient->sendState($state);
            $this->fail('Expected FitConnectException');
        } catch (FitConnectException $e) {
            $this->assertSame('auth', $e->step);
            $this->assertSame(401, $e->statusCode);
            $this->assertSame('ZBP_401_001', $e->errorCode);
            $this->assertSame('Invalid credentials', $e->description);
        }
    }

    private function createClient(MockHandler $mock): SenderClient
    {
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));
        $httpClient = new HttpClient(['handler' => $handlerStack, 'http_errors' => false]);

        return new SenderClient($this->config, new Encryptor, $httpClient);
    }

    private function createZbpClient(MockHandler $mock): ZbpClient
    {
        return new ZbpClient($this->createClient($mock), $this->config);
    }

    private function fakeKeyResponse(): string
    {
        return json_encode([
            'keys' => [$this->encryptionJwk->jsonSerialize()],
        ]);
    }

    private function fakeJwksResponse(): string
    {
        return json_encode(['keys' => [$this->signingPublicJwk->jsonSerialize()]]);
    }

    private function fakeJwtEvent(FitConnectEventState $state): string
    {
        return $this->buildSignedSetJwt([
            'jti' => 'jti-'.bin2hex(random_bytes(4)),
            'iss' => 'https://submission-api.testing.fitko.dev',
            'iat' => time(),
            'sub' => 'submission:'.bin2hex(random_bytes(4)),
            'txn' => 'case:'.bin2hex(random_bytes(4)),
            'events' => [$state->value => []],
        ]);
    }

    private function tokenResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'test-token',
            'expires_in' => 300,
        ]));
    }
}

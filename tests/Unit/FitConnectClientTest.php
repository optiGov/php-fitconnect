<?php

namespace OptiGov\FitConnect\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\FitConnectSubmission;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\FitConnectEventState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;
use OptiGov\FitConnect\Exceptions\FitConnectException;
use OptiGov\FitConnect\FitConnect\Client;
use OptiGov\FitConnect\Tests\TestKeys;
use OptiGov\FitConnect\Zbp\Client as ZbpClient;
use OptiGov\FitConnect\Zbp\SubmissionBuilder;
use Orchestra\Testbench\TestCase;

class FitConnectClientTest extends TestCase
{
    use TestKeys;

    private array $config;

    private Client $client;

    private ZbpClient $zbpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestKeys();

        $this->config = [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'zbp_destination_id' => 'default-dest-id',
            'endpoints' => [
                'token' => 'https://auth-testing.example.com/token',
                'submission' => 'https://test.example.com/submission-api',
                'destination' => 'https://test.example.com/destination-api',
            ],
            'callback' => [
                'enabled' => false,
                'url' => null,
                'secret' => null,
            ],
        ];

        $signer = new Signer($this->privateKeyPem, $this->certificatePem);
        $encryptor = new Encryptor;
        $payloadBuilder = new SubmissionBuilder($signer);
        $this->client = new Client($this->config, $encryptor);
        $this->zbpClient = new ZbpClient($this->client, $payloadBuilder, $this->config);
    }

    private function fakeKeyResponse(): array
    {
        return [
            'keys' => [$this->encryptionJwk->jsonSerialize()],
        ];
    }

    private function fakeJwtEvent(FitConnectEventState $state): string
    {
        $b64url = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $header = $b64url(json_encode(['alg' => 'none']));
        $payload = $b64url(json_encode([
            'events' => [$state->value => []],
            'iss' => 'fit-connect',
            'iat' => time(),
        ]));

        return "{$header}.{$payload}.";
    }

    public function test_send_message_full_flow(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/destinations/default-dest-id/keys' => Http::response($this->fakeKeyResponse()),
            '*/submissions' => Http::response([
                'submissionId' => 'sub-123',
                'caseId' => 'case-456',
                'destinationId' => 'default-dest-id',
            ]),
            '*/submissions/sub-123/attachments/*' => Http::response(null, 200),
            '*/submissions/sub-123' => Http::response(null, 200),
        ]);

        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());

        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test',
            title: 'Title',
            content: 'Content',
            service: 'Service',
            attachments: [new Attachment('test.pdf', 'pdf-bytes', 'application/pdf')],
        );

        $result = $this->zbpClient->sendMessage($message);

        $this->assertSame('sub-123', $result->submissionId);
        $this->assertSame('case-456', $result->caseId);
        $this->assertSame('default-dest-id', $result->destinationId);
        $this->assertCount(1, $result->attachmentIds);

        Http::assertSentCount(5); // token + keys + announce + attachment + submit
    }

    public function test_send_state_no_attachments(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/destinations/default-dest-id/keys' => Http::response($this->fakeKeyResponse()),
            '*/submissions' => Http::response([
                'submissionId' => 'sub-state-123',
                'caseId' => 'case-789',
            ]),
            '*/submissions/sub-state-123' => Http::response(null, 200),
        ]);

        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::PROCESSING,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $result = $this->zbpClient->sendState($state);

        $this->assertSame('sub-state-123', $result->submissionId);
        $this->assertEmpty($result->attachmentIds);

        // token + keys + announce + submit (no attachment upload)
        Http::assertSentCount(4);
    }

    public function test_get_submission_status_returns_latest_event_state(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/events*' => Http::response([
                'eventLog' => [
                    $this->fakeJwtEvent(FitConnectEventState::SUBMITTED),
                    $this->fakeJwtEvent(FitConnectEventState::ACCEPTED),
                ],
            ]),
        ]);

        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $status = $this->client->getLastSubmissionEventLog('sub-123');

        $this->assertSame(FitConnectEventState::ACCEPTED, $status->state);
        $this->assertSame('fit-connect', $status->issuer);
    }

    public function test_uses_config_destination_id_as_default(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/destinations/default-dest-id/keys' => Http::response($this->fakeKeyResponse()),
            '*/submissions' => Http::response([
                'submissionId' => 'sub-1',
                'caseId' => 'case-1',
            ]),
            '*/submissions/sub-1' => Http::response(null, 200),
        ]);

        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $result = $this->zbpClient->sendState($state);
        $this->assertSame('default-dest-id', $result->destinationId);
    }

    public function test_send_antrag_full_flow(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/destinations/default-dest-id/keys' => Http::response($this->fakeKeyResponse()),
            '*/submissions' => Http::response([
                'submissionId' => 'sub-antrag-1',
                'caseId' => 'case-antrag-1',
                'destinationId' => 'default-dest-id',
            ]),
            '*/submissions/sub-antrag-1/attachments/*' => Http::response(null, 200),
            '*/submissions/sub-antrag-1' => Http::response(null, 200),
        ]);

        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());

        $submission = new FitConnectSubmission(
            data: json_encode(['F00000001' => 'value']),
            schemaUri: 'https://schema.fitko.de/fim/s00000114_1.1.schema.json',
            serviceIdentifier: 'urn:de:fim:leika:leistung:99400048079000',
            serviceName: 'Testantrag',
            attachments: [new Attachment('doc.pdf', 'pdf-bytes', 'application/pdf')],
        );

        $result = $this->client->sendSubmission($submission, 'default-dest-id');

        $this->assertSame('sub-antrag-1', $result->submissionId);
        $this->assertSame('case-antrag-1', $result->caseId);
        $this->assertSame('default-dest-id', $result->destinationId);
        $this->assertCount(1, $result->attachmentIds);

        // token + keys + announce + attachment + submit
        Http::assertSentCount(5);
    }

    public function test_send_antrag_fluent_builder(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
            '*/destinations/default-dest-id/keys' => Http::response($this->fakeKeyResponse()),
            '*/submissions' => Http::response([
                'submissionId' => 'sub-antrag-2',
                'caseId' => 'case-antrag-2',
                'destinationId' => 'default-dest-id',
            ]),
            '*/submissions/sub-antrag-2' => Http::response(null, 200),
        ]);

        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());

        $result = $this->client->submission()
            ->destinationId('default-dest-id')
            ->serviceType('urn:de:fim:leika:leistung:99400048079000', 'Testantrag')
            ->schema('https://schema.fitko.de/fim/s00000114_1.1.schema.json')
            ->data(json_encode(['F00000001' => 'value']))
            ->send();

        $this->assertSame('sub-antrag-2', $result->submissionId);

        // token + keys + announce + submit (no attachments)
        Http::assertSentCount(4);
    }

    public function test_throws_fit_connect_exception_on_api_error(): void
    {
        Http::fake([
            '*/token' => Http::response([
                'errorCode' => 'ZBP_401_001',
                'description' => 'Invalid credentials',
            ], 401),
        ]);

        Cache::shouldReceive('store')->with('array')->andReturn(Cache::getFacadeRoot());
        Cache::shouldReceive('get')->with('fitconnect_access_token')->andReturn(null);

        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        try {
            $this->zbpClient->sendState($state);
            $this->fail('Expected FitConnectException');
        } catch (FitConnectException $e) {
            $this->assertSame('auth', $e->step);
            $this->assertSame(401, $e->statusCode);
            $this->assertSame('ZBP_401_001', $e->errorCode);
            $this->assertSame('Invalid credentials', $e->description);
        }
    }
}

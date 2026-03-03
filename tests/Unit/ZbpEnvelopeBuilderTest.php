<?php

namespace OptiGov\FitConnect\Tests\Unit;

use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;
use OptiGov\FitConnect\Tests\TestKeys;
use OptiGov\FitConnect\Zbp\SubmissionBuilder;
use PHPUnit\Framework\TestCase;

class ZbpEnvelopeBuilderTest extends TestCase
{
    use TestKeys;

    private SubmissionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestKeys();

        $signer = new Signer($this->privateKeyPem, $this->certificatePem);
        $this->builder = new SubmissionBuilder($signer);
    }

    public function test_build_message_returns_fit_connect_submission(): void
    {
        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test',
            title: 'Title',
            content: 'Content',
            service: 'Service',
        );

        $submission = $this->builder->fromMessage($message);

        $this->assertNotEmpty($submission->data);
        $this->assertSame('urn:schema-fitko-de:fit-connect:id.bund.de:message_v6', $submission->serviceIdentifier);
        $this->assertSame('Service', $submission->serviceName);
        $this->assertEmpty($submission->attachments);

        // Data should be a signed envelope (JSON with content, sha512sum, authorToken, authorCertificate)
        $envelope = json_decode($submission->data, true);
        $this->assertArrayHasKey('content', $envelope);
        $this->assertArrayHasKey('sha512sum', $envelope);
        $this->assertArrayHasKey('authorToken', $envelope);
        $this->assertArrayHasKey('authorCertificate', $envelope);
    }

    public function test_build_message_passes_attachments_through(): void
    {
        $attachments = [
            new Attachment('file1.pdf', 'pdf-content', 'application/pdf'),
            new Attachment('file2.txt', 'text-content', 'text/plain'),
        ];

        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test',
            title: 'Title',
            content: 'Content',
            service: 'Service',
            attachments: $attachments,
        );

        $submission = $this->builder->fromMessage($message);

        $this->assertCount(2, $submission->attachments);
        $this->assertSame('file1.pdf', $submission->attachments[0]->filename);
        $this->assertSame('file2.txt', $submission->attachments[1]->filename);
    }

    public function test_build_message_includes_attachment_hashes_in_signed_payload(): void
    {
        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test',
            title: 'Title',
            content: 'Content',
            service: 'Service',
            attachments: [new Attachment('doc.pdf', 'pdf-bytes', 'application/pdf')],
        );

        $submission = $this->builder->fromMessage($message);

        $envelope = json_decode($submission->data, true);
        $payload = json_decode($envelope['content'], true);

        $this->assertArrayHasKey('attachments', $payload);
        $this->assertCount(1, $payload['attachments']);
        $this->assertSame('doc.pdf', $payload['attachments'][0]['filename']);
        $this->assertSame(hash('sha512', 'pdf-bytes'), $payload['attachments'][0]['sha512sum']);
    }

    public function test_build_state_returns_fit_connect_submission(): void
    {
        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::PROCESSING,
            publicServiceName: 'Test Service',
            senderName: 'Test Sender',
        );

        $submission = $this->builder->fromState($state);

        $this->assertNotEmpty($submission->data);
        $this->assertSame('urn:schema-fitko-de:fit-connect:id.bund.de:status_v6', $submission->serviceIdentifier);
        $this->assertSame('ZBP State Forwarding', $submission->serviceName);
    }

    public function test_build_state_has_no_attachments(): void
    {
        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $submission = $this->builder->fromState($state);

        $this->assertEmpty($submission->attachments);
    }
}

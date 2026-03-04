<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit\DTOs;

use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\Enums\StorkQaaLevel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ZbpMessageTest extends TestCase
{
    public function testZbpMessageConstruction(): void
    {
        $attachment = new Attachment('doc.pdf', 'content', 'application/pdf');

        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'Test Sender',
            title: 'Test Title',
            content: 'Test Content',
            service: 'Test Service',
            storkQaaLevel: StorkQaaLevel::SUBSTANTIAL,
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            attachments: [$attachment],
        );

        $this->assertSame('e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c', $message->mailboxUuid);
        $this->assertSame('Test Sender', $message->sender);
        $this->assertSame('Test Title', $message->title);
        $this->assertSame('Test Content', $message->content);
        $this->assertSame('Test Service', $message->service);
        $this->assertSame(StorkQaaLevel::SUBSTANTIAL, $message->storkQaaLevel);
        $this->assertSame('54a0cd6a-e11a-4cbb-888f-56f2ca00d8af', $message->applicationId);
        $this->assertCount(1, $message->attachments);
        $this->assertNull($message->retrievalConfirmationAddress);
        $this->assertNull($message->replyAddress);
        $this->assertNull($message->reference);
        $this->assertNull($message->senderUrl);
    }

    public function testZbpMessageDefaults(): void
    {
        $message = new ZbpMessage(
            mailboxUuid: 'e3cacc6f-f53f-4d2c-aa7c-e7c66cfe512c',
            sender: 'sender',
            title: 'title',
            content: 'content',
            service: 'service',
        );

        $this->assertSame(StorkQaaLevel::BASIC, $message->storkQaaLevel);
        $this->assertNull($message->applicationId);
        $this->assertSame([], $message->attachments);
    }
}

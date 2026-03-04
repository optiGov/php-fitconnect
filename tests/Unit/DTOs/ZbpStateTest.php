<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Tests\Unit\DTOs;

use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Enums\ZbpSubmissionState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZbpState::class)]
class ZbpStateTest extends TestCase
{
    public function testZbpStateConstruction(): void
    {
        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::PROCESSING,
            publicServiceName: 'Test Service',
            senderName: 'Test Sender',
            statusDetails: 'In progress',
            additionalInformation: 'Being processed',
        );

        $this->assertSame('54a0cd6a-e11a-4cbb-888f-56f2ca00d8af', $state->applicationId);
        $this->assertSame(ZbpSubmissionState::PROCESSING, $state->status);
        $this->assertSame('Test Service', $state->publicServiceName);
        $this->assertSame('Test Sender', $state->senderName);
        $this->assertSame('In progress', $state->statusDetails);
        $this->assertSame('Being processed', $state->additionalInformation);
        $this->assertNull($state->reference);
        $this->assertNull($state->createdDate);
    }

    public function testZbpStateDefaultsCreatedDateToNull(): void
    {
        $state = new ZbpState(
            applicationId: '54a0cd6a-e11a-4cbb-888f-56f2ca00d8af',
            status: ZbpSubmissionState::SUBMITTED,
            publicServiceName: 'Service',
            senderName: 'Sender',
        );

        $this->assertNull($state->createdDate);
        $this->assertNull($state->statusDetails);
        $this->assertNull($state->additionalInformation);
    }
}

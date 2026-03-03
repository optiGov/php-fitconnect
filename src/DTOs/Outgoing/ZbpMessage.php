<?php

namespace OptiGov\FitConnect\DTOs\Outgoing;

use OptiGov\FitConnect\Enums\StorkQaaLevel;
use OptiGov\FitConnect\Traits\ValidateHelper;

readonly class ZbpMessage
{
    use ValidateHelper;

    public function __construct(
        public string $mailboxUuid,
        public string $sender,
        public string $title,
        public string $content,
        public string $service,
        public StorkQaaLevel $storkQaaLevel = StorkQaaLevel::BASIC,
        public ?string $applicationId = null,
        public ?string $retrievalConfirmationAddress = null,
        public ?string $replyAddress = null,
        public ?string $reference = null,
        public ?string $senderUrl = null,
        /** @var Attachment[] */
        public array $attachments = [],
    ) {
        self::assertUuid($this->mailboxUuid, 'mailboxUuid');
        self::assertLength($this->sender, 'sender', 1, 255);
        self::assertLength($this->title, 'title', 1, 1024);
        self::assertLength($this->content, 'content', 1);
        self::assertLength($this->service, 'service', 1, 255);

        if ($this->applicationId !== null) {
            self::assertUuid($this->applicationId, 'applicationId');
        }
        if ($this->retrievalConfirmationAddress !== null) {
            self::assertLength($this->retrievalConfirmationAddress, 'retrievalConfirmationAddress', 0, 320);
        }
        if ($this->replyAddress !== null) {
            self::assertLength($this->replyAddress, 'replyAddress', 0, 320);
        }
        if ($this->reference !== null) {
            self::assertLength($this->reference, 'reference', 0, 255);
        }
        if ($this->senderUrl !== null) {
            self::assertLength($this->senderUrl, 'senderUrl', 0, 255);
        }
    }
}

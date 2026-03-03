<?php

namespace OptiGov\FitConnect\Zbp\Fluent;

use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Outgoing\Attachment;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\Enums\StorkQaaLevel;
use OptiGov\FitConnect\Zbp\Client;

class MessageBuilder
{
    private string $mailboxUuid;

    private string $sender;

    private string $title;

    private string $content;

    private string $service;

    private StorkQaaLevel $storkQaaLevel = StorkQaaLevel::BASIC;

    private ?string $applicationId = null;

    private ?string $retrievalConfirmationAddress = null;

    private ?string $replyAddress = null;

    private ?string $reference = null;

    private ?string $senderUrl = null;

    /** @var Attachment[] */
    private array $attachments = [];

    public function __construct(
        private readonly Client $client,
    ) {}

    public function to(string $mailboxUuid): self
    {
        $this->mailboxUuid = $mailboxUuid;

        return $this;
    }

    public function from(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function service(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function storkQaaLevel(StorkQaaLevel $level): self
    {
        $this->storkQaaLevel = $level;

        return $this;
    }

    public function applicationId(string $applicationId): self
    {
        $this->applicationId = $applicationId;

        return $this;
    }

    public function retrievalConfirmationAddress(string $address): self
    {
        $this->retrievalConfirmationAddress = $address;

        return $this;
    }

    public function replyAddress(string $address): self
    {
        $this->replyAddress = $address;

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function senderUrl(string $url): self
    {
        $this->senderUrl = $url;

        return $this;
    }

    public function attach(string $filename, string $content, string $mimeType): self
    {
        $this->attachments[] = new Attachment($filename, $content, $mimeType);

        return $this;
    }

    public function send(): SubmissionResult
    {
        $message = new ZbpMessage(
            mailboxUuid: $this->mailboxUuid,
            sender: $this->sender,
            title: $this->title,
            content: $this->content,
            service: $this->service,
            storkQaaLevel: $this->storkQaaLevel,
            applicationId: $this->applicationId,
            retrievalConfirmationAddress: $this->retrievalConfirmationAddress,
            replyAddress: $this->replyAddress,
            reference: $this->reference,
            senderUrl: $this->senderUrl,
            attachments: $this->attachments,
        );

        return $this->client->sendMessage($message);
    }
}

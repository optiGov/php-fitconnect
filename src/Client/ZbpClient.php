<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Client;

use OptiGov\FitConnect\Client\Zbp\EnvelopeBuilder;
use OptiGov\FitConnect\Config\FitConnectConfig;
use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\Fluent\Zbp\Message as FluentMessage;
use OptiGov\FitConnect\Fluent\Zbp\State as FluentState;

class ZbpClient
{
    private readonly EnvelopeBuilder $envelopeBuilder;

    public function __construct(
        private readonly SenderClient $senderClient,
        private readonly FitConnectConfig $config,
    ) {
        if ($config->zbpSigningKey === null || $config->zbpCertificate === null) {
            throw new \InvalidArgumentException('ZbpClient requires zbpSigningKey and zbpCertificate in config');
        }

        $this->envelopeBuilder = new EnvelopeBuilder(
            new Signer($config->zbpSigningKey, $config->zbpCertificate),
        );
    }

    public function message(): FluentMessage
    {
        return new FluentMessage($this);
    }

    public function state(): FluentState
    {
        return new FluentState($this);
    }

    public function sendMessage(ZbpMessage $message): SubmissionResult
    {
        $destinationId = $this->config->zbpDestinationId;

        $jwk = $this->senderClient->getJwkForDestination($destinationId);
        $submission = $this->envelopeBuilder->fromMessage($message);

        return $this->senderClient->submit($submission, $jwk, $destinationId);
    }

    public function sendState(ZbpState $state): SubmissionResult
    {
        $destinationId = $this->config->zbpDestinationId;

        $jwk = $this->senderClient->getJwkForDestination($destinationId);
        $submission = $this->envelopeBuilder->fromState($state);

        return $this->senderClient->submit($submission, $jwk, $destinationId);
    }
}

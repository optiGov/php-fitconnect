<?php

namespace OptiGov\FitConnect\Zbp;

use OptiGov\FitConnect\DTOs\Incoming\SubmissionResult;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpMessage;
use OptiGov\FitConnect\DTOs\Outgoing\ZbpState;
use OptiGov\FitConnect\FitConnect\Client as FitConnectClient;
use OptiGov\FitConnect\Zbp\Fluent\MessageBuilder;
use OptiGov\FitConnect\Zbp\Fluent\StateBuilder;

class Client
{
    public function __construct(
        private readonly FitConnectClient $fitConnectClient,
        private readonly SubmissionBuilder $submissionBuilder,
        private readonly array $config,
    ) {}

    public function message(): MessageBuilder
    {
        return new MessageBuilder($this);
    }

    public function state(): StateBuilder
    {
        return new StateBuilder($this);
    }

    public function sendMessage(ZbpMessage $message): SubmissionResult
    {
        $destinationId = $this->config['zbp_destination_id'];

        $jwk = $this->fitConnectClient->getJwkForDestination($destinationId);
        $submission = $this->submissionBuilder->fromMessage($message);

        return $this->fitConnectClient->submit($submission, $jwk, $destinationId);
    }

    public function sendState(ZbpState $state): SubmissionResult
    {
        $destinationId = $this->config['zbp_destination_id'];

        $jwk = $this->fitConnectClient->getJwkForDestination($destinationId);
        $submission = $this->submissionBuilder->fromState($state);

        return $this->fitConnectClient->submit($submission, $jwk, $destinationId);
    }
}

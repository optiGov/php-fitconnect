<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\DTOs\Incoming;

readonly class SubmissionResult
{
    /**
     * @param string[] $attachmentIds
     */
    public function __construct(
        public string $submissionId,
        public string $caseId,
        public string $destinationId,
        public array $attachmentIds = [],
    ) {
    }
}

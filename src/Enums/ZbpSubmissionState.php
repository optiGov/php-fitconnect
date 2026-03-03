<?php

namespace OptiGov\FitConnect\Enums;

enum ZbpSubmissionState: string
{
    case INITIATED = 'INITIATED';
    case SUBMITTED = 'SUBMITTED';
    case RECEIVED = 'RECEIVED';
    case PROCESSING = 'PROCESSING';
    case ACTION_REQUIRED = 'ACTION_REQUIRED';
    case COMPLETED = 'COMPLETED';
}

<?php

namespace OptiGov\FitConnect\Enums;

enum FitConnectEventState: string
{
    case CREATED = 'https://schema.fitko.de/fit-connect/events/create-submission';
    case SUBMITTED = 'https://schema.fitko.de/fit-connect/events/submit-submission';
    case NOTIFIED = 'https://schema.fitko.de/fit-connect/events/notify-submission';
    case FORWARDED = 'https://schema.fitko.de/fit-connect/events/forward-submission';
    case REJECTED = 'https://schema.fitko.de/fit-connect/events/reject-submission';
    case ACCEPTED = 'https://schema.fitko.de/fit-connect/events/accept-submission';
    case DELETED = 'https://schema.fitko.de/fit-connect/events/delete-submission';
}

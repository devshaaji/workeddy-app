<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Clients;

enum FailureType: string
{
    case TEMPORARY_FAILURE = 'temporary_failure';
    case PERMANENT_FAILURE = 'permanent_failure';
    case CONFIGURATION_ERROR = 'configuration_error';
    case RECIPIENT_INVALID = 'recipient_invalid';
    case PROVIDER_REJECTED = 'provider_rejected';
    case RATE_LIMITED = 'rate_limited';
}

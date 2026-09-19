<?php

namespace App\Infrastructure\Payments\Kashier\Exceptions;

use DomainException;

final class InvalidWebhookSignature extends DomainException
{
}

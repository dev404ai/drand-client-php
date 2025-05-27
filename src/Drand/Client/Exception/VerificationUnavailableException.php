<?php

declare(strict_types=1);

namespace Drand\Client\Exception;

/**
 * Exception thrown when signature verification is unavailable (e.g., missing extension or backend).
 */
class VerificationUnavailableException extends \RuntimeException
{
}

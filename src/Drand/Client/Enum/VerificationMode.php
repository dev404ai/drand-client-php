<?php

declare(strict_types=1);

namespace Drand\Client\Enum;

/**
 * Enum for beacon verification mode.
 */
enum VerificationMode: string
{
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';

    /**
     * Create a VerificationMode enum from a boolean value.
     *
     * @param bool $value True for ENABLED, false for DISABLED
     * @return self
     */
    public static function fromBool(bool $value): self
    {
        return $value ? self::ENABLED : self::DISABLED;
    }
}

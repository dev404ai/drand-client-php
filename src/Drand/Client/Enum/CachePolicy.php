<?php

declare(strict_types=1);

namespace Drand\Client\Enum;

/**
 * Enum for cache policy options.
 */
enum CachePolicy: string
{
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';

    /**
     * Create a CachePolicy enum from a boolean value.
     *
     * @param bool $value True for ENABLED, false for DISABLED
     * @return self
     */
    public static function fromBool(bool $value): self
    {
        return $value ? self::ENABLED : self::DISABLED;
    }
}

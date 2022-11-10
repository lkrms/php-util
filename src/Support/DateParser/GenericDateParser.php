<?php

declare(strict_types=1);

namespace Lkrms\Support\DateParser;

use DateTimeImmutable;
use DateTimeZone;
use Lkrms\Contract\IDateParser;

/**
 * A wrapper around date_create_immutable()
 *
 */
final class GenericDateParser implements IDateParser
{
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        return date_create_immutable($value, $timezone) ?: null;
    }
}

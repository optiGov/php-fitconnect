<?php

declare(strict_types=1);

namespace OptiGov\FitConnect\Traits;

trait ValidateHelper
{
    private static function assertUuid(string $value, string $field): void
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(static::class.": '{$field}' must be a valid UUID, got '{$value}'");
        }
    }

    private static function assertLength(string $value, string $field, int $min, ?int $max = null): void
    {
        $len = mb_strlen($value);
        if ($len < $min) {
            throw new \InvalidArgumentException(static::class.": '{$field}' must be at least {$min} character(s), got {$len}");
        }
        if ($max !== null && $len > $max) {
            throw new \InvalidArgumentException(static::class.": '{$field}' must be at most {$max} characters, got {$len}");
        }
    }

    private static function assertDateTime(string $value, string $field): void
    {
        if (\DateTime::createFromFormat(\DateTime::ATOM, $value) === false
            && \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $value) === false
            && \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $value) === false) {
            throw new \InvalidArgumentException(static::class.": '{$field}' must be a valid ISO 8601 date-time, got '{$value}'");
        }
    }
}

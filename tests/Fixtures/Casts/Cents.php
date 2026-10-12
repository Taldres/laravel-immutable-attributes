<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Casts;

final readonly class Cents
{
    public function __construct(public int $value) {}

    public static function from(mixed $value): self
    {
        return $value instanceof self ? $value : new self(is_numeric($value) ? (int) $value : 0);
    }
}

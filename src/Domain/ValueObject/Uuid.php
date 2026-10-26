<?php

declare(strict_types=1);

namespace Payroll\Domain\ValueObject;

use Stringable;

abstract readonly class Uuid implements Stringable
{
    final private function __construct(public string $value) {}

    public static function generate(): static
    {
        $hex = bin2hex(random_bytes(16));
        $hex[12] = '4';
        $hex[16] = '8';

        return new static(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4)));
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

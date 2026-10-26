<?php

declare(strict_types=1);

use Payroll\Domain\ValueObject\Money;

it('is created from integer cents', function (): void {
    expect(Money::fromCents(100_000)->cents)->toBe(100_000);
});

it('adds amounts', function (): void {
    expect(Money::fromCents(105_000)->add(Money::fromCents(-4_555)))
        ->toEqual(Money::fromCents(100_445));
});

it('supports negative amounts', function (): void {
    expect(Money::fromCents(-4_555)->cents)->toBe(-4_555);
});

it('compares by value', function (): void {
    expect(Money::fromCents(10)->equals(Money::fromCents(10)))->toBeTrue()
        ->and(Money::fromCents(10)->equals(Money::fromCents(-10)))->toBeFalse();
});

it('knows whether it is zero', function (): void {
    expect(Money::fromCents(0)->isZero())->toBeTrue()
        ->and(Money::fromCents(1)->isZero())->toBeFalse();
});

it('formats whole and fractional dollars', function (): void {
    expect(Money::fromCents(110_445)->format())->toBe('$1,104.45')
        ->and(Money::fromCents(100_000)->format())->toBe('$1,000.00');
});

it('formats negative amounts', function (): void {
    expect(Money::fromCents(-4_555)->format())->toBe('-$45.55')
        ->and(Money::fromCents(-10)->format())->toBe('-$0.10');
});

it('formats boundary amounts without a stray sign', function (): void {
    expect(Money::fromCents(0)->format())->toBe('$0.00')
        ->and(Money::fromCents(-1)->format())->toBe('-$0.01')
        ->and(Money::fromCents(1)->format())->toBe('$0.01');
});

<?php

declare(strict_types=1);

use Payroll\Domain\EarningLine;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

it('is created by system calculation with an initial value', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(100_000));

    expect($line->currentValue())->toEqual(Money::fromCents(100_000));
});

it('records LineCalculated on creation', function (): void {
    $id = LineId::generate();
    $line = EarningLine::calculate($id, Money::fromCents(100_000));

    $events = $line->releaseEvents();
    $event = $events[0];
    assert($event instanceof LineCalculated);

    expect($events)->toHaveCount(1)
        ->and($event->lineId)->toEqual($id)
        ->and($event->amount)->toEqual(Money::fromCents(100_000))
        ->and($line->releaseEvents())->toBe([]);
});

it('allows recalculation while there are no manual adjustments', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(100_000));

    $line->recalculate(Money::fromCents(105_000));

    expect($line->currentValue())->toEqual(Money::fromCents(105_000));
});

it('records LineRecalculated', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(100_000));
    $line->releaseEvents();

    $line->recalculate(Money::fromCents(105_000));

    $events = $line->releaseEvents();
    $event = $events[0];
    assert($event instanceof LineRecalculated);

    expect($events)->toHaveCount(1)
        ->and($event->amount)->toEqual(Money::fromCents(105_000));
});

<?php

declare(strict_types=1);

use Payroll\Domain\Audit\AuditEntry;
use Payroll\Domain\EarningLine;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\Event\ManualAdjustmentAdded;
use Payroll\Domain\Exception\AdjustmentAmountCannotBeZero;
use Payroll\Domain\ValueObject\AdjustmentComment;
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

it('applies a manual adjustment to the current value', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));

    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));

    expect($line->currentValue())->toEqual(Money::fromCents(100_445));
});

it('records ManualAdjustmentAdded with its own id, amount and comment', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));
    $line->releaseEvents();

    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));

    $events = $line->releaseEvents();
    $event = $events[0];
    assert($event instanceof ManualAdjustmentAdded);

    expect($events)->toHaveCount(1)
        ->and($event->amount)->toEqual(Money::fromCents(-4_555))
        ->and($event->comment->text)->toBe('Declined benefit')
        ->and($event->adjustmentId->value)->not->toBe('');
});

it('rejects a zero-amount adjustment', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));

    expect(fn() => $line->addManualAdjustment(Money::fromCents(0), new AdjustmentComment('No-op')))
        ->toThrow(AdjustmentAmountCannotBeZero::class);
});

it('ignores recalculation after the first manual adjustment', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));

    $line->recalculate(Money::fromCents(120_000));

    expect($line->currentValue())->toEqual(Money::fromCents(100_445));
});

it('records no event for an ignored recalculation', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->releaseEvents();

    $line->recalculate(Money::fromCents(120_000));

    expect($line->releaseEvents())->toBe([]);
});

it('keeps ignoring recalculation after further adjustments', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->recalculate(Money::fromCents(120_000));
    $line->addManualAdjustment(Money::fromCents(10_010), new AdjustmentComment('Missed overtime bonus'));

    $line->recalculate(Money::fromCents(130_000));

    expect($line->currentValue())->toEqual(Money::fromCents(110_455));
});

it('accumulates multiple adjustments in order', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));

    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->addManualAdjustment(Money::fromCents(10_010), new AdjustmentComment('Missed overtime bonus'));
    $line->addManualAdjustment(Money::fromCents(-10), new AdjustmentComment('Minor rounding adjustment'));
    $line->addManualAdjustment(Money::fromCents(-20), new AdjustmentComment('Second minor rounding adjustment'));

    expect($line->currentValue())->toEqual(Money::fromCents(110_425));
});

it('treats a compensating adjustment as a new entry, not an edit', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(105_000));
    $line->releaseEvents();

    $line->addManualAdjustment(Money::fromCents(-20), new AdjustmentComment('Second minor rounding adjustment'));
    $line->addManualAdjustment(Money::fromCents(20), new AdjustmentComment('Correcting mistake in adjustment #4'));

    $manualAdjustments = array_filter(
        $line->releaseEvents(),
        static fn($event): bool => $event instanceof ManualAdjustmentAdded,
    );

    expect($manualAdjustments)->toHaveCount(2)
        ->and($line->currentValue())->toEqual(Money::fromCents(105_000));
});

it('exposes frozen system value, ordered entries and current value', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(100_000));
    $line->recalculate(Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->addManualAdjustment(Money::fromCents(10_010), new AdjustmentComment('Missed overtime bonus'));

    $audit = $line->auditHistory();

    expect($audit->frozenSystemValue)->toEqual(Money::fromCents(105_000))
        ->and($audit->currentValue)->toEqual(Money::fromCents(110_455))
        ->and(array_map(
            static fn(AuditEntry $entry): array => [$entry->amount->cents, $entry->comment->text],
            $audit->entries,
        ))->toBe([
            [-4_555, 'Declined benefit'],
            [10_010, 'Missed overtime bonus'],
        ]);
});

it('freezes the base at the last system value before the first adjustment', function (): void {
    $line = EarningLine::calculate(LineId::generate(), Money::fromCents(100_000));
    $line->recalculate(Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->recalculate(Money::fromCents(120_000));

    expect($line->auditHistory()->frozenSystemValue)->toEqual(Money::fromCents(105_000));
});

it('reconstitutes an identical line from its event history', function (): void {
    $id = LineId::generate();
    $line = EarningLine::calculate($id, Money::fromCents(100_000));
    $line->recalculate(Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $line->addManualAdjustment(Money::fromCents(10_010), new AdjustmentComment('Missed overtime bonus'));

    $restored = EarningLine::reconstitute($id, $line->releaseEvents());

    expect($restored->currentValue())->toEqual($line->currentValue())
        ->and($restored->auditHistory())->toEqual($line->auditHistory());
});

it('has no pending events after reconstitution', function (): void {
    $id = LineId::generate();
    $line = EarningLine::calculate($id, Money::fromCents(100_000));

    $restored = EarningLine::reconstitute($id, $line->releaseEvents());

    expect($restored->releaseEvents())->toBe([]);
});

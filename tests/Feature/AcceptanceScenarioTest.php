<?php

declare(strict_types=1);

use Payroll\Domain\EarningLine;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;
use Payroll\Infrastructure\InMemoryEarningLineRepository;

it('reproduces the reference scenario from the assignment', function (): void {
    $repository = new InMemoryEarningLineRepository();
    $id = LineId::generate();

    // Step 1: system calculates the line
    $line = EarningLine::calculate($id, Money::fromCents(100_000));
    expect($line->currentValue())->toEqual(Money::fromCents(100_000));

    // Step 2: source data changes, recalculation without manual corrections is allowed
    $line->recalculate(Money::fromCents(105_000));
    expect($line->currentValue())->toEqual(Money::fromCents(105_000));

    // Step 3: first manual correction freezes the system value
    $line->addManualAdjustment(
        Money::fromCents(-4_555),
        new AdjustmentComment('Employee declined dental benefit; reversing deduction'),
    );
    expect($line->currentValue())->toEqual(Money::fromCents(100_445));

    // Step 4: recalculation attempt must be ignored
    $line->recalculate(Money::fromCents(120_000));
    expect($line->currentValue())->toEqual(Money::fromCents(100_445));

    // Steps 5-8: further corrections accumulate, including a compensating one
    $line->addManualAdjustment(
        Money::fromCents(10_010),
        new AdjustmentComment('Late correction: missed approved overtime bonus'),
    );
    expect($line->currentValue())->toEqual(Money::fromCents(110_455));

    $line->addManualAdjustment(Money::fromCents(-10), new AdjustmentComment('Minor rounding adjustment'));
    expect($line->currentValue())->toEqual(Money::fromCents(110_445));

    $line->addManualAdjustment(Money::fromCents(-20), new AdjustmentComment('Second minor rounding adjustment'));
    expect($line->currentValue())->toEqual(Money::fromCents(110_425));

    $line->addManualAdjustment(Money::fromCents(20), new AdjustmentComment('Correcting mistake in adjustment #4'));
    expect($line->currentValue())->toEqual(Money::fromCents(110_445));

    $repository->save($line);
    $audit = $repository->get($id)->auditHistory();

    expect($audit->frozenSystemValue)->toEqual(Money::fromCents(105_000))
        ->and($audit->currentValue)->toEqual(Money::fromCents(110_445))
        ->and(array_map(
            static fn($entry): array => [$entry->amount->cents, $entry->comment->text],
            $audit->entries,
        ))->toBe([
            [-4_555, 'Employee declined dental benefit; reversing deduction'],
            [10_010, 'Late correction: missed approved overtime bonus'],
            [-10, 'Minor rounding adjustment'],
            [-20, 'Second minor rounding adjustment'],
            [20, 'Correcting mistake in adjustment #4'],
        ]);
});

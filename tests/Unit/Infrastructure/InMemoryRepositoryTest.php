<?php

declare(strict_types=1);

use Payroll\Domain\EarningLine;
use Payroll\Domain\Exception\EarningLineNotFound;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;
use Payroll\Infrastructure\InMemoryEarningLineRepository;

it('saves and reloads a line with identical state', function (): void {
    $repository = new InMemoryEarningLineRepository();
    $id = LineId::generate();
    $line = EarningLine::calculate($id, Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));

    $repository->save($line);
    $restored = $repository->get($id);

    expect($restored->currentValue())->toEqual(Money::fromCents(100_445))
        ->and($restored->auditHistory())->toEqual($line->auditHistory());
});

it('accumulates events across multiple saves', function (): void {
    $repository = new InMemoryEarningLineRepository();
    $id = LineId::generate();
    $repository->save(EarningLine::calculate($id, Money::fromCents(105_000)));

    $line = $repository->get($id);
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $repository->save($line);

    expect($repository->get($id)->currentValue())->toEqual(Money::fromCents(100_445))
        ->and($repository->get($id)->auditHistory()->entries)->toHaveCount(1);
});

it('throws when the line does not exist', function (): void {
    $repository = new InMemoryEarningLineRepository();
    $id = LineId::generate();

    expect(fn(): EarningLine => $repository->get($id))
        ->toThrow(EarningLineNotFound::class, sprintf('Earning line %s does not exist.', $id->value));
});

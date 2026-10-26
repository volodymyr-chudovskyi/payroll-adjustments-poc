<?php

declare(strict_types=1);

use Payroll\Application\Command\AddManualAdjustment;
use Payroll\Application\Command\CalculateLine;
use Payroll\Application\Command\RecalculateLine;
use Payroll\Application\Handler\AddManualAdjustmentHandler;
use Payroll\Application\Handler\CalculateLineHandler;
use Payroll\Application\Handler\GetAuditHistoryHandler;
use Payroll\Application\Handler\RecalculateLineHandler;
use Payroll\Application\Query\GetAuditHistory;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;
use Payroll\Infrastructure\InMemoryEarningLineRepository;

/** @return array{InMemoryEarningLineRepository, LineId} */
function calculatedLine(): array
{
    $repository = new InMemoryEarningLineRepository();
    $lineId = LineId::generate();

    new CalculateLineHandler($repository)(new CalculateLine($lineId, Money::fromCents(100_000)));

    return [$repository, $lineId];
}

it('calculates a new line and persists it', function (): void {
    [$repository, $lineId] = calculatedLine();

    expect($repository->get($lineId)->currentValue())->toEqual(Money::fromCents(100_000));
});

it('recalculates an existing line', function (): void {
    [$repository, $lineId] = calculatedLine();

    new RecalculateLineHandler($repository)(new RecalculateLine($lineId, Money::fromCents(105_000)));

    expect($repository->get($lineId)->currentValue())->toEqual(Money::fromCents(105_000));
});

it('adds a manual adjustment to an existing line', function (): void {
    [$repository, $lineId] = calculatedLine();

    new AddManualAdjustmentHandler($repository)(new AddManualAdjustment(
        $lineId,
        Money::fromCents(-4_555),
        new AdjustmentComment('Declined benefit'),
    ));

    expect($repository->get($lineId)->currentValue())->toEqual(Money::fromCents(95_445));
});

it('keeps an adjusted line frozen when recalculated through the handler', function (): void {
    [$repository, $lineId] = calculatedLine();

    new AddManualAdjustmentHandler($repository)(new AddManualAdjustment(
        $lineId,
        Money::fromCents(-4_555),
        new AdjustmentComment('Declined benefit'),
    ));
    new RecalculateLineHandler($repository)(new RecalculateLine($lineId, Money::fromCents(120_000)));

    expect($repository->get($lineId)->currentValue())->toEqual(Money::fromCents(95_445));
});

it('returns the audit history', function (): void {
    [$repository, $lineId] = calculatedLine();

    new AddManualAdjustmentHandler($repository)(new AddManualAdjustment(
        $lineId,
        Money::fromCents(-4_555),
        new AdjustmentComment('Declined benefit'),
    ));

    $audit = new GetAuditHistoryHandler($repository)(new GetAuditHistory($lineId));

    expect($audit->frozenSystemValue)->toEqual(Money::fromCents(100_000))
        ->and($audit->entries)->toHaveCount(1)
        ->and($audit->currentValue)->toEqual(Money::fromCents(95_445));
});

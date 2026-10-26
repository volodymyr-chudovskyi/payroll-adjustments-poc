<?php

declare(strict_types=1);

use Payroll\Domain\EarningLine;
use Payroll\Domain\Exception\EarningLineNotFound;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;
use Payroll\Infrastructure\ConcurrencyConflict;
use Payroll\Infrastructure\SqliteEventStoreRepository;

it('round-trips a line through the sqlite event store', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $id = LineId::generate();

    $line = EarningLine::calculate($id, Money::fromCents(100_000));
    $line->recalculate(Money::fromCents(105_000));
    $line->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $expectedAudit = $line->auditHistory();

    new SqliteEventStoreRepository($pdo)->save($line);
    $restored = new SqliteEventStoreRepository($pdo)->get($id);

    expect($restored->currentValue())->toEqual(Money::fromCents(100_445))
        ->and($restored->auditHistory())->toEqual($expectedAudit);
});

it('appends new events across separate saves in order', function (): void {
    $repository = new SqliteEventStoreRepository(new PDO('sqlite::memory:'));
    $id = LineId::generate();
    $repository->save(EarningLine::calculate($id, Money::fromCents(105_000)));

    $line = $repository->get($id);
    $line->addManualAdjustment(Money::fromCents(-20), new AdjustmentComment('Second minor rounding adjustment'));
    $line->addManualAdjustment(Money::fromCents(20), new AdjustmentComment('Correcting mistake in adjustment #4'));
    $repository->save($line);

    $audit = $repository->get($id)->auditHistory();

    expect($audit->entries)->toHaveCount(2)
        ->and($audit->entries[0]->amount)->toEqual(Money::fromCents(-20))
        ->and($audit->entries[1]->amount)->toEqual(Money::fromCents(20));
});

it('rejects concurrent writes with a version conflict', function (): void {
    $repository = new SqliteEventStoreRepository(new PDO('sqlite::memory:'));
    $id = LineId::generate();
    $repository->save(EarningLine::calculate($id, Money::fromCents(105_000)));

    $first = $repository->get($id);
    $second = $repository->get($id);
    $first->addManualAdjustment(Money::fromCents(-4_555), new AdjustmentComment('Declined benefit'));
    $second->addManualAdjustment(Money::fromCents(10_010), new AdjustmentComment('Missed overtime bonus'));

    $repository->save($first);

    expect(fn() => $repository->save($second))->toThrow(ConcurrencyConflict::class);
});

it('throws when the line does not exist', function (): void {
    $repository = new SqliteEventStoreRepository(new PDO('sqlite::memory:'));

    expect(fn(): EarningLine => $repository->get(LineId::generate()))
        ->toThrow(EarningLineNotFound::class);
});

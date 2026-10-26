<?php

declare(strict_types=1);

use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Uuid;

it('generates unique ids', function (string $class): void {
    assert(is_subclass_of($class, Uuid::class));

    expect($class::generate()->value)->not->toBe($class::generate()->value);
})->with([LineId::class, AdjustmentId::class]);

it('round-trips through string', function (string $class): void {
    assert(is_subclass_of($class, Uuid::class));

    $id = $class::generate();

    expect($class::fromString((string) $id))->toEqual($id);
})->with([LineId::class, AdjustmentId::class]);

it('looks like a uuid', function (string $class): void {
    assert(is_subclass_of($class, Uuid::class));

    expect($class::generate()->value)
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
})->with([LineId::class, AdjustmentId::class]);

it('always carries the uuid version and variant markers', function (): void {
    foreach (range(1, 20) as $attempt) {
        $value = LineId::generate()->value;

        expect($value[14])->toBe('4')
            ->and($value[19])->toBe('8');
    }
});

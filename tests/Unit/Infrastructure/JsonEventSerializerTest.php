<?php

declare(strict_types=1);

use Payroll\Domain\Event\DomainEvent;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\Event\ManualAdjustmentAdded;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;
use Payroll\Infrastructure\JsonEventSerializer;

it('serializes and deserializes every event type', function (DomainEvent $event): void {
    $serializer = new JsonEventSerializer();

    ['type' => $type, 'payload' => $payload] = $serializer->serialize($event);

    expect($serializer->deserialize($type, $payload))->toEqual($event);
})->with([
    'line calculated' => fn(): LineCalculated => new LineCalculated(
        LineId::generate(),
        Money::fromCents(100_000),
        new DateTimeImmutable(),
    ),
    'line recalculated' => fn(): LineRecalculated => new LineRecalculated(
        LineId::generate(),
        Money::fromCents(105_000),
        new DateTimeImmutable(),
    ),
    'manual adjustment added' => fn(): ManualAdjustmentAdded => new ManualAdjustmentAdded(
        LineId::generate(),
        AdjustmentId::generate(),
        Money::fromCents(-4_555),
        new AdjustmentComment('Declined benefit'),
        new DateTimeImmutable(),
    ),
]);

it('uses a stable storage name per event type', function (): void {
    $serializer = new JsonEventSerializer();
    $event = new LineCalculated(LineId::generate(), Money::fromCents(100_000), new DateTimeImmutable());

    expect($serializer->serialize($event)['type'])->toBe('line_calculated');
});

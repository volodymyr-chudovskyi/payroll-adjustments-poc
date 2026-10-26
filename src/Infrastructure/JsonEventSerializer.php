<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use DateTimeImmutable;
use InvalidArgumentException;
use Payroll\Domain\Event\DomainEvent;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\Event\ManualAdjustmentAdded;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final class JsonEventSerializer implements EventSerializer
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function serialize(DomainEvent $event): array
    {
        [$type, $data] = match (true) {
            $event instanceof LineCalculated => ['line_calculated', $this->systemValueData($event->lineId, $event->amount, $event->occurredAt)],
            $event instanceof LineRecalculated => ['line_recalculated', $this->systemValueData($event->lineId, $event->amount, $event->occurredAt)],
            $event instanceof ManualAdjustmentAdded => ['manual_adjustment_added', [
                'line_id' => $event->lineId->value,
                'adjustment_id' => $event->adjustmentId->value,
                'amount' => $event->amount->cents,
                'comment' => $event->comment->text,
                'occurred_at' => $event->occurredAt->format(self::DATE_FORMAT),
            ]],
            default => throw new InvalidArgumentException(sprintf('Unknown event type %s.', $event::class)),
        };

        return ['type' => $type, 'payload' => json_encode($data, JSON_THROW_ON_ERROR)];
    }

    public function deserialize(string $type, string $payload): DomainEvent
    {
        /** @var array{line_id: string, amount: int, occurred_at: string, adjustment_id?: string, comment?: string} $data */
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        $lineId = LineId::fromString($data['line_id']);
        $amount = Money::fromCents($data['amount']);
        $occurredAt = new DateTimeImmutable($data['occurred_at']);

        return match ($type) {
            'line_calculated' => new LineCalculated($lineId, $amount, $occurredAt),
            'line_recalculated' => new LineRecalculated($lineId, $amount, $occurredAt),
            'manual_adjustment_added' => new ManualAdjustmentAdded(
                $lineId,
                AdjustmentId::fromString($data['adjustment_id'] ?? throw new InvalidArgumentException('Missing adjustment_id.')),
                $amount,
                new AdjustmentComment($data['comment'] ?? throw new InvalidArgumentException('Missing comment.')),
                $occurredAt,
            ),
            default => throw new InvalidArgumentException(sprintf('Unknown event type %s.', $type)),
        };
    }

    /** @return array{line_id: string, amount: int, occurred_at: string} */
    private function systemValueData(LineId $lineId, Money $amount, DateTimeImmutable $occurredAt): array
    {
        return [
            'line_id' => $lineId->value,
            'amount' => $amount->cents,
            'occurred_at' => $occurredAt->format(self::DATE_FORMAT),
        ];
    }
}

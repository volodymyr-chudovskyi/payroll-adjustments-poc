<?php

declare(strict_types=1);

namespace Payroll\Domain\Event;

use DateTimeImmutable;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final readonly class ManualAdjustmentAdded implements DomainEvent
{
    public function __construct(
        public LineId $lineId,
        public AdjustmentId $adjustmentId,
        public Money $amount,
        public AdjustmentComment $comment,
        public DateTimeImmutable $occurredAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace Payroll\Domain\Event;

use DateTimeImmutable;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final readonly class LineCalculated implements DomainEvent
{
    public function __construct(
        public LineId $lineId,
        public Money $amount,
        public DateTimeImmutable $occurredAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace Payroll\Application\Command;

use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final readonly class CalculateLine
{
    public function __construct(
        public LineId $lineId,
        public Money $amount,
    ) {}
}

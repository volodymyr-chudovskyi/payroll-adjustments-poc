<?php

declare(strict_types=1);

namespace Payroll\Domain\Audit;

use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\Money;

final readonly class AuditEntry
{
    public function __construct(
        public AdjustmentId $adjustmentId,
        public Money $amount,
        public AdjustmentComment $comment,
    ) {}
}

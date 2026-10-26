<?php

declare(strict_types=1);

namespace Payroll\Domain\Audit;

use Payroll\Domain\ValueObject\Money;

final readonly class AuditHistory
{
    /** @param list<AuditEntry> $entries */
    public function __construct(
        public Money $frozenSystemValue,
        public array $entries,
        public Money $currentValue,
    ) {}
}

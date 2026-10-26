<?php

declare(strict_types=1);

namespace Payroll\Application\Query;

use Payroll\Domain\ValueObject\LineId;

final readonly class GetAuditHistory
{
    public function __construct(public LineId $lineId) {}
}

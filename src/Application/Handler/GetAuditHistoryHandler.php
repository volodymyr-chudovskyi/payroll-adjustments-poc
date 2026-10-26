<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Query\GetAuditHistory;
use Payroll\Domain\Audit\AuditHistory;
use Payroll\Domain\EarningLineRepository;

final readonly class GetAuditHistoryHandler
{
    public function __construct(private EarningLineRepository $repository) {}

    public function __invoke(GetAuditHistory $query): AuditHistory
    {
        return $this->repository->get($query->lineId)->auditHistory();
    }
}

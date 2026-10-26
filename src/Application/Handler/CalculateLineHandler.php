<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\CalculateLine;
use Payroll\Domain\EarningLine;
use Payroll\Domain\EarningLineRepository;

final readonly class CalculateLineHandler
{
    public function __construct(private EarningLineRepository $repository) {}

    public function __invoke(CalculateLine $command): void
    {
        $this->repository->save(EarningLine::calculate($command->lineId, $command->amount));
    }
}

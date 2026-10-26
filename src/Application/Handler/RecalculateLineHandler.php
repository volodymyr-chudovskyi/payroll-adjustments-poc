<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\RecalculateLine;
use Payroll\Domain\EarningLineRepository;

final readonly class RecalculateLineHandler
{
    public function __construct(private EarningLineRepository $repository) {}

    public function __invoke(RecalculateLine $command): void
    {
        $line = $this->repository->get($command->lineId);
        $line->recalculate($command->amount);
        $this->repository->save($line);
    }
}

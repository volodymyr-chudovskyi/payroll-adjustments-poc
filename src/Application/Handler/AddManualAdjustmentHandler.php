<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\AddManualAdjustment;
use Payroll\Domain\EarningLineRepository;

final readonly class AddManualAdjustmentHandler
{
    public function __construct(private EarningLineRepository $repository) {}

    public function __invoke(AddManualAdjustment $command): void
    {
        $line = $this->repository->get($command->lineId);
        $line->addManualAdjustment($command->amount, $command->comment);
        $this->repository->save($line);
    }
}

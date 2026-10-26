<?php

declare(strict_types=1);

namespace Payroll\Domain;

use Payroll\Domain\ValueObject\LineId;

interface EarningLineRepository
{
    public function save(EarningLine $line): void;

    public function get(LineId $id): EarningLine;
}

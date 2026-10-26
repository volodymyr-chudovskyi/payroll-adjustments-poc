<?php

declare(strict_types=1);

namespace Payroll\Domain\Exception;

use DomainException;

final class AdjustmentAmountCannotBeZero extends DomainException
{
    public function __construct()
    {
        parent::__construct('A manual adjustment must change the value of the line.');
    }
}

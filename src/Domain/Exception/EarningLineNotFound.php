<?php

declare(strict_types=1);

namespace Payroll\Domain\Exception;

use DomainException;
use Payroll\Domain\ValueObject\LineId;

final class EarningLineNotFound extends DomainException
{
    public function __construct(LineId $id)
    {
        parent::__construct(sprintf('Earning line %s does not exist.', $id->value));
    }
}

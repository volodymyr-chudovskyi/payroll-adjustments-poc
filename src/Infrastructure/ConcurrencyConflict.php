<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use Payroll\Domain\ValueObject\LineId;
use RuntimeException;

final class ConcurrencyConflict extends RuntimeException
{
    public function __construct(LineId $id)
    {
        parent::__construct(sprintf('Earning line %s was modified concurrently.', $id->value));
    }
}

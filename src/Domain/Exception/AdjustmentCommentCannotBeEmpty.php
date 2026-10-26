<?php

declare(strict_types=1);

namespace Payroll\Domain\Exception;

use DomainException;

final class AdjustmentCommentCannotBeEmpty extends DomainException
{
    public function __construct()
    {
        parent::__construct('A manual adjustment requires a non-empty comment.');
    }
}

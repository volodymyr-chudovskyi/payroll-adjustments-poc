<?php

declare(strict_types=1);

namespace Payroll\Domain\ValueObject;

use Payroll\Domain\Exception\AdjustmentCommentCannotBeEmpty;

final readonly class AdjustmentComment
{
    public string $text;

    public function __construct(string $text)
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new AdjustmentCommentCannotBeEmpty();
        }

        $this->text = $trimmed;
    }
}

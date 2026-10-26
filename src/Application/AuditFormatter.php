<?php

declare(strict_types=1);

namespace Payroll\Application;

use Payroll\Domain\Audit\AuditHistory;
use Payroll\Domain\ValueObject\Money;

final readonly class AuditFormatter
{
    /** @return list<string> */
    public function format(AuditHistory $audit): array
    {
        $lines = [sprintf('  %-74s %10s', 'System value (frozen)', $audit->frozenSystemValue->format())];

        foreach ($audit->entries as $index => $entry) {
            $lines[] = sprintf(
                '  %-74s %10s',
                sprintf('Adjustment %d: %s', $index + 1, $entry->comment->text),
                $this->signed($entry->amount),
            );
        }

        $lines[] = sprintf('  %-74s %10s', 'Current (new) value', $audit->currentValue->format());

        return $lines;
    }

    public function signed(Money $amount): string
    {
        return $amount->cents > 0 ? '+' . $amount->format() : $amount->format();
    }
}

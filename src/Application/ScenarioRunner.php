<?php

declare(strict_types=1);

namespace Payroll\Application;

use Payroll\Application\Command\AddManualAdjustment;
use Payroll\Application\Command\CalculateLine;
use Payroll\Application\Command\RecalculateLine;
use Payroll\Application\Handler\AddManualAdjustmentHandler;
use Payroll\Application\Handler\CalculateLineHandler;
use Payroll\Application\Handler\GetAuditHistoryHandler;
use Payroll\Application\Handler\RecalculateLineHandler;
use Payroll\Application\Query\GetAuditHistory;
use Payroll\Domain\EarningLineRepository;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final readonly class ScenarioRunner
{
    public function __construct(private EarningLineRepository $repository) {}

    /** @return list<string> */
    public function run(): array
    {
        $lineId = LineId::generate();
        $calculate = new CalculateLineHandler($this->repository);
        $recalculate = new RecalculateLineHandler($this->repository);
        $adjust = new AddManualAdjustmentHandler($this->repository);

        $output = [];
        $step = function (int $number, string $description) use (&$output, $lineId): void {
            $value = $this->repository->get($lineId)->currentValue();
            $output[] = sprintf('Step %d  %-68s %10s', $number, $description, $value->format());
        };

        $calculate(new CalculateLine($lineId, Money::fromCents(100_000)));
        $step(1, 'System calculates the line');

        $recalculate(new RecalculateLine($lineId, Money::fromCents(105_000)));
        $step(2, 'Source data changes, system recalculates');

        $adjust(new AddManualAdjustment($lineId, Money::fromCents(-4_555), new AdjustmentComment('Employee declined dental benefit; reversing deduction')));
        $step(3, 'Specialist adds a manual correction (-$45.55)');

        $recalculate(new RecalculateLine($lineId, Money::fromCents(120_000)));
        $step(4, 'System attempts to recalculate (ignored: manual correction exists)');

        $adjust(new AddManualAdjustment($lineId, Money::fromCents(10_010), new AdjustmentComment('Late correction: missed approved overtime bonus')));
        $step(5, 'Specialist adds a second correction (+$100.10)');

        $adjust(new AddManualAdjustment($lineId, Money::fromCents(-10), new AdjustmentComment('Minor rounding adjustment')));
        $step(6, 'Specialist adds a third correction (-$0.10)');

        $adjust(new AddManualAdjustment($lineId, Money::fromCents(-20), new AdjustmentComment('Second minor rounding adjustment')));
        $step(7, 'Specialist adds a fourth correction (-$0.20)');

        $adjust(new AddManualAdjustment($lineId, Money::fromCents(20), new AdjustmentComment('Correcting mistake in adjustment #4')));
        $step(8, 'Specialist adds a compensating correction (+$0.20)');

        $audit = new GetAuditHistoryHandler($this->repository)(new GetAuditHistory($lineId));

        $output[] = '';
        $output[] = 'Final audit history';
        $output[] = sprintf('  %-74s %10s', 'System value (frozen)', $audit->frozenSystemValue->format());

        foreach ($audit->entries as $index => $entry) {
            $output[] = sprintf(
                '  %-74s %10s',
                sprintf('Adjustment %d: %s', $index + 1, $entry->comment->text),
                $this->signed($entry->amount),
            );
        }

        $output[] = sprintf('  %-74s %10s', 'Current (new) value', $audit->currentValue->format());

        return $output;
    }

    private function signed(Money $amount): string
    {
        return $amount->cents > 0 ? '+' . $amount->format() : $amount->format();
    }
}

<?php

declare(strict_types=1);

namespace Payroll\Application;

use DomainException;
use InvalidArgumentException;
use Payroll\Application\Command\AddManualAdjustment;
use Payroll\Application\Command\CalculateLine;
use Payroll\Application\Command\RecalculateLine;
use Payroll\Application\Handler\AddManualAdjustmentHandler;
use Payroll\Application\Handler\CalculateLineHandler;
use Payroll\Application\Handler\RecalculateLineHandler;
use Payroll\Domain\EarningLine;
use Payroll\Domain\EarningLineRepository;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final class ReplSession
{
    private ?LineId $lineId = null;

    private readonly AuditFormatter $formatter;

    public function __construct(private readonly EarningLineRepository $repository)
    {
        $this->formatter = new AuditFormatter();
    }

    /** @return list<string> */
    public function handle(string $input): array
    {
        $parts = explode(' ', trim($input), 2);
        $command = strtolower($parts[0]);
        $argument = trim($parts[1] ?? '');

        if ($command === '') {
            return [];
        }

        try {
            return match ($command) {
                'help' => $this->help(),
                'calculate' => $this->calculate($argument),
                'recalculate' => $this->recalculate($argument),
                'adjust' => $this->adjust($argument),
                'value' => ['Current value: ' . $this->currentValue()->format()],
                'audit' => ['Audit history', ...$this->formatter->format($this->line()->auditHistory())],
                default => [sprintf("Unknown command '%s'. Type 'help'.", $command)],
            };
        } catch (DomainException|InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }
    }

    /** @return list<string> */
    private function help(): array
    {
        return [
            'calculate <amount>         create the line with its system-calculated value',
            'recalculate <amount>       system recalculation (ignored once corrections exist)',
            'adjust <amount> <comment>  manual correction, e.g.: adjust -45.55 Declined benefit',
            'value                      show the current value',
            'audit                      show the full audit history',
            'exit                       quit',
        ];
    }

    /** @return list<string> */
    private function calculate(string $argument): array
    {
        if ($this->lineId instanceof LineId) {
            return ['A line already exists in this session. Use recalculate or adjust, or restart to start over.'];
        }

        $lineId = LineId::generate();
        new CalculateLineHandler($this->repository)(new CalculateLine($lineId, $this->parseAmount($argument)));
        $this->lineId = $lineId;

        return ['Line calculated: ' . $this->currentValue()->format()];
    }

    /** @return list<string> */
    private function recalculate(string $argument): array
    {
        $lineId = $this->requireLine();
        $amount = $this->parseAmount($argument);
        $frozen = $this->line()->auditHistory()->entries !== [];

        new RecalculateLineHandler($this->repository)(new RecalculateLine($lineId, $amount));

        if ($frozen) {
            return ['Recalculation ignored: the line is frozen by manual corrections. Current value: ' . $this->currentValue()->format()];
        }

        return ['Line recalculated: ' . $this->currentValue()->format()];
    }

    /** @return list<string> */
    private function adjust(string $argument): array
    {
        $lineId = $this->requireLine();
        $parts = explode(' ', $argument, 2);
        $amount = $this->parseAmount($parts[0]);
        $comment = new AdjustmentComment($parts[1] ?? '');

        new AddManualAdjustmentHandler($this->repository)(new AddManualAdjustment($lineId, $amount, $comment));

        return [sprintf('Adjustment recorded (%s). Current value: %s', $this->formatter->signed($amount), $this->currentValue()->format())];
    }

    private function currentValue(): Money
    {
        return $this->line()->currentValue();
    }

    private function line(): EarningLine
    {
        return $this->repository->get($this->requireLine());
    }

    private function requireLine(): LineId
    {
        return $this->lineId ?? throw new DomainException('No line in this session yet. Start with: calculate <amount>.');
    }

    private function parseAmount(string $input): Money
    {
        $normalized = str_replace(['$', ','], '', trim($input));

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf("Expected an amount like 1050.00 or -45.55, got '%s'.", $input));
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $cents = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '', 2, '0');

        return Money::fromCents($sign * $cents);
    }
}

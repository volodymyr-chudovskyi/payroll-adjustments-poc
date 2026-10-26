<?php

declare(strict_types=1);

namespace Payroll\Domain;

use DateTimeImmutable;
use Payroll\Domain\Audit\AuditEntry;
use Payroll\Domain\Audit\AuditHistory;
use Payroll\Domain\Event\DomainEvent;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\Event\ManualAdjustmentAdded;
use Payroll\Domain\Exception\AdjustmentAmountCannotBeZero;
use Payroll\Domain\ValueObject\AdjustmentComment;
use Payroll\Domain\ValueObject\AdjustmentId;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final class EarningLine
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    private int $version = 0;

    private Money $systemValue;

    /** @var list<ManualAdjustmentAdded> */
    private array $adjustments = [];

    private function __construct(private readonly LineId $id) {}

    public static function calculate(LineId $id, Money $amount): self
    {
        $line = new self($id);
        $line->recordThat(new LineCalculated($line->id, $amount, new DateTimeImmutable()));

        return $line;
    }

    /** @param list<DomainEvent> $events */
    public static function reconstitute(LineId $id, array $events): self
    {
        $line = new self($id);

        foreach ($events as $event) {
            $line->apply($event);
        }

        return $line;
    }

    public function id(): LineId
    {
        return $this->id;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function recalculate(Money $amount): void
    {
        if ($this->adjustments !== []) {
            return;
        }

        $this->recordThat(new LineRecalculated($this->id, $amount, new DateTimeImmutable()));
    }

    public function addManualAdjustment(Money $amount, AdjustmentComment $comment): void
    {
        if ($amount->isZero()) {
            throw new AdjustmentAmountCannotBeZero();
        }

        $this->recordThat(new ManualAdjustmentAdded(
            $this->id,
            AdjustmentId::generate(),
            $amount,
            $comment,
            new DateTimeImmutable(),
        ));
    }

    public function currentValue(): Money
    {
        return array_reduce(
            $this->adjustments,
            static fn(Money $value, ManualAdjustmentAdded $adjustment): Money => $value->add($adjustment->amount),
            $this->systemValue,
        );
    }

    public function auditHistory(): AuditHistory
    {
        return new AuditHistory(
            $this->systemValue,
            array_map(
                static fn(ManualAdjustmentAdded $adjustment): AuditEntry => new AuditEntry(
                    $adjustment->adjustmentId,
                    $adjustment->amount,
                    $adjustment->comment,
                ),
                $this->adjustments,
            ),
            $this->currentValue(),
        );
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    private function recordThat(DomainEvent $event): void
    {
        $this->apply($event);
        $this->pendingEvents[] = $event;
    }

    private function apply(DomainEvent $event): void
    {
        $this->version++;

        if ($event instanceof LineCalculated || $event instanceof LineRecalculated) {
            $this->systemValue = $event->amount;
        } elseif ($event instanceof ManualAdjustmentAdded) {
            $this->adjustments[] = $event;
        }
    }
}

<?php

declare(strict_types=1);

namespace Payroll\Domain;

use DateTimeImmutable;
use Payroll\Domain\Event\DomainEvent;
use Payroll\Domain\Event\LineCalculated;
use Payroll\Domain\Event\LineRecalculated;
use Payroll\Domain\ValueObject\LineId;
use Payroll\Domain\ValueObject\Money;

final class EarningLine
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    private Money $systemValue;

    private function __construct(private readonly LineId $id) {}

    public static function calculate(LineId $id, Money $amount): self
    {
        $line = new self($id);
        $line->recordThat(new LineCalculated($line->id, $amount, new DateTimeImmutable()));

        return $line;
    }

    public function recalculate(Money $amount): void
    {
        $this->recordThat(new LineRecalculated($this->id, $amount, new DateTimeImmutable()));
    }

    public function currentValue(): Money
    {
        return $this->systemValue;
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
        if ($event instanceof LineCalculated || $event instanceof LineRecalculated) {
            $this->systemValue = $event->amount;
        }
    }
}

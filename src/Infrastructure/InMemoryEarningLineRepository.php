<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use Payroll\Domain\EarningLine;
use Payroll\Domain\EarningLineRepository;
use Payroll\Domain\Event\DomainEvent;
use Payroll\Domain\Exception\EarningLineNotFound;
use Payroll\Domain\ValueObject\LineId;

final class InMemoryEarningLineRepository implements EarningLineRepository
{
    /** @var array<string, list<DomainEvent>> */
    private array $streams = [];

    public function save(EarningLine $line): void
    {
        $key = $line->id()->value;

        $this->streams[$key] = [...$this->streams[$key] ?? [], ...$line->releaseEvents()];
    }

    public function get(LineId $id): EarningLine
    {
        $events = $this->streams[$id->value] ?? [];

        if ($events === []) {
            throw new EarningLineNotFound($id);
        }

        return EarningLine::reconstitute($id, $events);
    }
}

<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use Payroll\Domain\Event\DomainEvent;

interface EventSerializer
{
    /** @return array{type: string, payload: string} */
    public function serialize(DomainEvent $event): array;

    public function deserialize(string $type, string $payload): DomainEvent;
}

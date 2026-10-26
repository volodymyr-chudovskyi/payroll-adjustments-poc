<?php

declare(strict_types=1);

use Payroll\Domain\ValueObject\Uuid;

arch()->preset()->php();

arch()->preset()->security();

arch('domain depends only on itself')
    ->expect('Payroll\Domain')
    ->toOnlyUse('Payroll\Domain');

arch('application never touches infrastructure')
    ->expect('Payroll\Application')
    ->not->toUse('Payroll\Infrastructure');

arch('strict types everywhere')
    ->expect('Payroll')
    ->toUseStrictTypes();

arch('domain classes are final')
    ->expect('Payroll\Domain')
    ->classes()
    ->toBeFinal()
    ->ignoring(Uuid::class);

arch('value objects are immutable')
    ->expect('Payroll\Domain\ValueObject')
    ->classes()
    ->toBeReadonly();

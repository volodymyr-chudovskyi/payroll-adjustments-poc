<?php

declare(strict_types=1);

use Payroll\Application\ScenarioRunner;
use Payroll\Infrastructure\InMemoryEarningLineRepository;
use Payroll\Infrastructure\SqliteEventStoreRepository;

it('replays the reference scenario and prints the final audit history', function (): void {
    $text = implode("\n", new ScenarioRunner(new InMemoryEarningLineRepository())->run());

    expect($text)->toContain('System value (frozen)')
        ->and($text)->toContain('$1,050.00')
        ->and($text)->toContain('Correcting mistake in adjustment #4')
        ->and($text)->toContain('Current (new) value')
        ->and($text)->toContain('$1,104.45');
});

it('prints the value after every step of the assignment table', function (): void {
    $text = implode("\n", new ScenarioRunner(new InMemoryEarningLineRepository())->run());

    foreach (['$1,000.00', '$1,050.00', '$1,004.45', '$1,104.55', '$1,104.45', '$1,104.25'] as $value) {
        expect($text)->toContain($value);
    }
});

it('runs against the sqlite event store as well', function (): void {
    $runner = new ScenarioRunner(new SqliteEventStoreRepository(new PDO('sqlite::memory:')));

    expect(implode("\n", $runner->run()))->toContain('$1,104.45');
});

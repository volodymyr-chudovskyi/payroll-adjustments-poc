<?php

declare(strict_types=1);

use Payroll\Application\ReplSession;
use Payroll\Infrastructure\InMemoryEarningLineRepository;

function repl(): ReplSession
{
    return new ReplSession(new InMemoryEarningLineRepository());
}

it('walks a line through calculate, adjust and audit', function (): void {
    $session = repl();

    expect(implode("\n", $session->handle('calculate 1000.00')))->toContain('$1,000.00')
        ->and(implode("\n", $session->handle('recalculate 1050.00')))->toContain('$1,050.00')
        ->and(implode("\n", $session->handle('adjust -45.55 Employee declined dental benefit')))->toContain('$1,004.45');

    $audit = implode("\n", $session->handle('audit'));

    expect($audit)->toContain('System value (frozen)')
        ->and($audit)->toContain('$1,050.00')
        ->and($audit)->toContain('Employee declined dental benefit')
        ->and($audit)->toContain('$1,004.45');
});

it('requires a calculated line before other commands', function (): void {
    foreach (['recalculate 1050.00', 'adjust -1.00 Oops', 'value', 'audit'] as $command) {
        expect(implode("\n", repl()->handle($command)))->toContain('calculate');
    }
});

it('refuses to calculate twice in one session', function (): void {
    $session = repl();
    $session->handle('calculate 1000.00');

    expect(implode("\n", $session->handle('calculate 2000.00')))->toContain('already');
});

it('reports an ignored recalculation on a frozen line', function (): void {
    $session = repl();
    $session->handle('calculate 1050.00');
    $session->handle('adjust -45.55 Declined benefit');

    $output = implode("\n", $session->handle('recalculate 1200.00'));

    expect($output)->toContain('ignored')
        ->and($output)->toContain('$1,004.45');
});

it('reports domain errors instead of crashing', function (): void {
    $session = repl();
    $session->handle('calculate 1000.00');

    expect(implode("\n", $session->handle('adjust 0 No-op')))
        ->toContain('must change the value')
        ->and(implode("\n", $session->handle('adjust -5.00')))
        ->toContain('non-empty comment');
});

it('parses dollar signs, commas and an explicit plus', function (): void {
    $session = repl();
    $session->handle('calculate $1,000.00');
    $session->handle('adjust +100.10 Missed overtime bonus');

    expect(implode("\n", $session->handle('value')))->toContain('$1,100.10');
});

it('rejects malformed amounts', function (): void {
    $session = repl();

    expect(implode("\n", $session->handle('calculate abc')))->toContain('Expected an amount')
        ->and(implode("\n", $session->handle('calculate 1.234')))->toContain('Expected an amount');
});

it('prints help and reports unknown commands', function (): void {
    expect(implode("\n", repl()->handle('help')))->toContain('adjust <amount> <comment>')
        ->and(implode("\n", repl()->handle('frobnicate')))->toContain('Unknown command');
});

it('ignores blank input', function (): void {
    expect(repl()->handle('   '))->toBe([]);
});

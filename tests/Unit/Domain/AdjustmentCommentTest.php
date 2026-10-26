<?php

declare(strict_types=1);

use Payroll\Domain\Exception\AdjustmentCommentCannotBeEmpty;
use Payroll\Domain\ValueObject\AdjustmentComment;

it('keeps the comment text', function (): void {
    expect(new AdjustmentComment('Minor rounding adjustment')->text)
        ->toBe('Minor rounding adjustment');
});

it('trims surrounding whitespace', function (): void {
    expect(new AdjustmentComment("  Late correction \n")->text)->toBe('Late correction');
});

it('rejects an empty comment', function (): void {
    expect(fn(): AdjustmentComment => new AdjustmentComment(''))
        ->toThrow(AdjustmentCommentCannotBeEmpty::class);
});

it('rejects a whitespace-only comment', function (): void {
    expect(fn(): AdjustmentComment => new AdjustmentComment("   \t "))
        ->toThrow(AdjustmentCommentCannotBeEmpty::class);
});

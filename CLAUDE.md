# Agent instructions

PHP 8.5 proof of concept: event-sourced payroll earning line with append-only manual adjustments. No framework.

## Commands (run inside Docker if no local PHP 8.5)

```sh
docker compose run --rm app composer install
composer test            # pest, parallel
composer test:coverage   # min 90
composer test:types      # min 100
composer test:mutate     # domain mutation testing, min 85
composer analyse         # phpstan level max
composer format          # pint
composer check           # format + analyse + test
composer demo            # replay the reference scenario
```

## Rules

- TDD, strictly: failing test first, minimal implementation, refactor. One red-green-refactor cycle per commit, conventional commit messages.
- No code comments except type annotations PHP cannot express (`@param list<T>`) and spec-tracing step markers in the acceptance test.
- KISS/DRY/YAGNI, but keep the explicit contracts: `EarningLineRepository`, `DomainEvent`, `EventSerializer`.
- Money is integer cents — never float. The frozen base is the last system value before the first adjustment.
- Layer boundaries are enforced by `tests/Arch`; the domain must not gain outside dependencies.
- The acceptance test mirrors the assignment table 1:1 — its numbers are the spec and must not change.

# ADR-0003: No framework

## Context

The assignment states this is a data modeling exercise that needs no UI or framework; unit tests plus a command-line entry point are enough. The target production stack is Laravel.

## Decision

Plain Composer project. Pure-PHP layers (`Payroll\Domain` → nothing, `Payroll\Application` → domain, `Payroll\Infrastructure` → both), enforced by architecture tests. Dependency injection is constructor injection against interfaces; the object graph is assembled in five lines in `bin/demo` — a container would be ceremony at this scale.

## Consequences

- The reviewer runs everything with Composer (or the provided single-service Docker setup); zero services to stand up.
- Dropping into Laravel is mechanical: bind `EarningLineRepository` in a service provider, wrap handlers in artisan commands or controllers; the domain and application layers ship unchanged — the architecture test proves they don't know Laravel exists.
- The CLI demo doubles as the composition root, making the wiring explicit and readable.

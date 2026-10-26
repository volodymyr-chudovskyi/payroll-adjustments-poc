# ADR-0001: Lightweight event sourcing without a library

## Context

The core requirement is an immutable, fully traceable history of manual corrections: nothing may be edited or deleted, mistakes are fixed by compensating entries, and the audit trail must always be reproducible. The team's production stack is DDD + CQRS + Event Sourcing, but the assignment explicitly allows a simpler design.

## Decision

Model `EarningLine` as an event-sourced aggregate with a hand-rolled mechanism (`recordThat` / `apply` / `reconstitute`, ~30 lines). State is a fold over `LineCalculated`, `LineRecalculated` and `ManualAdjustmentAdded`. No event-sourcing library.

An ignored recalculation (the freeze rule) is a silent no-op: no event, no exception. The assignment's expected audit history contains no trace of the ignored attempt, so persisting one would diverge from the spec.

## Consequences

- The append-only event stream *is* the audit history — the central business rule holds by construction, not by discipline.
- Reconstitution is covered by a dedicated round-trip test; any state change that bypasses events breaks it.
- Ignored recalculation attempts are not observable; production would record them to a separate non-audit stream.
- No snapshots or projections — streams here are a handful of events; production would need both.

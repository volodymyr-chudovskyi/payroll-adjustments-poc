# ADR-0002: Money as integer minor units

## Context

The domain is payroll: every value in the reference scenario is an exact dollar amount, and the expected numbers must match to the cent. Binary floating point cannot represent most decimal fractions exactly, and accumulated rounding is the classic payroll defect.

## Decision

`Money` is a `readonly` value object wrapping an `int` number of cents. Arithmetic is integer addition; formatting splits with `intdiv`/modulo. No floats anywhere in the model, including formatting.

## Consequences

- All scenario values are exact by construction; `int64` overflows only beyond ~92 quadrillion cents.
- Single currency and scale are assumptions of the PoC; multi-currency is `brick/money` territory in production.
- Only the operations the domain needs exist (`add`, `equals`, `isZero`, `format`) — no speculative API.

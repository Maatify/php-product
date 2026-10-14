# DEC-012 — Restore Position of Ordered Rows

- **Decision ID:** `DEC-012`
- **Title:** Restore Position of Ordered Rows
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** `display_order` of a restored Option, Option Value, Variant, or Image Assignment (`GAP-11`)
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§9.4, §11.3) and [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (§1.4; Behavior and guarantees: Restore position)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture does not state where a restored ordered row goes. The shared `maatify/persistence` Ordering API (`getNextPosition()`) ignores soft-deleted rows, so after a soft deletion a new row may take the deleted row's old position. The Package Reference recorded a Lead decision on `GAP-11` (commit `08c4276`) that places a restored row at the end of its live scope. The Lead raised it to the Owner.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> End of scope

### Lead Interpretation (Not Owner Words)

1. Restoring an ordered row (Option, Option Value, Variant, Image Assignment, including a Variant that `reconfigure()` restores for reuse) places it at the end of its live scope: its `display_order` becomes the shared Ordering API's next position (`getNextPosition()`), computed under the operation's locks and written in the same operation that clears `deleted_at`.
2. Soft deletion does not compact positions.
3. This deliberately differs from `maatify/php-category`, which keeps the original position, to prevent duplicate live positions.

## Rationale

Keeping the old position could duplicate a live position and break the shifting of `moveWithinScope()`. The end of the live scope is always free and deterministic.

## Consequences

- The behavior is already implemented; the architecture is amended in §9.4 and §11.3.

## Canonical Contract / Current Owner

The architecture (§9.4, §11.3) and the Package Reference own the current contract.

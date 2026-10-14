# DEC-011 — Standalone Option and Option Value Status Changes

- **Decision ID:** `DEC-011`
- **Title:** Standalone Option and Option Value Status Changes
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Option and Option Value status commands (`OPT-02`, `OVL-02`, `GAP-16`)
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§6.4) and [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (§3.3, §3.4)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The Package Reference recorded a Lead decision on `GAP-16` (commit `c57d868`): Option and Option Value status changes only through `reconfigure()` (`CFG-01`), and `OPT-02` and `OVL-02` were marked "superseded by `CFG-01`". That decision was not recorded as a Decision Record. The Lead raised it to the Owner.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> Add standalone updateStatus

### Lead Interpretation (Not Owner Words)

1. A standalone `updateStatus` is added for Option and for Option Value.
2. It locks the Product graph, and rejects a soft-deleted row or a soft-deleted owner with `FC-INV`.
3. A request for the current status is a no-op with no write.
4. It validates the complete configuration with the new status and rejects with `FC-INV` any result that breaks `INV-01` to `INV-07`.
5. `reconfigure()` remains the way to change the active set atomically.
6. This replaces the earlier Lead decision recorded in the Package Reference ("superseded by `CFG-01`").

## Rationale

A standalone status command lets the Host prepare and retire Options and Values whose status change keeps the configuration valid, without building a full reconfiguration target. Validating the whole graph under the Product lock keeps every committed state valid, so the command cannot become a path around the invariants.

## Consequences

- `OPT-02` and `OVL-02` are approved and not implemented yet; the Package Reference lists them under "Not yet implemented". Their implementation belongs to a later Work Unit.
- The architecture is amended in §6.4.
- The earlier Lead decision on `GAP-16` was never a Decision Record, so there is nothing to supersede formally; the Package Reference text that stated it is replaced.

## Canonical Contract / Current Owner

The architecture (§6.4) and the Package Reference (§3.3, §3.4) own the current contract.

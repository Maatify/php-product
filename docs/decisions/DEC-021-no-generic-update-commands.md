# DEC-021 — No Generic Update Commands

- **Decision ID:** `DEC-021`
- **Title:** No Generic Update Commands
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Command inventory: no generic entity update command for Product, Option, Option Value, or Image Role (`GAP-17`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (§3.1, §3.3, §3.6)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§4.1, §5.2, §9.2) and `DEC-006` leave Product, Option, Option Value, and Image Role with no mutable business field besides `status` and lifecycle. The Package Reference recorded the question whether a generic `Update*` command is intended as `GAP-17`.

This record documents a decision that already exists: it is stated in the Package Reference (§3.1, §3.3, §3.6), and settled in commit `c57d868`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. No generic "update entity" command exists in V1 for Product, Option, Option Value, or Image Role.
2. The only mutable fields of the model have their own dedicated commands: Content (`CNT-02`, `Update{Owner}ContentCommand`) and the Variant identifiers (`VAR-01`, `UpdateVariantIdentifiersCommand`). Status, lifecycle, and ordering have their own operations (`updateStatus()`, `softDelete()`, `restore()`, `updateDisplayOrder()`).
3. Codes, keys, and other identity columns are never updated (`DEC-009`).

## Rationale

A generic update command would have nothing to update except identity columns, which are immutable.

## Consequences

- A new mutable business field needs a new decision and its own command.
- `DEC-011` adds a status command for Options and Option Values; a status command is not a generic update command, so this record is unaffected.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (§3.1, §3.3, §3.6) owns the current contract; this record keeps the decision and its rationale.

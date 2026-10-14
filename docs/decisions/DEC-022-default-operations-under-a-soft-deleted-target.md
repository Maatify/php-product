# DEC-022 — Default Operations Under a Soft-Deleted Target

- **Decision ID:** `DEC-022`
- **Title:** Default Operations Under a Soft-Deleted Target
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** `setDefault()` and `clearDefault()` of Image Assignments when the assignment, its target, or its Role is soft deleted (`GAP-18`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Image Assignments; §3.7)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§9.4, §11.4) defines atomic default replacement and forbids auto-promotion, but does not say how the default operations behave when the assignment, its target, or its Role is soft deleted. The Package Reference recorded this as `GAP-18`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Image Assignments; §3.7), and implemented in commit `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. `setDefault()` requires a non-deleted assignment, a non-deleted target Product and Variant, and a non-deleted Role when one is set; an inactive Product, Variant, or Role does not block it. It clears the previous default of the exact scope in the same transaction; the current default is a no-op.
2. `clearDefault()` removes the marker of a non-deleted assignment even when its target or Role is soft deleted, promotes nothing, and is a no-op for a non-default row; on a soft-deleted assignment it is rejected like every other change (`FC-INV`).
3. `softDelete()` clears the marker and promotes nothing. A soft-deleted assignment can never become default, and no operation promotes a replacement row.
4. Creation never sets `is_default`.

## Rationale

Clearing a marker can only reduce state, so allowing it under a deleted target lets the Host tidy up without first restoring the owner; setting a default under a deleted target would create a default that consumer reads can never show.

## Consequences

- `DEC-008` adds the explicit `restoreAsDefault` command for a soft-deleted assignment; it does not change this record.
- Proven by `tests/Integration/Runtime/ImageAssignmentCommandTest.php` and `ConcurrentDefaultReplacementTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Image Assignments; §3.7) owns the current contract; this record keeps the decision and its rationale.

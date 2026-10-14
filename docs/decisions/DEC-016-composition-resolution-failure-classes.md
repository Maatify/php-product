# DEC-016 — Composition Resolution Failure Classes

- **Decision ID:** `DEC-016`
- **Title:** Composition Resolution Failure Classes
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Failure classes of `resolveByComposition()` (`RES-01`, `GAP-08`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Composition resolution; §3.8 `RES-01`)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§5.5) requires the resolution query to reject structurally invalid selections and to fail closed with a documented typed result or exception when no Variant matches, but does not assign exception classes. The Package Reference recorded this as `GAP-08`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Composition resolution; §3.8 `RES-01`), and implemented in commit `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. A Product that does not exist or is not consumer-visible (inactive or soft deleted) raises `ProductNotFoundException` (`FC-NF`), judged before the selection.
2. A structurally invalid selection raises `ProductInvalidArgumentException` (`FC-VAL`): a repeated Option (raised by the criteria), an Option that is not an active, non-deleted Option of the Product (including an Option of another Product or an unknown identity), a Value that is not an active, non-deleted Value of the selected Option (including a Value of another Product or paired with the wrong Option), and incomplete coverage of the active Options. A non-empty selection on a simple Product and an empty selection on a configurable Product are both rejected.
3. A structurally valid, complete selection that matches no consumer-visible Variant raises `VariantNotFoundException` (`FC-NF`), including a historical inactive composition and a match hidden by the product-wide visibility rule (`DEC-024`).
4. A fingerprint that matches with different pairs raises `ProductPersistenceException` (`FC-INT`).
5. The read never creates a Variant, never reactivates one, and never invents a default.

## Rationale

Separating an invisible Product, a malformed request, and a valid request with no match lets the Host respond to each case correctly, and the integrity failure is never mistaken for a caller error.

## Consequences

- Proven by `tests/Integration/Runtime/VariantResolutionTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Composition resolution; §3.8 `RES-01`) owns the current contract; this record keeps the decision and its rationale.

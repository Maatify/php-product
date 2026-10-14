# DEC-020 — Product Reconfiguration Contract

- **Decision ID:** `DEC-020`
- **Title:** Product Reconfiguration Contract
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Input shape, semantics, and failure classes of `reconfigure()` (`CFG-01`, `GAP-15`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Reconfiguration; §3.8 `CFG-01`)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§4.5, §5.4) requires a dedicated atomic Product-local reconfiguration but does not define its typed input, its handling of historical compositions, or its failure classes. The Package Reference recorded this as `GAP-15`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Reconfiguration; §3.8 `CFG-01`), and implemented in commit `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Input.** `ReconfigureProductCommand(productId, options, variants)` is the complete target state, not a list of deltas: `options` is a list of `ReconfigureOptionTarget(optionId, activeOptionValueIds)`, and `variants` is a list of `ReconfigureVariantTarget(composition, reuseVariantId)` with compositions as `OptionValueSelection` pairs. It carries no SKU or barcode; new Variants start with `NULL` (`DEC-019`).
2. **Prepared members only.** Options and Values must already exist under the Product, be non-deleted, and be labelled; the operation activates and deactivates them and never creates them. Options outside the target are deactivated and their Values are kept unchanged; Values of a target Option that are not listed are deactivated.
3. **Variants.** An active Variant absent from the target becomes inactive and keeps its identity. A target composition that exists in the Product's history (active, inactive, or soft deleted) must be named through `reuseVariantId`, confirmed against the actual pairs and the owning Product; a reused inactive Variant is reactivated, a reused soft-deleted Variant is restored and activated inside the same operation, and a reused active Variant is unchanged. A new composition becomes a new active Variant with its position from the Ordering API. The transition to simple names the original empty-composition Variant; a second empty-composition identity is never created.
4. **Failure classes.** A malformed input or a repetition inside the request: `FC-VAL` at construction. An existing composition without a reuse identity, or a reuse identity that does not match: `FC-CONF` (`VariantCompositionConflictException`). A target that violates an invariant: `FC-INV`. A fingerprint that does not match the stored pairs, or a missing original empty-composition Variant for the transition to simple: `FC-INT`. A missing Product: `FC-NF`; an unknown reuse identity: `VariantNotFoundException`; a soft-deleted Product: `FC-INV`.
5. **Atomicity.** Every failure rejects the whole operation with no write. The result lists the Variant identities created (in request order), reactivated, restored, and deactivated, and the Option and Value identities activated and deactivated.

## Rationale

A declarative target state lets the operation validate the whole resulting configuration before any write, which the architecture requires (§5.4 items 1 to 6), and explicit reuse identities keep historical Variant identities from being revived silently.

## Consequences

- Proven by `tests/Integration/Runtime/ProductReconfigurationTest.php` and `ConcurrentReconfigurationTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Reconfiguration; §3.8 `CFG-01`) owns the current contract; this record keeps the decision and its rationale.

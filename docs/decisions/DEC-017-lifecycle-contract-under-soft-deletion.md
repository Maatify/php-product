# DEC-017 — Lifecycle Contract Under Soft Deletion

- **Decision ID:** `DEC-017`
- **Title:** Lifecycle Contract Under Soft Deletion
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Status, soft-delete, and restore operations of every family; operations on soft-deleted rows and owner chains (`GAP-10`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Lifecycle contract; §1.2)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§6.1, §6.5) separates status from deletion and requires restore to revalidate invariants, but does not say which operations a soft-deleted row or a row under a soft-deleted owner accepts. The Package Reference recorded this as `GAP-10`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Lifecycle contract; §1.2), and implemented in commit `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Operations.** `updateStatus()`, `softDelete()`, and `restore()` exist for Products, Variants, and Image Roles; `softDelete()` and `restore()` exist for Options, Option Values, and Image Assignments. An Image Assignment has no status. (Option and Option Value status commands are governed by `DEC-011`.)
2. **Failure classes.** A missing identity raises the family's not-found exception (`FC-NF`); a non-positive identity raises `ProductInvalidArgumentException` (`FC-VAL`). `FC-INV` (`ProductBusinessRuleException`) is for a row that exists but whose state, or whose owner's state, prevents the operation.
3. **Soft-deleted rows.** A soft-deleted row accepts nothing but `restore()`: a status change, a reorder, a content change, a default operation, or a re-delete raises `FC-INV`.
4. **Soft-deleted owners.** A child under a soft-deleted owner cannot be created or changed until the owner chain is restored: an Option or Variant of a deleted Product, a Value of a deleted Option or Product, an Image Assignment of a deleted Product or target Variant (`FC-INV`). `clearDefault()` is the one exception (`DEC-022`).
5. **Restore.** `restore()` requires the row to be soft deleted and its owner chain not deleted, preserves the saved status (an inactive row stays inactive), and revalidates the Product invariants before writing; a rejected restore leaves the row deleted.
6. **No cascade, no status gate.** Deactivating or deleting a parent never changes a child. Inactivity alone blocks no management operation. A request for the current status changes nothing (no write, `updated_at` unchanged).
7. **Invariants.** Product activation requires a non-empty name in a non-deleted Product Content record and a valid configuration; deactivation needs nothing. A Variant is activated only when its full composition satisfies the current configuration (`INV-09`); deactivating or deleting it is rejected when the Product would be left without a valid active Variant or a Value would be left unrepresented. An Option or Value that is part of the active configuration cannot be soft deleted outside reconfiguration. Every validation runs on the whole Product graph read under the Product lock.

## Rationale

Making restore the only operation on a soft-deleted row keeps one explicit, revalidated path back to life, and blocking changes under a deleted owner prevents building state that the owner's restore would have to revalidate implicitly.

## Consequences

- Proven by `tests/Integration/Runtime/ProductLifecycleTest.php`, `VariantLifecycleTest.php`, and `OptionLifecycleTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Lifecycle contract; §1.2) owns the current contract; this record keeps the decision and its rationale.

# DEC-019 — Variant Identifier Command Shape

- **Decision ID:** `DEC-019`
- **Title:** Variant Identifier Command Shape
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Shape and behavior of the SKU and barcode update command (`VAR-01`, `GAP-14`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Variant identifiers; §3.2)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§4.2) allows SKU and barcode to be corrected or replaced through an explicit typed identifier-update mutation with collision validation, but does not define the command shape. The Package Reference recorded this as `GAP-14`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Variant identifiers; §3.2), and implemented in commit `c62a2b7`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Command.** `UpdateVariantIdentifiersCommand` carries the Variant identity and one `VariantIdentifierChange` each for the SKU and the barcode, in three exclusive states: `unchanged()`, `set(string)`, and `clear()`.
2. **Values.** A set value is valid UTF-8, not empty or whitespace only (any Unicode whitespace), at most 128 characters, and stored verbatim (a barcode keeps its leading zeroes); a violation is `ProductInvalidArgumentException` at construction.
3. **Preconditions.** The Product row is locked first, then the Variant row is read under its lock. A missing Variant raises `VariantNotFoundException`; a soft-deleted Variant or Product raises `ProductBusinessRuleException`; an inactive Variant or Product blocks nothing.
4. **No-op.** A field set to its exact stored value, or cleared while `NULL`, does not change; when no field changes, nothing is written.
5. **Uniqueness.** Each value is unique across all Variants under the collation while assigned; a soft-deleted Variant keeps its values reserved; replacing or clearing releases the old value. Only MySQL driver error 1062 on `uq_maa_product_variants_sku` or `uq_maa_product_variants_barcode` becomes `VariantSkuAlreadyExistsException` or `VariantBarcodeAlreadyExistsException`, naming the value, with the original `PDOException` as `previous`; there is no pre-check.
6. The Variant identity never changes.

## Rationale

Three explicit states remove the ambiguity of a nullable field (leave unchanged versus clear), and letting the database key decide collisions keeps the check correct under concurrency.

## Consequences

- Proven by `tests/Integration/Runtime/VariantIdentifiersTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Variant identifiers; §3.2) owns the current contract; this record keeps the decision and its rationale.

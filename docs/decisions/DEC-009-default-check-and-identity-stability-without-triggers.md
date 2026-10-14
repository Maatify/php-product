# DEC-009 — Soft-Deleted Default CHECK Retained; Identity Stability Is an Application Contract Without Triggers

- **Decision ID:** `DEC-009`
- **Title:** Soft-Deleted Default CHECK Retained; Identity Stability Is an Application Contract Without Triggers
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Database guard for soft-deleted default assignments / Enforcement layer of identity stability / No triggers or routines
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Identity stability; §1.2) and `schema/product.sql`
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The schema of `maa_product_image_assignments` carries `CONSTRAINT chk_maa_product_image_assignments_default_not_deleted CHECK (deleted_at IS NULL OR is_default = 0)`. The architecture (§4.1) requires identity columns to be immutable; the schema enforces uniqueness of each identity, and no trigger or routine stops an `UPDATE` of an identity column issued outside the package.

The Lead's Post-Merge Reconciliation comment on `Maatify/php-product#7` on 2026-10-12 (comment `6133230631`, item 2 of the remaining blockers) recorded as unresolved the Owner decisions concerning identity-stability triggers and the `CHECK` (then written with `is_primary`). The Lead raised this to the Owner as item B2.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> Keep CHECK, no triggers

### Lead Interpretation (Not Owner Words)

1. `CHECK (deleted_at IS NULL OR is_default = 0)` stays in the schema as the final database guard: a soft-deleted assignment can never be the default.
2. The schema defines no triggers and no routines.
3. Identity stability is an application contract: no package write path updates an identity column, and no service method replaces one. It is proven by `tests/Unit/IdentityStabilityTest.php`, which scans every `UPDATE` statement in `src/`, and by a schema test that asserts that no trigger or routine exists (`tests/Integration/Schema/SchemaInstallTest.php`).
4. This closes the Owner blocker recorded as item 2 in comment `6133230631`.

## Rationale (Lead; the Owner chose among options presented with these trade-offs)

The `CHECK` is a declarative constraint with no runtime cost to the package and protects the default invariant against any writer. Triggers would add hidden database behavior to a reusable package, complicate installation and migration for the Host, and still not express the whole identity contract. Writes outside the package remain the Host's responsibility.

## Consequences

- The database does not stop an identity `UPDATE` issued by the Host outside the package; the Package Reference states this limit.
- Identity corrections use an explicit migration or replacement process outside the package.
- A future trigger or routine needs a new Owner decision.

## Canonical Contract / Current Owner

The Package Reference (Identity stability, §1.2) and `schema/product.sql` own the current contract.

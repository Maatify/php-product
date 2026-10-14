# DEC-010 — Re-Adding a Soft-Deleted Identity

- **Decision ID:** `DEC-010`
- **Title:** Re-Adding a Soft-Deleted Identity
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Create semantics for every family that keeps identity across soft-deleted history (`GAP-09`)
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§6.1, §9.3) and [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (§1.2)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The approved architecture worded the rule two ways. §6.1 ("Re-adding a soft-deleted logical identity restores/reuses that identity") and §9.3 ("Re-adding the same logical assignment restores/reuses its original identity") could be read as an implicit restore on create. §6.4 and §17 require rejection and an explicit restore. The Package Reference recorded this as `GAP-09` and then as a settled Lead reading in favor of the typed conflict. The Lead raised it to the Owner.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> Typed conflict + explicit restore

### Lead Interpretation (Not Owner Words)

1. `create` never restores implicitly and never inserts a second row for an identity that exists in soft-deleted history.
2. It fails with a typed conflict (`FC-CONF`) that identifies the existing identity.
3. The only way back is an explicit `restore`, which reuses the original identity and revalidates the invariants.
4. This applies to every family that keeps identity across soft-deleted history: Product code, Option and Option Value codes, the Image Role key, Content identity, Variant composition, Variant SKU and barcode, and Image Assignment identity.
5. The conflict between A§6.1/A§9.3 ("restores/reuses") and A§6.4/A§17 is resolved in favor of A§6.4/A§17.

## Rationale

An implicit restore would hide a lifecycle transition inside a create, skip the revalidation a restore performs, and make the caller unable to tell a new identity from a revived one. A typed conflict keeps both operations explicit and matches `maatify/php-category`.

## Consequences

- The architecture is amended in §6.1 and §9.3.
- The behavior is already implemented and proven by the Integration tests; the Package Reference records it in §1.2.

## Canonical Contract / Current Owner

The architecture (§6.1, §9.3) and the Package Reference (§1.2) own the current contract.

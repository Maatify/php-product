# DEC-026 — Public Type Names

- **Decision ID:** `DEC-026`
- **Title:** Public Type Names
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Exact PHP type names of the public API (`GAP-02`), including the `*CriteriaDTO` names after relocation under `DEC-013`
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Implemented Public API; Approved Contract Inventory: Names)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§5.5, §12, §13) gives illustrative names only. The Package Reference recorded the exact PHP type names as `GAP-02`, to be decided when each type is written, under the root namespace `Maatify\Product\` fixed by Package Building §4 and `composer.json`.

Items 1 and 2 document a decision that already exists: it is stated in the Package Reference (Implemented Public API; Approved Contract Inventory: Names), and `GAP-02` was closed in commit `c57d868`. Item 3 is the Lead's placement decision for the relocation approved by `DEC-013`, given with the Work Unit that records these decisions. The record is made on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change any implemented name.

## Decision

1. The exact public type names are the names recorded in the Implemented Public API of the Package Reference. They follow the suffix rules of Package Building §4 (`Interface`, `Enum`, `DTO`, `Exception`) and the method naming of `maatify/php-category` (`create`, `updateStatus`, `softDelete`, `restore`, `updateDisplayOrder`, `getById`, `getByIdForManagement`).
2. The illustrative names of the architecture (`VariantCompositionCriteria`, `ResolveVariantByCompositionQueryInterface`, `withExactLanguage()`, and so on) are not binding and are not reserved.
3. The `*CriteriaDTO` types keep their names when `DEC-013` moves them from `{Capability}/DTO/` to the `Criteria/` responsibility of the Management or Consumer side. The relocation is implemented in WU-8.

## Rationale

Fixed names give the Host one inventory to depend on before the first release. Keeping the `*CriteriaDTO` names avoids a rename that adds no meaning while the placement moves to the canonical `Criteria/` responsibility (Package Building §5).

## Consequences

- A new public type follows the same rules and is recorded in the Implemented Public API when it exists.
- `tests/Unit/PublicApiInventoryTest.php` checks that every public type is listed or tagged `@internal`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Implemented Public API; Approved Contract Inventory: Names) owns the current contract; this record keeps the decision and its rationale.

# DEC-023 — Image Assignment Management Criteria

- **Decision ID:** `DEC-023`
- **Title:** Image Assignment Management Criteria
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Typed selectors of the Image Assignment management list (`GAP-19`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Image Assignment criteria; §1.6; §3.7)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§9.3, §12) treats `NULL` as an exact scope value and requires the absence of a filter and an explicit `NULL` not to be conflated, but does not define the management criteria for Image Assignments. The Package Reference recorded this as `GAP-19`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Image Assignment criteria; §1.6; §3.7), and implemented in commit `c4c33c2`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. `ImageAssignmentListCriteriaDTO` lists the assignments of one Product.
2. `ImageAssignmentTargetFilter` selects any target, the Product level only (`variant_id IS NULL`), or one Variant.
3. `ImageAssignmentRoleFilter` (by Role identity), `ImageAssignmentLanguageFilter`, and `ImageAssignmentPlatformFilter` each select omitted (no condition), exactly `NULL`, or one exact value compared under the collation, with the creation rules for language and platform.
4. Omitting a dimension and asking for `NULL` are different typed states; `NULL` never acts as a wildcard and a value never matches `NULL`.

## Rationale

Each dimension needs three distinct intents. `maatify/php-category` filters role by identity in the same way but language and platform only together; separate selectors per dimension let the Host express every exact management query without a fallback.

## Consequences

- Proven by `tests/Integration/Runtime/ManagementListTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Image Assignment criteria; §1.6; §3.7) owns the current contract; this record keeps the decision and its rationale.

# DEC-027 — Placement of the Shared PDO Support

- **Decision ID:** `DEC-027`
- **Title:** Placement of the Shared PDO Support
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Source placement of `src/Common/Pdo/*`
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Source Topology)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

`src/Common/Pdo/` holds package-internal PDO support shared by several capabilities (`DuplicateKey`, `ManagementListQuery`, `RowHydrator`). Package Building §5 (Technology Placement) orders placement as Capability → Responsibility → Technology, so technology must sit under a responsibility such as `Repository/Pdo/`.

The Lead took this placement decision on 2026-10-14, with the Work Unit that records the decisions of `DEC-013`, and recorded it here and in the Package Reference (Source Topology) in the same change (`DECISION_GOVERNANCE_STANDARD_AR.md` §3).

## Decision

1. `src/Common/Pdo/*` moves to `src/Common/Repository/Pdo/`.
2. The move is implemented in WU-8, together with the Management and Consumer split of `DEC-013`.

## Rationale

The classes are persistence support used by several capabilities' repositories, so `Common/Repository/Pdo/` follows the canonical order and keeps `Common/` a shared responsibility rather than a technology root.

## Consequences

- Nothing in `src/Common/Pdo/` is part of the public API, so the move breaks no consumer contract.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Source Topology) owns the current contract; this record keeps the decision and its rationale.

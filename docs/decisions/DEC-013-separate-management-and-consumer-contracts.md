# DEC-013 — Separate Management and Consumer Contracts

- **Decision ID:** `DEC-013`
- **Title:** Separate Management and Consumer Contracts
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Public API topology / Management and Consumer use cases inside each Capability / Factory construction methods (E-2)
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§13) and [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Source Topology, Implemented Public API)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The implemented services expose management and consumer methods on one interface per capability (for example `getById` and `getByIdForManagement` on `ProductServiceInterface`). Package Building Standard §15 requires separate Management and Consumer contracts when the two use cases are distinct, and §5 places that split inside the Capability. The Lead raised the question to the Owner as item E-2.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> Split now

### Lead Interpretation (Not Owner Words)

1. Each capability has a separate Management contract and Consumer contract inside the Capability, per Package Building §5 and §15.
2. Layout: `{Capability}/Management/{Command,Criteria,Service}/` and `{Capability}/Consumer/{Criteria,Service}/`.
3. Types shared by both use cases stay at the Capability level: result DTOs, Enums, Exceptions, the Repository, and ValueObjects.
4. A capability with no consumer read (Image Role) has a Management side only.
5. The Factory methods are replaced by the pair `create{X}ManagementService` / `create{X}ConsumerService`.
6. Nothing has been released, so no compatibility layer is kept.
7. The implementation belongs to WU-8.

## Rationale (Lead; the Owner chose among options presented with these trade-offs)

The Host's administrative surface and its storefront surface have different visibility rules. Separate contracts make the consumer side unable to reach deleted or invisible records by construction, and doing it before the first release avoids a breaking change later.

## Consequences

- The architecture is amended in §13.
- The split is approved and not implemented yet; the Package Reference lists it under "Not yet implemented". Until WU-8 lands, the Implemented Public API describes the current single service per capability.
- The relocation of `*CriteriaDTO` types and of `Common/Pdo/*` that accompanies this split is recorded in `DEC-026` and `DEC-027`.

## Canonical Contract / Current Owner

The architecture (§13) and the Package Reference own the current contract.

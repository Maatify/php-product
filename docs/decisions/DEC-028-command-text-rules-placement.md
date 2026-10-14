# DEC-028 — Placement of the Command Text Rules

- **Decision ID:** `DEC-028`
- **Title:** Placement of the Command Text Rules
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Source placement of `src/Common/Command/CommandTextRules`
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Source Topology)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

`src/Common/Command/CommandTextRules` holds the text validation rules (valid UTF-8, length, Unicode whitespace) shared by the Commands of several capabilities.

The Lead took this placement decision on 2026-10-14, with the Work Unit that records the decisions of `DEC-013`, and recorded it here and in the Package Reference (Source Topology) in the same change (`DECISION_GOVERNANCE_STANDARD_AR.md` §3).

## Decision

1. `Common/Command/CommandTextRules` stays where it is, as the shared input-validation rules of Commands.

## Rationale

Package Building §13 places input validation in Commands, and `Common/` is permitted for genuinely shared primitives used by more than one capability (Package Building §5). The rules are used by the Commands of several capabilities and are owned by none of them.

## Consequences

- No move is required in WU-8 for this class.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Source Topology) owns the current contract; this record keeps the decision and its rationale.

# DEC-018 — Content Fields and Limits

- **Decision ID:** `DEC-018`
- **Title:** Content Fields and Limits
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Fields and value limits of Product, Option, and Option Value Content (`GAP-13`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Content; §1.1; §3.5)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§4.1, §11) lists `name`, an optional short description, and an optional description for Product Content and a label for Option and Option Value Content, but does not settle the PHP-level limits and verbatim storage. The Package Reference recorded this as `GAP-13`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Content; §1.1; §3.5), and implemented in commit `87642d3`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Product Content.** `name` is required, at most 255 characters, not empty or whitespace only (any Unicode whitespace). `shortDescription` is optional, at most 500 characters. `description` is optional, at most 65535 bytes (the `TEXT` column).
2. **Option and Option Value Content.** `name` only, with the same rules as the Product Content `name`.
3. **Storage.** Values are valid UTF-8 and stored verbatim, never trimmed; an empty optional string is kept and differs from `null`. `name` is a label, not HTML; the package never renders or sanitizes markup.
4. **Validation point.** A violation is `ProductInvalidArgumentException` at Command construction. The `language_code` rule is the same as for Image Assignments.

## Rationale

The limits follow the schema columns so that validation fails in PHP before the database truncates or rejects a value; the `description` byte limit is checked in PHP because `TEXT` is byte-bounded. `maatify/php-category` has no PHP limit on its description, so this is a deliberate difference.

## Consequences

- Proven by the Content Unit tests and `tests/Integration/Runtime/ContentCommandTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Content; §1.1; §3.5) owns the current contract; this record keeps the decision and its rationale.

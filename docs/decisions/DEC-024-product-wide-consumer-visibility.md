# DEC-024 — Product-Wide Consumer Visibility

- **Decision ID:** `DEC-024`
- **Title:** Product-Wide Consumer Visibility
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Consumer visibility of Variants, Options, and Option Values when an active Value is unrepresented (`GAP-26`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Variant reads and Consumer reads; §3.2)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§6.2, §12) requires every active Value of an active Option to be represented by an active valid Variant. No package write path breaks that rule, but direct SQL can. The Package Reference recorded the read-time treatment of an unrepresented Value as `GAP-26`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Variant reads and Consumer reads; §3.2), and implemented in commit `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. When an active Value of an active Option is represented by no active, structurally valid Variant, **every** Variant of the Product is hidden from the consumer reads, the single get and the list alike, so the two reads never disagree.
2. The consumer Option and Option Value reads share the same SQL source of Variant visibility, so every Option and Value of the Product is hidden too.
3. Management reads keep showing the stored state.
4. The consumer reads return the correct result again as soon as every active Value is represented.

## Rationale

Visibility is judged on the configuration as a whole, so a consumer is never offered a selectable Value that cannot resolve to a Variant, and a single get never shows a Variant that the list hides.

## Consequences

- Composition resolution reports a match hidden by this rule as `VariantNotFoundException` (`DEC-016`).
- Proven by `tests/Integration/Runtime/VariantReadTest.php` and `ConsumerReadTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Variant reads and Consumer reads; §3.2) owns the current contract; this record keeps the decision and its rationale.

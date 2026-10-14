# DEC-015 — Management List Bounds, Pagination, and Default Order

- **Decision ID:** `DEC-015`
- **Title:** Management List Bounds, Pagination, and Default Order
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Unpaginated list bound / Shared pagination / Default and allowed sort keys (`GAP-05`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Management lists, search, and pagination; §1.4; §1.5)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§12) bounds every unpaginated list to 100 records and requires the shared `maatify/persistence` pagination for large management lists, but does not fix the defaults, the sort whitelists, or the default order of each list. The Package Reference recorded this as `GAP-05`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Management lists, search, and pagination; §1.4; §1.5), and implemented in commits `87642d3` (Content) and `c4c33c2`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Unpaginated read.** Every management list has an unpaginated read bounded by its criteria's `maxResults` (1 to 100, default 100). The result is silently truncated at the bound, with no "more" flag. Every unpaginated `LIMIT` is bound as an integer parameter.
2. **Paginated twin.** Every management list has a paginated read through `maatify/persistence` (`PdoPaginator`, `PageRequest` in, `PageResult` of the family's public DTO out): 20 per page by default, 1 to 100 (a larger request is clamped), a sort whitelist per list where an unknown key falls back to the default, and `id` ascending as the tie-breaker. `maxResults` does not apply to a page.
3. **Default order and sort keys.**
   - Products: by `id` (`id`, `code`, `created_at`, `updated_at`).
   - Variants, Options, Option Values: by `display_order, id` (`display_order`, `id`, `created_at`, `updated_at`).
   - Image Roles: by the binary value of the role key, then `id` (`role_key`, `id`, `created_at`, `updated_at`).
   - Image Assignments: by `ordering_scope`, `display_order`, `id`; the page sorts by the derived `business_order`, `ROW_NUMBER() OVER (ORDER BY ordering_scope, display_order, id)` (`business_order`, `id`, `created_at`, `updated_at`).
   - Content: by `language_code` (the unlocalized record first), then `id` (`language_code`, `id`, `created_at`, `updated_at`).
4. **Filters.** The family's status (`null` means any) and the deleted state (`NON_DELETED` by default). Management lists hide nothing for an owner's state; a non-positive owner identity is `ProductInvalidArgumentException`, and a missing owner yields an empty result. `total`, `filtered`, and the page use one filter, except the Product search (`DEC-014`).
5. No counts, aggregates, or summaries beyond the pagination metadata are provided.

## Rationale

The bounds, defaults, and tie-breaker match `maatify/php-category`, so a Host administering both packages sees one behavior, and the shared paginator avoids a package-local pagination engine (Package Building §11).

## Consequences

- Proven by `tests/Integration/Runtime/ManagementListTest.php` and `tests/Integration/Runtime/ContentCommandTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Management lists, search, and pagination; §1.4; §1.5) owns the current contract; this record keeps the decision and its rationale.

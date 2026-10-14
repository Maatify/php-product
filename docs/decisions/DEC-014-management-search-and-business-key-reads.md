# DEC-014 — Management Search and Business-Key Reads

- **Decision ID:** `DEC-014`
- **Title:** Management Search and Business-Key Reads
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Management search for Products / Literal code matching and escaping / Business-key reads (`GAP-04`)
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Management lists, search, and pagination; §1.5)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§12) allows management reads with explicit filters but does not define which fields are searchable, how a search term is matched, or which business keys can be read. The Package Reference recorded this as `GAP-04`.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Management lists, search, and pagination; §1.5), and implemented in commits `c4c33c2` and `4712da6`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Search exists for Products only.** `ProductListCriteriaDTO::$search` is trimmed of Unicode whitespace, must then be non-empty and at most 100 characters, and matches a literal substring of `code` under the collation `utf8mb4_unicode_ci`.
2. **Escaping.** `!`, `%`, and `_` are escaped with `!`, and the query uses `LIKE ... ESCAPE '!'`, which works under any Host `sql_mode`, including `NO_BACKSLASH_ESCAPES`; `\` is an ordinary character.
3. **Counts.** `total` counts without the search; `filtered` and the page are computed with it.
4. **Business-key reads.** `getByCodeForManagement()` finds a Product by `code` and `getByKeyForManagement()` finds an Image Role by key, under the collation, with the identifier rules of creation and the deleted-state filter, raising `ProductNotFoundException` or `ImageRoleNotFoundException` naming the value.
5. **No other business-key reads in V1.** There is no read by SKU, barcode, or Option or Value code, and Image Roles have no search.

## Rationale

The Product code is the only package-owned business key a Host searches for administration, matching `maatify/php-category`, which searches `code` only. An escape character other than backslash keeps literal matching correct whatever `sql_mode` the Host sets; `php-category` escapes with a backslash, so this is a deliberate difference.

## Consequences

- Adding a search field or a business-key read needs a new decision.
- Proven by `tests/Integration/Runtime/ManagementListTest.php`.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Management lists, search, and pagination; §1.5) owns the current contract; this record keeps the decision and its rationale.

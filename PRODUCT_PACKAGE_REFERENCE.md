# Product Package Reference

**Package:** `maatify/php-product`
**Status:** Pre-implementation. This file is the canonical root Package Reference path required by the Package Building Standard (`/{PACKAGE_NAME}_PACKAGE_REFERENCE.md`). No runtime exists yet, so the Public Runtime API inventory, exception guarantees, and behavior sections are not populated. They are added in place by the implementation Work Units, and nothing here is a released contract.
**Architecture contract:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md), Owner-approved under [`DEC-007`](docs/decisions/DEC-007-product-v1-implementation-start-authorization.md)

## Operational Read / Reporting Classification

This section records the classification required by Package Building Standard §15.

**Classification: In Scope.**

**Reason.** §15 decides applicability by what the package owns, not by whether it uses a database. `php-product` owns persisted domain state with its own lifecycle and invariants (architecture §3.1, §4–§6, §9):

- Product identity and its `active`/`inactive` status and soft-deletion state;
- Variant identity and lifecycle, including the immutable composition;
- Option and Option Value identity and lifecycle;
- optionally localized Product, Option, and Option Value Content;
- Product Media Roles and Product/Variant Media Assignments, including primary and ordering state.

These are package-owned statuses and assignments of the kind §15 names, so the capability cannot be classified Not Applicable. The persistence engine is not the justification.

### Read Contracts

The Host must be able to understand and manage this state without querying Product tables or reimplementing Product invariants. The approved architecture (§12, §13) establishes two distinct use cases, so the contracts stay separate. They are PHP-level, framework-agnostic, typed, and read-only. Exact names and signatures are owned by the implementation and recorded here when they exist.

| Use case | What it covers | Visibility boundary |
|---|---|---|
| **Management reads** | Listing and search of Products, Variants, Options, Option Values, Content, Media Roles, and Media Assignments, with explicit status and deleted-state filters. | Can include `inactive` and soft-deleted records, because the Host needs to administer them. Unpaginated lists return at most 100 records. Large lists use the shared `maatify/persistence` pagination (`PageRequest`/`PageResult`), not a package-local engine. |
| **Consumer reads** | Variant listing for a Product, Variant composition resolution (§5.5), exact Content reads, and exact-scope media reads. | Return only records valid under the visibility invariants: an active, non-deleted Product with structurally valid, active, non-deleted Variants and Options. A Variant appears only if every Option Value in its composition is active and not soft-deleted, belongs to the selected Option and to that Product, and the composition covers exactly the Product's active Options, one value per active Option with none missing, extra, or invalid (§5.5, §6.2, §6.3). A simple Product's single empty-composition Variant is valid because it has no active Options. An inactive or deleted ancestor suppresses its children without changing them. Consumer reads cannot request deleted records or bypass these rules. |

Both use cases keep the architecture's read semantics:

- Content reads are exact. The typed criteria distinguish an exact language, unlocalized-only (`language_code IS NULL`), and all languages (management only). There is no implicit fallback to `NULL` or another language.
- Media reads use the exact target, role, language, and platform scope, where `NULL` means exactly `NULL`.
- Lists are ordered deterministically (`display_order, id`) where ordering applies. `Product` itself has no `display_order` (`DEC-006`).
- Composition resolution is read-only. It never creates or reactivates a Variant and never invents a default.

### Domain Boundaries and Non-Goals

- Reads cover only data and meanings Product owns. They never join Host, Category, Pricing, Inventory, Supplier, Order, Customer, or Media storage tables, and they never use another package's internal storage.
- Product queries do not claim a Product is "in stock" or "sellable", because Product reads neither Inventory nor Pricing (architecture §12, §15). Sellability is derived by the Catalog/Host layer.
- Cross-package and Host-specific aggregation belongs to the Host or Project-Aware scope.
- Product adds no HTTP routes, controllers, middleware, permissions, UI dashboards, exports, or Host actor/name resolution.
- No generic `Dashboard`, `ReportService`, or metrics abstraction is part of the package. None is added for compliance.

### Intentionally Unsupported Reporting Dimensions in V1

The approved architecture establishes no meaning for any of the following, so Product V1 does not provide them. Adding one needs an Owner-approved change to the architecture contract.

- counts, aggregates, grouped results, or status summaries beyond the pagination metadata supplied by `maatify/persistence`;
- time-window summaries and audit or history reads, beyond the application-managed `created_at`, `updated_at`, and `deleted_at` fields on the records themselves;
- price, availability, stock, supplier, order, or sellability reporting, which belongs to other domains;
- Category-based grouping or ordering, because Product-to-Category mapping is outside the package (`DEC-006`).

### Verification Obligation

Any read result that depends on persisted state, including exact language and scope matching, visibility suppression, deleted-state filtering, ordering, and pagination, requires Integration evidence on the supported persistence engine (MySQL `8.0.16+`). Unit mocks alone are not sufficient (§15; architecture §17).

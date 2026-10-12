# Product Package Reference

**Package:** `maatify/php-product`
**Status:** Pre-implementation. This is the canonical root Package Reference path required by the Package Building Standard (`/{PACKAGE_NAME}_PACKAGE_REFERENCE.md`). It records the **Public API contract inventory** derived from the Owner-approved architecture. No PHP or SQL exists yet, so nothing here is an implemented or released contract. Later implementation Work Units update this file in place when the real signatures exist.
**Architecture contract:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md), Owner-approved under [`DEC-007`](docs/decisions/DEC-007-product-v1-implementation-start-authorization.md)
**Binding decisions:** [`DEC-001`](docs/decisions/DEC-001-product-variant-pricing.md) to [`DEC-007`](docs/decisions/DEC-007-product-v1-implementation-start-authorization.md), all `ACTIVE`. Index: [`DECISIONS_INDEX.md`](docs/decisions/DECISIONS_INDEX.md)

## 1. How to Read This Inventory

This revision inventories the public contract. It does not design it. Four conventions keep the two apart:

- **Basis.** Every rule cites the architecture section (`A§`) or decision (`DEC-`) that establishes it. A rule marked **(derived)** is not stated verbatim. It follows from the cited invariants, and a reviewer should confirm it.
- **Type names.** The approved sources fix almost no PHP type names. The few they mention are illustrative ("for example"), so they are not binding. Contracts are therefore identified by a **Capability ID** (`PRD-01`, `VAR-03`, and so on). Final names are chosen when the type is written, under the suffix rules of Package Building §4 (`Interface`, `Enum`, `DTO`, `Exception`) and recorded here (`GAP-02`).
- **Gaps.** Anything the governing sources leave open is recorded in [§12](#12-pre-implementation-contract-gaps) as `GAP-nn` and referenced where it matters. A gap is not an approved decision. Implementation of an affected contract must not proceed on an assumption.
- **Failures.** Failure classes (`FC-*`) are defined in [§3.8](#38-failure-classes). Exact exception class names are not fixed by the sources.

### 1.1 Illustrative names mentioned by the approved architecture

These appear in A§5.5, A§12, and A§13 as examples only. They are not binding and are not reserved.

| Name | Where | Used for |
|---|---|---|
| `VariantCompositionCriteria` | A§5.5 | Typed selection input for composition resolution |
| `OptionValueSelection` | A§5.5 | One `(option_id, option_value_id)` pair |
| `ResolveVariantByCompositionQueryInterface` | A§13 | Resolution query contract |
| `VariantNotFoundException` | A§5.5 | Failure when no active Variant matches |
| `withExactLanguage(string)`, `withUnlocalizedOnly()`, `withAllLanguages()` | A§12 | Content language selectors |

## 2. Package Identity and Boundaries

`php-product` owns the product domain and the identity of each sellable unit (A§1). It is standalone, host-agnostic, and framework-agnostic.

| Owned by the package | Source |
|---|---|
| Product identity and lifecycle | A§3.1, A§4.1 |
| Variant identity, lifecycle, and optional SKU/barcode | A§3.1, A§4.2 |
| Variant-defining Options and Option Values | A§3.1, A§5 |
| Immutable Variant-to-Option-Value composition | A§3.1, A§5.3 |
| Optionally localized Content for Product, Option, and Option Value | A§3.1, DEC-004 |
| Product Media Roles and Product/Variant Media Assignments | A§3.1, A§9, DEC-005 |

Out of scope, and never added for compliance: price, currency, inventory, stock, supplier, Category mapping, orders, customer account types, URL slugs, HTTP/routes/UI, upload or storage of media, and any `catalog_id`/tenant scope (A§3.2, A§10, DEC-001, DEC-002, DEC-003, DEC-006). The Variant is the only reference unit Pricing, Inventory, Supplier, and orders use (DEC-001). Sellability is derived by the Catalog/Host layer, never by this package (A§15).

### 2.1 Shared packages the contract relies on

| Package | Capability the contract uses | Evidence read in WU-01 |
|---|---|---|
| `maatify/exceptions` | Exception hierarchy. Families exist for `Validation`, `NotFound`, `Conflict`, `BusinessRule`, and `System`. | Repository tree read at its current HEAD (not a pinned commit) |
| `maatify/shared-common` | `ClockInterface` | Repository tree read at its current HEAD |
| `maatify/persistence` | `PageRequest`, `PageResult`, `ScopedOrderingManager`, `TransactionRunnerInterface`, `SavepointTransactionRunnerInterface`. Pagination was introduced in v1.1.0. | `PERSISTENCE_PACKAGE_REFERENCE.md` at the commit pinned by Package Building §16 |

Version constraints are owned by `composer.json` (Composer Package Standard) and are not stated here. They are chosen when `composer.json` is created.

## 3. Cross-Cutting Contract

### 3.1 Identifiers and value rules

| Field | Rules | Basis |
|---|---|---|
| Package-owned `id` (Product, Variant, Option, Option Value, Content, Role, Assignment) | Immutable. Persisted `BIGINT UNSIGNED`. | A§11 |
| Product `code` | Required. At most 100 characters. Empty and whitespace-only values are rejected. Never silently trimmed or rewritten (whether a value with surrounding whitespace that is not whitespace-only is rejected or kept verbatim is open, `GAP-13`). Immutable. Unique in the package namespace across all history, including soft-deleted rows. Case-insensitive under `utf8mb4_unicode_ci`. | A§4.1, DEC-003 |
| Option `code` | Same value rules. Unique within its Product across history. | A§5.2 |
| Option Value `code` | Same value rules. Unique within its Option across history. | A§5.2 |
| Variant `sku`, `barcode` | Each is `NULL` or a non-empty, non-whitespace-only string of at most 128 characters. Unique in the package namespace while assigned (collation `utf8mb4_unicode_ci`). A soft-deleted Variant keeps its values reserved. Removing or replacing a value releases the old one. | A§4.2 |
| Media Role `role_key` | Required. At most 100 characters. Non-empty after trimming. Immutable. Unique across history under `utf8mb4_unicode_ci`. | A§9.2 |
| `language_code` (all Content, and Media Assignments) | `NULL` or a non-empty, non-whitespace string of at most 16 characters. `NULL` is exactly the unlocalized scope, and is distinct from an empty string. The package does no normalization or semantic validation. Case variants compare equal under the collation. | A§4.1, A§6.4, DEC-004 |
| `platform` (Media Assignments only) | `NULL` or a non-empty, non-whitespace string of at most 255 characters. No hard-coded platform values. Core Content has no `platform` (DEC-004). | A§9.3 |
| `media_asset_id` | Host-owned, external. Persisted `BIGINT UNSIGNED` with `> 0`. Exposed as PHP `int`. Command input is `int` or `string`. An `int` must be at least 1. A `string` must match `^[1-9][0-9]*\z` and must not exceed `PHP_INT_MAX`, checked before the cast. No foreign key, no join, no existence check. | A§9.1, DEC-005 |
| `role_id`, `variant_id` (Assignments) | Positive when non-NULL. | A§9.3 |
| `display_order` | Positive. Never accepted by Create or Update Commands. Changed only by a dedicated typed ordering command (see [§3.4](#34-ordering)). | A§11.3, Package Building §16 |

The collation consequences, the PHP definition of "whitespace", the character-versus-byte length unit, and the input type of package-owned IDs are not fully specified (`GAP-13`, `GAP-23`, `GAP-24`).

### 3.2 Status, soft deletion, and restore

| Entity | Has `status` | Soft-deletable | Default at creation |
|---|---|---|---|
| Product | yes | yes | `inactive` (A§6.1) |
| Variant | yes | yes | The initial empty-composition Variant is `active`. Non-empty Variants are created only by reconfiguration as active members of the validated set (A§4.5). |
| Option | yes | yes | `inactive` |
| Option Value | yes | yes | `inactive` |
| Media Role | yes | yes | `active` |
| Content (three tables) | no | yes | n/a |
| Media Assignment | no (`is_primary` only) | yes | not primary (see `GAP-14`, `GAP-18`) |
| Variant composition pair | no | no. It has no independent update, delete, or restore lifecycle (A§11.3). | n/a |

- Status values are exactly lowercase `active` and `inactive`. Public mutations take a typed enum. The database must reject other casings (A§6.1, A§11.3).
- `status` and `deleted_at` are independent dimensions. `deleted_at IS NULL` means not soft-deleted. Runtime hard delete is not used (A§6.1).
- **No cascade.** Deactivating or deleting a parent never changes a child's `status` or `deleted_at`. A parent that is inactive or deleted only suppresses the child's consumer visibility (A§6.1).
- **Restore** reuses the original identity and preserves the saved `status`. Restoring an `inactive` row never activates it. Restore must revalidate both entity-level and aggregate invariants, and is rejected if they fail (A§6.1, A§6.5).
- **Re-adding a soft-deleted identity** must never insert a second row (A§6.1). The two readings in the sources differ for Content (reject, restore explicitly) and for Media Assignments (restore or reuse). See `GAP-09`.
- A status change on a soft-deleted row is "not a substitute for restore" (A§6.1). Whether it is rejected or merely discouraged is unresolved (`GAP-10`).
- No identity-correction API exists in V1. `id` and `code` are never updated (A§4.1). Corrections use an explicit migration or replacement process outside this package.

### 3.3 Timestamps and the clock

`created_at`, `updated_at`, and `deleted_at` are assigned by the application from the Host-injected `ClockInterface` (`maatify/shared-common`). There is no database timestamp default or `ON UPDATE`. The package performs no timezone normalization and must not claim UTC (A§10, A§11.3). The public timestamp representation must be documented explicitly (A§11.3) and is not yet decided (`GAP-20`).

### 3.4 Ordering

Entities with persistent ordering: Option (scope: Product), Option Value (scope: Option), Variant (scope: Product), Media Assignment (scope: target plus role, language, and platform). `Product` has none (DEC-006).

- Positions are positive and managed by the shared `maatify/persistence` Ordering API (`ScopedOrderingManager`), not by database defaults and not by a package-local engine (A§11.3, Package Building §11, §16).
- Create Commands do not accept a position. A new row gets its position through the Ordering API.
- Movement is a dedicated typed ordering command per entity. The repository returns `bool`, and the service turns `false` into a not-found failure (Package Building §10, §16).
- A target above the current maximum is clamped to the maximum by the shared API. A non-positive target is rejected before clamping (persistence reference). The Command must reject a non-positive position itself (`FC-VAL`).
- All lists end with `display_order, id` (A§12).
- Open: the position given to a restored row (`GAP-11`), and how a multi-dimensional Media Assignment scope maps onto the shared API's single scope column (`GAP-12`).

### 3.5 Pagination and list bounds

- Every **unpaginated** consumer or management list returns at most 100 records (A§12).
- Large management lists use `maatify/persistence` `PageRequest` and `PageResult`. A package-local pagination engine is forbidden (A§12, Package Building §11).
- The package owns the query semantics: searchable fields, filters, matching, count alignment, and the result contract. The shared package owns only the pagination mechanics (Package Building §11).
- Which lists are paginated, what happens when an unpaginated list would exceed 100, the page-size limit, search semantics, and the default order of lists that have no `display_order` (Product, Role, Content) are not specified (`GAP-04`, `GAP-05`).

### 3.6 Exact scope and `NULL`

`NULL` is an exact value, never a wildcard, fallback, "all", or "default" (A§9.3, A§12, Package Building §24).

- **Content** language: three mutually exclusive selectors (exact language, unlocalized only, all languages). "No language filter" and "`NULL`" must not be the same parameter (A§12).
- **Media** role, language, and platform: each dimension is matched exactly. `NULL` means exactly `NULL`. Non-`NULL` strings compare under `utf8mb4_unicode_ci`. There is no fallback (A§9.3).
- No Product-level fallback for Variant-level media, and no Variant-level substitution for Product-level media (A§9.4).
- Management criteria for media dimensions (omitted versus `NULL`) are not yet specified (`GAP-19`).

### 3.7 Atomicity, locking, and transactions

- Every multi-table Product mutation is atomic locally (A§14). The package never claims cross-domain atomicity. Pricing, Inventory, Supplier, and fulfilment reconciliation belongs to the Host/Catalog coordinator (A§5.4, A§14).
- Any mutation that can affect Product content validity, Option/Value activation, Variant composition or status, or configuration serializes on the **Product row** before it reads current state (A§6.4, A§11.6). Read-before-write without locking and database uniqueness is not sufficient.
- Media primary replacement locks the owning target scope and uses the database unique key as the final guard (A§9.4, A§11.4). Lock order across Product, Variant, Role, and Assignment rows must be stable (A§11.6).
- The package must not commit or roll back an outer transaction it does not own (A§11.6, A§14). It cooperates with the shared transaction contract. No automatic retry is claimed, because the sources permit retry only where the shared contract explicitly allows it (A§11.6).
- Duplicate identity is blocked by database-enforced keys. Application pre-checks are advisory (A§9.4, A§11.1).
- Which runner (`TransactionRunnerInterface` or `SavepointTransactionRunnerInterface`) is used, and what "reject the whole operation" means when a Host transaction is already open, are unresolved (`GAP-21`).

### 3.8 Failure classes

The package exposes one marker interface, expected at `Maatify\Product\Exception\ProductExceptionInterface` (Package Building §7). Every package-defined exception implements it and uses a `maatify/exceptions` family. The namespace is derived from Package Building §4 and the sibling `maatify/php-category`, because no `composer.json` exists yet (`GAP-02`). Exact exception class names are not fixed.

| Class | Family | Meaning |
|---|---|---|
| `FC-VAL` | Validation | An input violates the contract at construction. Examples: empty or whitespace-only code, overlength value, malformed language code or `media_asset_id`, non-positive position, unknown status, duplicate Option in a selection. |
| `FC-NF` | NotFound | The target identity does not exist. Whether an invisible or soft-deleted record reads as not found is part of `GAP-07`. |
| `FC-CONF` | Conflict | An identity is already taken. This covers duplicate code, role key, SKU, barcode, content identity, and Media Assignment identity. It also covers a composition that already exists in history (the conflict must identify the existing Variant, A§5.3), and restore-as-primary when a primary already exists. |
| `FC-INV` | BusinessRule | The operation would leave an invalid configuration, or a precondition fails. See [§4](#4-structural-invariants). |
| `FC-INT` | System | Persisted state is inconsistent. Examples: a composition digest matches but the pairs differ (A§5.3), or the expected original empty-composition Variant is missing or invalid during a configurable-to-simple transition (A§4.5). |

Infrastructure failures (`PDOException` and similar) are not defined here. Whether the package wraps or propagates them must be documented (Package Building §7), and the sources do not decide it (`GAP-22`). SQLSTATE class `23` must never be mapped blindly to a conflict. MySQL driver code `1062` supports a duplicate classification only with index-level evidence (Package Building §7).

### 3.9 Shared enums and value objects

| Contract | Kind | Basis |
|---|---|---|
| Status | Enum (`active`, `inactive`) | A§6.1 |
| Management status filter and deleted-state filter | Enum or equivalent | A§12. Value domains and defaults are undefined (`GAP-03`). |
| Content language selector | Criteria selector (three states) | A§12 |
| Media dimension selector | Criteria selector | `GAP-19` |
| Option/Value selection pair | Value object (`option_id`, `option_value_id`, both positive) | A§5.5 |
| Composition fingerprint | Internal only, not a consumer-facing identifier | A§5.3 |
| `media_asset_id` | Validated at the Command boundary | A§9.1, DEC-005 |

Public contracts never expose SQL rows or associative arrays as domain contracts (A§2 principle 9, A§13).

## 4. Structural Invariants

These are postconditions of every committed mutation, including while a Product is `inactive`. Inactivity is not permission to commit a broken graph (A§6.2, A§6.5).

| ID | Invariant | Basis |
|---|---|---|
| `INV-01` | A Product is exactly one of two forms. **Simple:** no active Options and exactly one active, non-deleted, empty-composition Variant, with no active non-empty Variant. **Configurable:** at least one active Option and at least one active, non-deleted Variant. | A§4.3, A§4.4, A§6.2 |
| `INV-02` | Every active Variant has exactly one active Value for every active Option, no Value of an inactive or deleted Option, and no missing or extra Option. | A§4.4, A§6.3 |
| `INV-03` | Each selected Option and Value belongs to the same Product. Each Value belongs to its selected Option. This is enforced by composite foreign keys, not only by application code. | A§4.4, A§11.1 |
| `INV-04` | Every active Option has at least one active, non-deleted Value. Every active Value of an active Option appears in at least one active valid Variant. | A§6.2 |
| `INV-05` | Every active Option and every active Value of an active Option has a non-empty label in at least one non-deleted Content record. | A§5.4, A§6.3 |
| `INV-06` | While `Product.status = active`, the Product has at least one non-deleted Product Content record with a non-empty `name`. | A§6.2, A§6.5 |
| `INV-07` | No mutation may deactivate or delete the last valid active Variant, remove the last usable Product name from an active Product, or remove a Value needed by an active Variant, unless the same transaction completes a valid reconfiguration. | A§6.5 |
| `INV-08` | A composition fingerprint is unique per Product across all Variant history (active, inactive, soft-deleted). A match must be confirmed against the actual pairs. | A§5.3, A§11.1 |
| `INV-09` | A historical inactive Variant cannot be reactivated until its full composition satisfies `INV-02` to `INV-05` against the current configuration. | A§4.5, A§6.3 |
| `INV-10` | A simple Product always retains its original empty-composition Variant identity. Reconfiguration never creates a second empty-composition Variant. | A§4.5, A§6.5 |
| `INV-11` | Product activation requires `INV-06` and a structurally valid configuration. It never depends on price, stock, supplier, or account type. | A§6.2 |
| `INV-12` | The package does not generate the Cartesian product of Option Values. The caller supplies the combinations that are offered. | A§5.4 |

### 4.1 Composition fingerprint (informative)

The algorithm is normative in A§5.3. The vectors below were computed during WU-01 with SHA-256 over the exact UTF-8 string, and are provided to help later tests.

| Composition (sorted by numeric `option_id`) | Canonical string | SHA-256 (lowercase hex) |
|---|---|---|
| empty | `v1` | `3bfc269594ef649228e9a74bab00f042efc91d5acc6fbee31a382e80d42388fe` |
| `12=58`, `13=70` | `v1\|12=58\|13=70` | `496340f00cb8b95f0534b757625de9b9bd38d6bcd8173b49dcabaa3dcf229d2c` |
| `9=1`, `10=2` (numeric, not lexicographic) | `v1\|9=1\|10=2` | `d855b95fc8dfd49bc8b26ac1b606737bf38a2bce6c161f3d0f54ffcf5b24794e` |

Input order must not change the result: `(10=2, 9=1)` sorts to the third row.

## 5. Public API Inventory

Capability IDs are stable references. "Kind" uses the Package Building responsibility vocabulary. Each row is a Management or Consumer contract as noted. Inputs and outputs are logical, not final signatures.

### 5.1 Product

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `PRD-01` | Command | Create a Product and its single empty-composition Variant in one local atomic operation. Product starts `inactive`. The Variant starts `active`. | `code` (and nothing else is established, `GAP-14`) | Typed result carrying both identities (A§4.3) | `FC-VAL`, `FC-CONF` |
| `PRD-02` | Command | Change Product status. Activation needs `INV-06`, a valid configuration, and a non-deleted row. | product id, status enum | none or updated state | `FC-NF`, `FC-INV` |
| `PRD-03` | Command | Soft-delete a Product. Children are not changed. | product id | none | `FC-NF` |
| `PRD-04` | Command | Restore a Product. Saved status is preserved, and invariants are revalidated. | product id | none | `FC-NF`, `FC-INV` |
| `PRD-R1` | Management read | Get one Product. List and search Products with explicit status and deleted-state filters. Unpaginated lists return at most 100. Large lists use `PageRequest`/`PageResult`. | criteria | DTO, or page of DTOs | `FC-NF` for a single get |
| `PRD-R2` | Consumer read | A Product read for consumers is not enumerated by the sources. | | | `GAP-06` |

Rules and edge cases:

- The initial Variant is the only Variant that may exist outside reconfiguration (A§4.5). Creating a Product never activates it, even though its Variant is active.
- `code` is never silently trimmed or rewritten. Case-only differences are not a new code. Equality follows `utf8mb4_unicode_ci` (`GAP-23`). Handling of surrounding whitespace on an otherwise valid code is open (`GAP-13`).
- A Product has no mutable business field other than `status` and lifecycle. There is no slug, price, or `display_order` (A§4.1, DEC-006). So no generic "update Product" command exists in V1 **(derived, `GAP-17`)**.
- Deactivating or soft-deleting a Product never changes its Variants, Options, Values, Content, or media (A§6.1, A§17).
- Activating an already active Product, or deactivating an inactive one, is not specified (`GAP-14`).

### 5.2 Variant

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `VAR-01` | Command | Update SKU and/or barcode with collision validation. The Variant `id` never changes. | variant id, optional new `sku`, optional new `barcode` | none | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `VAR-02` | Command | Change Variant status. Activation only when the full composition satisfies current invariants (`INV-09`). Deactivation is rejected for the last valid active Variant (`INV-07`). | variant id, status enum | none | `FC-NF`, `FC-INV` |
| `VAR-03` | Command | Soft-delete a Variant. Rejected for the last valid active Variant. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-04` | Command | Restore a Variant. Saved status is preserved. Invariants and fingerprint uniqueness are revalidated. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-05` | Command | Move a Variant within its Product's ordering scope. | variant id, positive position | none | `FC-NF`, `FC-VAL` |
| `VAR-R1` | Management read | Get a Variant. List Variants of a Product with status and deleted-state filters, ordered `display_order, id`. | criteria | DTO, or list/page of DTOs | `FC-NF` |
| `VAR-R2` | Consumer read | List the Variants of a Product that are consumer-visible (see below). | product id | list of DTOs, at most 100 | `FC-NF` (`GAP-07`) |
| `VAR-R3` | Consumer read | Resolve a composition to one Variant. See `RES-01` in [§5.8](#58-cross-family-contracts). | | | |

Rules and edge cases:

- A Variant belongs to exactly one Product. It carries no price, quantity, or account-type field (DEC-001, DEC-002).
- Non-empty Variants are created only by reconfiguration (`CFG-01`). There is no standalone Create Variant command **(A§4.5)**.
- Composition is immutable after creation. There is no update command for it, and no API accepts a changed composition (A§5.3).
- A change of composition means a new Variant identity and deactivation of the old one. The old identity is never repurposed (A§5.3).
- **SKU/barcode edge cases.** Each is `NULL` or a non-empty string, and an empty string is not stored. A soft-deleted Variant still reserves its values. Replacing or clearing releases the old value. Case-only differences collide. The Command must distinguish "leave unchanged", "set", and "clear to NULL" for each of the two fields (`GAP-14`).
- **Consumer visibility of a Variant:** the Product is active and not deleted. The Variant is active, not deleted, and structurally valid. Every Option Value in its composition is active, not deleted, belongs to the selected Option and to the Product, and the composition covers exactly the Product's active Options. A simple Product's single empty-composition Variant is valid because it has no active Options. Every active Value must be represented by at least one visible Variant (A§12, A§6.2).
- Management reads can include inactive and soft-deleted Variants. Consumer reads cannot request deleted records.
- The contents of the consumer Variant DTO (for example whether the composition pairs are exposed) are not specified (`GAP-06`).

### 5.3 Option

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OPT-01` | Command | Create an Option under a Product. Created `inactive`. Position comes from the Ordering API. | product id, `code` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `OPT-02` | Command | Change Option status. Rejected if the result would break an active configuration unless done by reconfiguration. | option id, status enum | none | `FC-NF`, `FC-INV` |
| `OPT-03` | Command | Soft-delete an Option. Same constraint as `OPT-02`. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-04` | Command | Restore an Option. Saved status is preserved and invariants are revalidated. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-05` | Command | Move an Option within its Product's ordering scope. | option id, positive position | none | `FC-NF`, `FC-VAL` |
| `OPT-R1` | Management read | Get an Option. List Options of a Product with status and deleted-state filters, ordered `display_order, id`. | criteria | DTO, or list/page | `FC-NF` |
| `OPT-R2` | Consumer read | Selectable Options of an active, visible Product. Not enumerated by the sources. | | | `GAP-06` |

Rules and edge cases:

- The Option `code` is immutable and stays reserved after soft deletion. An Option has no mutable business field besides `status` and lifecycle, so no generic update command exists **(derived, `GAP-17`)**.
- V1 Options are always Variant-defining and required for every active Variant while active. Optional per-Variant Options are not supported (A§5.1). Descriptive attributes are not Options.
- **Effective status behavior (derived, `GAP-16`).** `INV-01`, `INV-02`, and `INV-07` together mean any standalone change of an Option between `active` and `inactive` leaves an invalid configuration. This is because an active Product configuration always has at least one active Variant. So such a change succeeds only inside reconfiguration. A same-status request is a no-op, and its handling is unspecified.
- An Option can be activated only if it has at least one active, non-deleted Value (`INV-04`) and a label (`INV-05`).

### 5.4 Option Value

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OVL-01` | Command | Create a Value under an Option. Created `inactive`. Position within the Option scope. | option id, `code` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `OVL-02` | Command | Change Value status. Same constraint as `OPT-02`. | value id, status enum | none | `FC-NF`, `FC-INV` |
| `OVL-03` | Command | Soft-delete a Value. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-04` | Command | Restore a Value. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-05` | Command | Move a Value within its Option's ordering scope. | value id, positive position | none | `FC-NF`, `FC-VAL` |
| `OVL-R1` | Management read | Get a Value. List Values of an Option with status and deleted-state filters, ordered `display_order, id`. | criteria | DTO, or list/page | `FC-NF` |
| `OVL-R2` | Consumer read | Selectable Values of a visible Option. Not enumerated by the sources. | | | `GAP-06` |

Rules and edge cases:

- The Value `code` is immutable, unique within its Option across history. The Value belongs to the Product through its Option.
- New Values are created `inactive` so codes and labels can be prepared without changing the current configuration (A§5.4).
- **Effective status behavior (derived, `GAP-16`).** A standalone status change is meaningful only for a Value of an **inactive** Option. For an active Option, `INV-04` and `INV-02` make a change invalid. Deactivating a used Value strands an active Variant, and activating an unused Value leaves it in no Variant.
- A Value is not selectable while its Option is inactive (A§4.5).

### 5.5 Content (Product, Option, Option Value)

The three Content tables share one contract. The owner is a Product, an Option, or an Option Value.

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `CNT-01` | Command | Add a Content record for `(owner, language_code)`, including the `NULL` identity. | owner id, `language_code` or `NULL`, content fields | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `CNT-02` | Command | Update content fields by Content record id. The owner and language never change. | content id, fields | none | `FC-NF`, `FC-VAL` |
| `CNT-03` | Command | Soft-delete a Content record. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-04` | Command | Restore a Content record. This is the only way to bring back a soft-deleted identity. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-R1` | Management read | List Content of an owner with one of three selectors: exact language, unlocalized only, all languages. | owner id, selector | list of DTOs | |
| `CNT-R2` | Consumer read | Exact-language read, or exact unlocalized read. The all-languages selector is not available to consumers (A§12). | owner id, exact-language or unlocalized selector | DTO, or absence (`GAP-07`) | |

Rules and edge cases:

- **Fields.** Product Content has `name` (required, non-empty, at most 255), `short_description` (optional, at most 500), and `description` (optional text). `name` is a label, not HTML. The package stores and returns values only, and never renders or sanitizes markup (A§4.1, A§11). Option and Option Value Content have a "non-empty name/label"; their full field list and length limits are not specified (`GAP-13`).
- **Identity.** `(owner_id, language_code)` is immutable and unique across soft-deleted history, including the single `NULL` row (A§4.1, A§6.4, A§11.2).
- **Exactness.** An exact-language read for `'ar'` never returns `NULL` content or another language. An exact unlocalized read never returns a localized record. There is no implicit fallback of any kind (A§12, DEC-004).
- **Selectors.** The three selectors are distinct, mutually exclusive typed states. Omitting a language filter and passing `NULL` are different intents and must not share a parameter (A§12).
- **Language edge cases.** `'EN'` and `'en'` are the same identity under the collation. Creating the second is a `FC-CONF`. An empty or whitespace-only non-`NULL` language is a `FC-VAL`.
- **Required labels.** Removing or soft-deleting the last non-empty name of an **active** Product is rejected (`INV-06`, `FC-INV`). The same protection applies to the last label of an active Option or Value used by an active Variant (`INV-05`, **derived**).
- **Consumer visibility.** A Content record is visible only if it is not deleted and its owner chain is visible: an active, non-deleted Product, and for Option/Value Content an active, non-deleted Option (and Value).
- **Re-adding.** Creating a Content identity that exists in soft-deleted history must be rejected and an explicit restore required (A§17). `GAP-09` records that other families are worded differently.

### 5.6 Media Roles

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `MRL-01` | Command | Create a Product Media Role. Created `active`. The package ships no seeded roles. | `role_key` | typed result with the new id | `FC-VAL`, `FC-CONF` |
| `MRL-02` | Command | Change Role status. | role id, status enum | none | `FC-NF` |
| `MRL-03` | Command | Soft-delete a Role. | role id | none | `FC-NF` |
| `MRL-04` | Command | Restore a Role. Same identity, same saved status. | role id | none | `FC-NF`, `FC-INV` |
| `MRL-R1` | Management read | Get a Role. List and search Roles with status and deleted-state filters. | criteria | DTO, or page | `FC-NF` |

Rules and edge cases:

- Role IDs are Product-owned. They are never Category Image Role IDs, and the two namespaces are never mixed (A§9, DEC-005).
- A Role has no mutable business field besides `status`. The Host defines what a role means. There is no `hero` or `gallery` meaning in the package **(A§9.2; no update command, derived, `GAP-17`)**.
- A new role-scoped assignment needs an active, non-deleted Role. Existing assignments are not changed when the Role becomes inactive or deleted. They disappear from consumer reads and stay in management reads (A§9.2).
- `role_id = NULL` on an assignment is the exact generic/unclassified scope, not a "no role requested" fallback.
- No consumer read of the Role registry is enumerated. Consumers read assignments, not roles (`GAP-06`).

### 5.7 Media Assignments

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `MAS-01` | Command | Create an assignment of one external Media Asset to a Product (target `variant_id = NULL`) or to one Variant of that Product, within an exact `(role, language, platform)` scope. | product id, optional variant id, `media_asset_id`, optional role id, `language_code`, `platform` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` |
| `MAS-02` | Command | Set the primary assignment of an exact scope. Atomically clears the previous primary and sets the requested one. | assignment id | none | `FC-NF`, `FC-INV` |
| `MAS-03` | Command | Soft-delete an assignment. If it was primary, primary status is cleared and no other row is promoted. | assignment id | none | `FC-NF` |
| `MAS-04` | Command | Restore an assignment as non-primary. | assignment id | none | `FC-NF`, `FC-INV` |
| `MAS-05` | Command | Restore an assignment as primary. A separate explicit command. Fails if the exact scope already has a primary. | assignment id | none | `FC-NF`, `FC-CONF` |
| `MAS-06` | Command | Move an assignment within its exact ordering scope. | assignment id, positive position | none | `FC-NF`, `FC-VAL` |
| `MAS-R1` | Management read | List assignments of a target, with deleted-state filter, ordered `display_order, id`. | criteria | list/page of DTOs | |
| `MAS-R2` | Consumer read | Exact-scope read: target (Product or Variant) plus `role_id`, `language_code`, `platform`, each matched exactly. | exact scope | list of DTOs, at most 100 | |

Rules and edge cases:

- **Target and integrity.** A target is the Product or one Variant of that same Product. A Variant of another Product is rejected by the database through the composite key `(variant_id, product_id)`. A Product-level assignment is still anchored to an existing Product through the independent `product_id` key (A§9.3, A§11.1).
- **Identity.** `(target kind, target identity, media_asset_id, role_id, language_code, platform)` is immutable and unique across soft-deleted history. The same Media Asset may be assigned to another target or another exact scope (A§9.3).
- **Primary.** At most one active primary per exact `(target, role, language, platform)` scope. Primary is independent of `display_order`. Soft-deleting or clearing a primary never promotes another row. Restore leaves a row non-primary unless the explicit restore-as-primary command is used (A§9.4, A§11.4).
- **Concurrency.** A primary replacement is protected by transaction locking and by a database unique key that treats `NULL` as a real scope value. A plain unique key containing `NULL` is not sufficient (A§9.4, A§11.4).
- **Consumer visibility.** The Product is active and not deleted. For a Variant target, the Variant is active and not deleted. If `role_id` is non-`NULL`, the Role is active and not deleted. The assignment is not deleted. An inactive or deleted ancestor hides the assignment without changing it (A§9.3, A§12).
- **No inheritance.** A Product-level read does not return Variant-level media, and a Variant-level read does not return Product-level media (A§9.4).
- **No media check.** The package never uploads, resolves, transforms, or verifies the Media Asset (A§9.4, DEC-005).
- Whether a create can set `is_primary`, whether a standalone "clear primary" exists, whether `MAS-02` is allowed under an inactive Role or inactive target, and the management criteria are open (`GAP-12`, `GAP-18`, `GAP-19`).

### 5.8 Cross-family contracts

#### `RES-01` Variant composition resolution (Consumer read, read-only)

Resolves a shopper's option choices to one concrete `variant_id`. The Host owns presentation and when to call it. The package owns whether the selection is a real, active, valid Variant (A§5.5).

| Aspect | Contract |
|---|---|
| Input | `product_id` and a typed collection of selection pairs `(option_id, option_value_id)`. No associative array is accepted. An empty collection is valid input. |
| Order | Order-independent. Pairs are canonicalized by numeric `option_id` (A§5.3). |
| Output | The single matching active, non-deleted Variant identity (shape: `GAP-06`). |
| Rejects (`FC-VAL` or `FC-INV`, exact split `GAP-08`) | A repeated Option. An Option or Value from another Product. A Value paired with the wrong Option. An inactive or soft-deleted Option or Value. |
| Coverage | For a configurable Product, exactly one active Value for every active Option, with none missing and none extra. |
| Simple Product | An empty selection returns the single active empty-composition Variant, provided the Product and Variant are visible to consumers. A non-empty selection names Options that are not active, so it is rejected **(derived from A§5.5 items 3 and 4)**. |
| No match | A valid composition with no matching active Variant fails closed as a documented typed result or a typed exception (for example `VariantNotFoundException`). The sources allow both (`GAP-08`). |
| Never | It never creates a Variant, never reactivates a historical one, and never invents a default. A matching digest with different pairs is `FC-INT`. |

Examples of composition safety:

- A configurable Product with active Options `{color, size}` and a request carrying only `color` is rejected as partial coverage, even if exactly one Variant has that color.
- A request containing a Value of an inactive Option is rejected, even though a historical inactive Variant still contains it.
- A request equal to a historical inactive Variant's composition returns no active Variant. It does not reactivate it.

#### `CFG-01` Product reconfiguration (Management command, atomic)

The only way to change the active Option set or the active Variant set, and the only way to create non-empty Variants. It is a Product-local atomic operation, never a sequence of ordinary Option calls (A§4.5, A§5.4, A§6.5).

Required behavior (A§5.4):

1. Validate the intended new active Option/Value set. Every active Option and every active Value of an active Option needs a non-empty label (`INV-05`).
2. Validate every proposed Variant composition for exact coverage of the active Options. Reject extra inactive or deleted Options, cross-Product values, invalid Values, and duplicate compositions.
3. Create replacement Variant identities with immutable compositions.
4. Deactivate replaced Variants without deleting history. A matching historical composition is a conflict unless the command explicitly names the existing Variant to reactivate or restore.
5. Keep at least one active valid Variant.
6. Establish every Product-local invariant before the operation completes. If a complete valid replacement cannot be produced, the whole operation is rejected (`FC-INV`, `FC-CONF`).

Transitions it must support:

- **Simple to configurable.** Establish the new active Option set, create complete replacement Variants, and deactivate the empty-composition Variant in the same transaction.
- **Configurable to simple.** Deactivate every active Option and every active non-empty Variant. Reactivate or explicitly restore the original empty-composition Variant. Retain Options and Values as history. Never create a second empty-composition Variant. If the original is missing or invalid, fail closed and report why (`FC-INT`).
- **Configurable to configurable.** Replace the set, reactivating historical Variants only by explicit identity.

Boundaries:

- It never calls Pricing, Inventory, or Supplier, and never copies or transfers their records. New Variants inherit nothing (A§5.4, A§14). The Host/Catalog coordinator reconciles external mappings before it reactivates a historical Variant (A§4.5).
- Its result must let the Host see the deterministic old and new Variant identities (A§14). The exact shape is open.
- The command model (declarative target state or deltas), what happens to a currently active Variant that is omitted, whether Options/Values are only activated or can also be created inside it, and whether it may also soft-delete or restore Options and Values are not specified (`GAP-15`).

## 6. Management and Consumer Behavior

| Aspect | Management reads | Consumer reads |
|---|---|---|
| Records | Can include `inactive` and soft-deleted records, with explicit status and deleted-state filters. | Only records valid under the visibility invariants. They cannot request deleted records or bypass an inactive ancestor. |
| Ancestors | Not suppressed. | An inactive or deleted Product, Variant, Option, Value, or Role hides its children without changing them. |
| Language | Exact language, unlocalized only, or all languages. | Exact language or unlocalized only. |
| Media | Target listing across scopes (`GAP-19`). | Exact target, role, language, and platform. |
| Bounds | Unpaginated lists return at most 100. Large lists use `PageRequest`/`PageResult`. | Bounded to at most 100. |
| Fallback | None. | None. The Host owns language, platform, and role fallback. |
| Claims about sale | None. | None. The package never states that an item is "in stock" or "sellable" (A§12, A§15). |

## 7. Consumer Workflow (logical)

This is the contract path required by Package Building §25. It contains no code, because no code exists.

```text
Host input
   -> typed Command / Criteria
   -> Management or Consumer service (Product aggregate lock for mutations)
   -> shared persistence boundary (MySQL 8.0.16+, direct PDO, Ordering, Pagination, Transaction)
   -> typed result, or a documented package exception
```

Typical flows:

1. **Catalog setup.** Create a Product (`PRD-01`). Add Content (`CNT-01`) and a name. Activate it (`PRD-02`).
2. **Make it configurable.** Create Options (`OPT-01`) and Values (`OVL-01`) while inactive. Add labels. Run reconfiguration (`CFG-01`) with the offered combinations.
3. **Shopper selection.** The Host reads the visible Variants (`VAR-R2`), collects the shopper's choices, and calls resolution (`RES-01`). It gets one `variant_id`, and passes that to Pricing, Inventory, or an order.
4. **Presentation.** The Host reads exact Content (`CNT-R2`) and exact-scope media (`MAS-R2`), applying its own fallback.

## 8. Operational Read / Reporting Classification

This section records the classification required by Package Building Standard §15.

**Classification: In Scope.**

**Reason.** §15 decides applicability by what the package owns, not by whether it uses a database. `php-product` owns persisted domain state with its own lifecycle and invariants (architecture §3.1, §4–§6, §9):

- Product identity and its `active`/`inactive` status and soft-deletion state;
- Variant identity and lifecycle, including the immutable composition;
- Option and Option Value identity and lifecycle;
- optionally localized Product, Option, and Option Value Content;
- Product Media Roles and Product/Variant Media Assignments, including primary and ordering state.

These are package-owned statuses and assignments of the kind §15 names, so the capability cannot be classified Not Applicable. The persistence engine is not the justification.

### 8.1 Read Contracts

The Host must be able to understand and manage this state without querying Product tables or reimplementing Product invariants. The approved architecture (§12, §13) establishes two distinct use cases, so the contracts stay separate. They are PHP-level, framework-agnostic, typed, and read-only. The capability IDs in [§5](#5-public-api-inventory) are the inventory of these contracts. Exact names and signatures are recorded here when they exist.

| Use case | What it covers | Visibility boundary |
|---|---|---|
| **Management reads** | Listing and search of Products, Variants, Options, Option Values, Content, Media Roles, and Media Assignments, with explicit status and deleted-state filters. | Can include `inactive` and soft-deleted records, because the Host needs to administer them. Unpaginated lists return at most 100 records. Large lists use the shared `maatify/persistence` pagination (`PageRequest`/`PageResult`), not a package-local engine. |
| **Consumer reads** | Variant listing for a Product, Variant composition resolution (§5.5), exact Content reads, and exact-scope media reads. | Return only records valid under the visibility invariants: an active, non-deleted Product with structurally valid, active, non-deleted Variants and Options. A Variant appears only if every Option Value in its composition is active and not soft-deleted, belongs to the selected Option and to that Product, and the composition covers exactly the Product's active Options, one value per active Option with none missing, extra, or invalid (§5.5, §6.2, §6.3). A simple Product's single empty-composition Variant is valid because it has no active Options. An inactive or deleted ancestor suppresses its children without changing them. Consumer reads cannot request deleted records or bypass these rules. |

Both use cases keep the architecture's read semantics:

- Content reads are exact. The typed criteria distinguish an exact language, unlocalized-only (`language_code IS NULL`), and all languages (management only). There is no implicit fallback to `NULL` or another language.
- Media reads use the exact target, role, language, and platform scope, where `NULL` means exactly `NULL`.
- Lists are ordered deterministically (`display_order, id`) where ordering applies. `Product` itself has no `display_order` (`DEC-006`).
- Composition resolution is read-only. It never creates or reactivates a Variant and never invents a default.

### 8.2 Domain Boundaries and Non-Goals

- Reads cover only data and meanings Product owns. They never join Host, Category, Pricing, Inventory, Supplier, Order, Customer, or Media storage tables, and they never use another package's internal storage.
- Product queries do not claim a Product is "in stock" or "sellable", because Product reads neither Inventory nor Pricing (architecture §12, §15). Sellability is derived by the Catalog/Host layer.
- Cross-package and Host-specific aggregation belongs to the Host or Project-Aware scope.
- Product adds no HTTP routes, controllers, middleware, permissions, UI dashboards, exports, or Host actor/name resolution.
- No generic `Dashboard`, `ReportService`, or metrics abstraction is part of the package. None is added for compliance.

### 8.3 Intentionally Unsupported Reporting Dimensions in V1

The approved architecture establishes no meaning for any of the following, so Product V1 does not provide them. Adding one needs an Owner-approved change to the architecture contract.

- counts, aggregates, grouped results, or status summaries beyond the pagination metadata supplied by `maatify/persistence`;
- time-window summaries and audit or history reads, beyond the application-managed `created_at`, `updated_at`, and `deleted_at` fields on the records themselves;
- price, availability, stock, supplier, order, or sellability reporting, which belongs to other domains;
- Category-based grouping or ordering, because Product-to-Category mapping is outside the package (`DEC-006`).

### 8.4 Verification Obligation

Any read result that depends on persisted state, including exact language and scope matching, visibility suppression, deleted-state filtering, ordering, and pagination, requires Integration evidence on the supported persistence engine (MySQL `8.0.16+`). Unit mocks alone are not sufficient (§15; architecture §17).

## 9. Verification Obligations for the Contract

Beyond [§8.4](#84-verification-obligation), the architecture (A§17) requires real MySQL evidence for: composite foreign keys and candidate keys, database rejection of invalid media targets, NULL-safe content and media identity, single-primary replacement, duplicate-history rejection (including composition), and Product aggregate locking under real concurrent transactions. The Consumer Verification Harness and the persistence-conditioned Integration obligations of `std-testing`, `std-ci-workflow`, and `std-composer-package` apply to the implementing Work Units. None of this evidence exists yet.

## 10. Index of Supporting Documents

| Document | Role |
|---|---|
| [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) | Approved architecture contract. Supports this reference and is not a competing Package Reference. |
| [`docs/decisions/DECISIONS_INDEX.md`](docs/decisions/DECISIONS_INDEX.md) | Decision discovery surface (`DEC-001` to `DEC-007`). |
| [`docs/php-engineering-standards/STANDARDS_MANIFEST.md`](docs/php-engineering-standards/STANDARDS_MANIFEST.md) | Pinned standards adoption record. |

## 11. Source Topology

**Source Topology: not yet declared.** Package Building §5 requires the Package Reference to declare exactly one topology (Single Capability, Multi Capability, or Multi Domain). A§13 leaves the choice open, and the API families above (Product, Variant, Option, Option Value, Content, Media Roles, Media Assignments, plus the cross-family resolution and reconfiguration contracts) are not directory commitments. The declaration is required before the package's first Stable release (`GAP-01`). The Management/Consumer split, when it applies, stays inside each capability (Package Building §5).

## 12. Pre-Implementation Contract Gaps

Each gap is open until a decision or evidence closes it, and the closing change updates this file in place. "Needs" names the kind of resolution. It is advisory, and authority follows `std-ai-collaboration-workflow` §3.2.1: a public-contract choice belongs to the Lead and, where it changes the approved contract, the Owner. An implementer must not guess on any of these.

| ID | Gap | Sources and what is missing | Impact | Needs |
|---|---|---|---|---|
| `GAP-01` | Source topology is undeclared. | Package Building §5 requires it. A§13 leaves capability split open. | Blocks the package skeleton (WU-02). | Lead decision |
| `GAP-02` | Exact PHP type names and the root namespace are not fixed. | Illustrative names only (§1.1 of this file). `Maatify\Product` follows Package Building §4 and the sibling package, but no `composer.json` exists. | Every contract signature. | Technical decision when each type is written |
| `GAP-03` | Management filters. Value domains and defaults are undefined: deleted-state values, status values, and what an omitted filter returns. | A§12 says "explicit status and deleted-state filters" only. | Every management list. | Contract decision |
| `GAP-04` | Management search semantics. Searchable fields, matching mode, escaping, sort whitelist, count alignment. Lookup by business key (Product code, SKU, barcode, role key, Option/Value code) is unspecified. | Package Building §11 makes these package-owned. A§12 names "search" without defining it. | Management list/search contracts. | Contract decision |
| `GAP-05` | List bounding and pagination. Which lists are paginated. Behavior when an unpaginated list would exceed 100 (truncate or reject). Page-size limit versus the shared default of 200. Default order for lists with no `display_order` (Product, Role, Content). | A§12, DEC-006, persistence reference. | Every list. | Contract decision |
| `GAP-06` | The consumer read surface is incomplete. No consumer read is enumerated for the Product itself, selectable Options, selectable Option Values, or the Role registry. The consumer Variant DTO fields (for example the composition pairs) are undefined. A§4.4 and A§6.2 assume a Host that presents selectable values. | PRODUCT_PACKAGE_REFERENCE read table, A§4.4, A§6.2, A§12, Package Building §15 (Host must not reproduce invariants). | The Host cannot build selectors from the approved list alone. | Owner decision |
| `GAP-07` | Absence semantics. A single get, an exact Content read, and an exact media read can return no record. Nullable or empty result versus a not-found exception is not fixed. Only the resolution query is allowed either way. | A§12 ("returns no match"), Package Building §10, §13. | Every single-record read. | Contract decision |
| `GAP-08` | Resolution failure contract. Typed result or exception is open (A§5.5). The classes `FC-VAL` versus `FC-INV` for each rejection, and the error for a Product that is not visible, are not fixed. | A§5.5(6), A§12. | `RES-01`. | Contract decision |
| `GAP-09` | Re-adding a soft-deleted identity. Content: "reject, restore explicitly". Media Assignment and the general rule: "restores or reuses its original identity". Whether Create implicitly restores or fails with a typed conflict is unresolved for Option, Option Value, Role, Content, and Assignment. | A§6.1, A§9.3, A§12, A§17. | Create semantics of five families. | Owner decision |
| `GAP-10` | Mutations under inactive or soft-deleted parents. May Options, Values, Content, or media be added under a soft-deleted Product? Is a status change on a soft-deleted row rejected? | A§6.1 ("where allowed"). | Error semantics of every child command. | Contract decision |
| `GAP-11` | Restore and `display_order`. A restored row's position (original, or next position) and duplicate positions are unspecified. The shared Ordering API ignores soft-deleted rows. | A§9.4, A§11.3, persistence reference. | Restore of Option, Value, Variant, Assignment. | Contract decision |
| `GAP-12` | Ordering and primary scope key for Media Assignments. The shared Ordering API takes one scope column. The approved scope is a composite with `NULL`s, expressed as a generated key. Compatibility with that API, and the lock order against the Product lock, are unproven. | A§9.4, A§11.4, A§11.6, persistence reference (`ScopedOrderingConfig`). | Assignment ordering and primary. | Evidence (MySQL integration) |
| `GAP-13` | Content fields and value limits. Fields and lengths of Option and Option Value Content. The PHP definition of "whitespace" (MySQL `TRIM()` does not cover all Unicode). Character versus byte length unit. Whether a code with surrounding whitespace that is not whitespace-only is rejected or kept verbatim. | A§11 table, A§11.2. | Content Commands, all identifier Commands. | Technical decision |
| `GAP-14` | Command shapes the sources leave open: whether Product creation takes initial Content, initial SKU/barcode, or a status. Whether reconfiguration-created Variants take SKU/barcode. Whether `MAS-01` can set `is_primary`. "Leave, set, clear" for identifiers. Behavior of a same-status request. | A§4.5, A§4.2, A§9.4. | Create and update contracts. | Contract decision |
| `GAP-15` | Reconfiguration model. Declarative target state or deltas. Treatment of active Variants omitted from the set. Whether Options and Values can be created inside it. Whether it may soft-delete or restore Options and Values. Whether replacement Variants may be inactive (A§4.5 and A§6.1 differ in wording). Result shape. | A§4.5, A§5.4, A§6.1, A§14. | `CFG-01`. | Owner decision |
| `GAP-16` | Standalone Option and Value status changes. Derived: an Option status change always breaks an invariant outside reconfiguration, and a Value status change is valid only under an inactive Option. Whether these are public commands or only reconfiguration internals is not stated. | A§5.4, A§6.2, A§6.4, A§6.5 (derivation in §5.3, §5.4). | `OPT-02`, `OVL-02`. | Lead confirmation |
| `GAP-17` | No generic update commands. Derived: Product, Option, Option Value, and Role have no mutable business field. Confirm no `Update*` command is intended. | A§4.1, A§5.2, A§9.2, DEC-006. | Command inventory. | Lead confirmation |
| `GAP-18` | Primary operations not enumerated. Is "clear primary" a standalone command? Is setting primary allowed under an inactive Role, inactive Product, or soft-deleted target? | A§9.4, A§11.4. | `MAS-02`. | Contract decision |
| `GAP-19` | Media management criteria. Omitted dimension versus `NULL` versus value for role, language, platform. Whether a Role is identified by id or by key. | A§9.3, A§12. | `MAS-R1`, `MAS-R2`. | Contract decision |
| `GAP-20` | Public timestamp representation and database column type. | A§11.3 requires explicit documentation. | Every DTO with timestamps. | Technical decision |
| `GAP-21` | Transaction contract. Which shared runner is used. Whether an outer Host transaction is supported, and how "reject the whole operation" holds inside one. Lock order across Product, Option, Value, Variant, Role, and Assignment. | A§11.6, A§14, Package Building §25, persistence reference. | Every multi-table mutation. | Contract decision with Integration evidence |
| `GAP-22` | Infrastructure and shared-package exceptions. Whether `PDOException` and persistence exceptions propagate or are wrapped. Index-level evidence for mapping driver code `1062` to a conflict. | Package Building §7. | Every command. | Technical decision |
| `GAP-23` | Collation consequences for identity. Equality under `utf8mb4_unicode_ci` (including accent-insensitive and trailing-space behavior of that collation) versus the PHP pre-check, so the PHP layer does not apply a different rule. | A§4.1, A§5.2, A§9.2. | Identifier Commands and conflicts. | Evidence (MySQL integration) |
| `GAP-24` | Input type of package-owned IDs (`int` or `int\|string`) at Command boundaries. Only `media_asset_id` is fixed. If raw `int\|string` is accepted, Package Building §8 applies. | DEC-005, Package Building §8. | Every Command with an id. | Technical decision |
| `GAP-25` | Compatibility with `maatify/php-category` (Media Asset ID and content semantics) is taken from `DEC-005` and the architecture. It was not re-verified against the current Category package in WU-01. | A§9.1, A§10, DEC-005. | Cross-package alignment claim. | Evidence |

## 13. Revision Notes

The reference follows `std-documentation-lifecycle` §3 and §6. It is a current-state contract and is updated in place. It records no phase, branch, executor, or PR status.

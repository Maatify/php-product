# Product Package Reference

**Package:** `maatify/php-product`
**Status:** Implementation in progress, nothing released. This file is the canonical root Package Reference path required by the Package Building Standard (`/{PACKAGE_NAME}_PACKAGE_REFERENCE.md`). The first runtime Work Unit (Product creation) is implemented and its contract is recorded under [Implemented Public API](#implemented-public-api); the remaining capabilities are added in place by later Work Units, and nothing here is a released contract.
**Architecture contract:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md), Owner-approved under [`DEC-007`](docs/decisions/DEC-007-product-v1-implementation-start-authorization.md)

## Source Topology

```text
Source Topology: Multi Capability

Capabilities:
- Product
- Variant
- Option
- Option Value
- Image Role
- Image Assignment
```

`php-product` is one domain, so no `Domain` directory is used; `src/` holds one directory per capability, each containing only the responsibilities that capability actually owns (`Command`, `Criteria`, `DTO`, `Enum`, `Repository`, `Service`, and so on), per the Package Building Standard §5. Classes live under `src/` with the PSR-4 prefix `Maatify\Product\`.

The capability list is the one enumerated by the Owner-approved architecture (§13), and the Lead confirmed it for this package. Today `src/` contains the `Product` and `Variant` capabilities plus the package-wide `Common`, `Exception`, and `Factory` responsibilities, all introduced by the first runtime Work Unit (see [Implemented Public API](#implemented-public-api)). The remaining capabilities (`Option`, `Option Value`, `Image Role`, `Image Assignment`) get their directory only in the Work Unit that implements them; no empty or ceremonial folder is created. The architecture allows capabilities to be merged or split "if the public contract and implementation justify" it (§13), so any change to this list requires a recorded decision, and every Work Unit must place code according to this declaration.

## Implemented Public API

This is the current public runtime inventory: Product creation with its simple Variant, the Product reads, and the Variant reads. It is not a released contract. Everything under `Maatify\Product\` that is not listed here is internal.

Requirements: PHP `^8.4`, `ext-pdo`, `ext-pdo_mysql`, `ext-mbstring`, MySQL `8.0.16+` with `schema/product.sql` installed, `maatify/exceptions`, `maatify/persistence` `^1.4` (Ordering and Savepoint Transaction APIs), and `maatify/shared-common` (`ClockInterface`).

| Type | Role |
|---|---|
| `Factory\ProductFactory` | The only construction entry point: `createProductService(PDO, ClockInterface): ProductServiceInterface` and `createVariantService(PDO, ClockInterface): VariantServiceInterface`. Both reject a PDO that is not in `ERRMODE_EXCEPTION`. |
| `Product\Service\ProductServiceInterface` | `create(CreateProductCommand): CreatedProductDTO`, `getById(int $productId): ProductDTO` (consumer-visible), and `getByIdForManagement(int $productId, ProductDeletedStateEnum = NON_DELETED): ProductDTO`. |
| `Product\Command\CreateProductCommand` | Validated input: `code` only. |
| `Common\Enum\ProductDeletedStateEnum` | Soft-deletion visibility for management reads. |
| `Product\DTO\CreatedProductDTO`, `Product\DTO\ProductDTO` | Typed results; both are `JsonSerializable`. |
| `Product\Enum\ProductStatusEnum`, `Variant\Enum\VariantStatusEnum` | `active`, `inactive`. |
| `Variant\Service\VariantServiceInterface` | `getById(int $variantId): VariantDTO` (consumer-visible), `getByIdForManagement(int $variantId, ProductDeletedStateEnum = NON_DELETED): VariantDTO`, `listByProductId(int $productId, VariantVisibleListCriteriaDTO): VariantCollectionDTO` (consumer), and `listByProductIdForManagement(int $productId, VariantListCriteriaDTO): VariantCollectionDTO`. |
| `Variant\DTO\VariantDTO`, `Variant\DTO\VariantCompositionPairDTO`, `Variant\DTO\VariantCollectionDTO` | Typed results; all are `JsonSerializable`. |
| `Variant\DTO\VariantListCriteriaDTO`, `Variant\DTO\VariantVisibleListCriteriaDTO` | Bounded read criteria. |
| `Product\Exception\ProductCodeAlreadyExistsException`, `Product\Exception\ProductNotFoundException`, `Variant\Exception\VariantNotFoundException` | Named failures. |
| `Common\Exception\ProductInvalidArgumentException` | Input contract violations. |
| `Common\Exception\ProductPersistenceException` | Storage returned data that does not match the schema contract (see Failure contract). Consumers depend on the class and the `ProductExceptionInterface` marker only. The class exposes no named constructors: the package raises it through an internal factory, which is not part of this inventory. |
| `Exception\ProductExceptionInterface` | Marker implemented by every package-defined exception. |

Consumer walkthroughs are in the [Usage Guide](docs/guides/USAGE_GUIDE.md) and runnable code is in [`examples/`](examples/); neither creates a contract beyond this file.

### Behavior and guarantees

- **Create.** `create()` writes an **inactive** Product and its single **active** empty-composition Variant in one local transaction; both rows exist or neither does. The Variant fingerprint is the SHA-256 of `v1`, its `display_order` is allocated through the shared `maatify/persistence` Ordering API under the Product row lock, and it has no SKU or barcode. The result carries both identities. The operation is not idempotent. It performs no Pricing, Inventory, or other cross-domain work.
- **Code.** `CreateProductCommand::$code` must be valid UTF-8, at most 100 characters, and not empty or whitespace only (any Unicode whitespace). It is stored exactly as supplied, never trimmed. Uniqueness uses the database collation `utf8mb4_unicode_ci`, so `P-1` and `p-1` conflict, and a soft-deleted Product keeps its code reserved.
- **Read.** `getById()` is the consumer read: it returns only an active, non-deleted Product, and any other identity raises `ProductNotFoundException`. `getByIdForManagement()` ignores status and filters only by soft-deletion state (`NON_DELETED` by default, `INCLUDE_DELETED`, `DELETED_ONLY`), matching `php-category`'s read pair. `ProductDTO` timestamps are `DateTimeImmutable` values hydrated in the Host Clock timezone, with no normalization, and serialize as `DATE_ATOM`; `ProductDTO` rejects a non-positive `id`.
- **Variant reads.** `getById()` and `listByProductId()` return only consumer-visible Variants: the Variant and its Product are active and not deleted, every selected Option and Option Value is active, not deleted, and has a non-deleted label, and the composition covers exactly the Product's active Options (none missing, none extra). `getByIdForManagement()` and `listByProductIdForManagement()` ignore visibility and filter by status (`null` means any) and soft-deletion state. A missing or not-visible identity raises `VariantNotFoundException`; a list read never fails for a missing Product and returns an empty collection. Lists are ordered by `display_order, id` and bounded to at most 100 (`maxResults` 1 to 100). `VariantDTO` carries `id`, `productId`, `sku`, `barcode`, `status`, `displayOrder`, `composition` (the Option and Option Value pairs ordered by numeric Option identity, empty for a simple Product's Variant), and the three timestamps; the composition fingerprint is internal and never exposed. Every identity argument must be a positive integer, otherwise `ProductInvalidArgumentException`.
- **Identity stability.** `id`, `code`, and the other identity columns never change through the package: no write path updates them, and the schema defines no triggers. Stability is enforced by the application contract (architecture 4.1), and `tests/Unit/IdentityStabilityTest.php` fails if a package SQL statement sets an identity column or a service method replaces an identity.
- **Transactions.** The package never commits or rolls back a transaction it did not start. When the Host already has an active transaction on the same PDO connection, `create()` runs inside an operation-local savepoint in it (`PdoSavepointTransactionRunner`, `maatify/persistence` `^1.4`), so the Host still decides commit or rollback of its own transaction. Atomic composition requires every participant to use that one connection.
- **Failure contract.** A duplicate code (MySQL driver error 1062) becomes `ProductCodeAlreadyExistsException`, with the original `PDOException` as `previous`. A storage value the package cannot interpret raises the public, package-owned `ProductPersistenceException` (a `maatify/exceptions` `SystemMaatifyException` that implements `ProductExceptionInterface`): an unknown `status`, an unparseable timestamp, a column returned with an unexpected PHP type, or an `AUTO_INCREMENT` that did not yield a positive identity. It reports a broken storage contract, not a caller error, so it is not retryable by the caller, and it carries no Product data. During `create()` it is raised inside the operation's transaction or savepoint, so the failed operation is rolled back like any other failure. **Every other `PDOException` and `Throwable` propagates unchanged**; nothing is swallowed or blanket-wrapped. When the package started the transaction, it is rolled back before the exception propagates. When the Host already had a transaction on the connection, the failed operation is rolled back to its savepoint before the exception propagates: none of its writes remain in the Host transaction, so a Host that catches the exception and still commits cannot persist a Product without its Variant. The Host transaction stays active, neither committed nor rolled back by the package, and the Host's other work in it is unaffected.
- **Timestamps.** `created_at`, `updated_at`, and `deleted_at` are taken from the Host-injected `ClockInterface` and stored as the `Y-m-d H:i:s` string of that instant. Reads return `DateTimeImmutable` values hydrated in the Host Clock's timezone. The package performs no timezone normalization and makes no UTC claim.
- **Concurrency.** Product creation serializes on the database unique key for the code and on the Product row lock for the Variant. Concurrent creates of one code yield exactly one Product; concurrent creates of different codes each yield exactly one Variant. This is proven against real MySQL by `tests/Integration/Runtime/ConcurrentCreationTest.php`.

### Not yet implemented

Content, Options, Option Values, non-empty Variant composition and reconfiguration, status mutations, soft delete and restore, Image Roles and Assignments, SKU and barcode mutation, Variant composition resolution, the consumer reads of the families that are not implemented, and pagination. They follow the architecture contract in later Work Units; their approved contract is inventoried in [Approved Contract Inventory](#approved-contract-inventory).

## Approved Contract Inventory

This section inventories the approved Public API contract for every capability the Owner-approved architecture specifies, so that the Host and the implementing Work Units have one contract reference. It is derived from [`PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (`A§`) and the decisions `DEC-001` to `DEC-007`.

- **Implemented versus approved.** Only the types in [Implemented Public API](#implemented-public-api) exist. A row or rule marked **Implemented** describes that behavior; every other row is an approved contract awaiting implementation, not a released API.
- **Basis.** Every rule cites the architecture section or decision that establishes it. A rule marked **(derived)** follows from the cited invariants and should be confirmed by a reviewer.
- **Names.** Contracts are identified by a **Capability ID** (`PRD-01`, `VAR-03`, and so on). Final PHP names follow the suffix rules of Package Building §4 and the sibling convention of `php-category` (`create`, `updateStatus`, `softDelete`, `restore`, `updateDisplayOrder`, `getById`, `getByIdForManagement`). Exact signatures are recorded in the Implemented Public API when they exist.
- **Gaps.** Anything the governing sources leave open is a `GAP-nn` in [§6](#6-open-contract-gaps). A gap is not an approved decision, and an affected contract must not be implemented on an assumption.
- **Failures.** Failure classes (`FC-*`) are defined in [§1.8](#18-failure-classes).

### Illustrative names mentioned by the approved architecture

These appear in A§5.5, A§12, and A§13 as examples only. They are not binding and are not reserved.

| Name | Where | Used for |
|---|---|---|
| `VariantCompositionCriteria` | A§5.5 | Typed selection input for composition resolution |
| `OptionValueSelection` | A§5.5 | One `(option_id, option_value_id)` pair |
| `ResolveVariantByCompositionQueryInterface` | A§13 | Resolution query contract |
| `VariantNotFoundException` | A§5.5 | Failure when no active Variant matches |
| `withExactLanguage(string)`, `withUnlocalizedOnly()`, `withAllLanguages()` | A§12 | Content language selectors |

### 1. Cross-Cutting Contract

#### 1.1 Identifiers and value rules

| Field | Rules | Basis |
|---|---|---|
| Package-owned `id` (Product, Variant, Option, Option Value, Content, Role, Assignment) | Immutable. Persisted `BIGINT UNSIGNED`. | A§11 |
| Product `code` | Required. At most 100 characters. Empty and whitespace-only values are rejected. Valid UTF-8. Never trimmed or rewritten: a value with surrounding whitespace that is not whitespace-only is kept verbatim. Whitespace means any Unicode whitespace. Length counts characters. **Implemented.** Immutable. Unique in the package namespace across all history, including soft-deleted rows. Case-insensitive under `utf8mb4_unicode_ci`. | A§4.1, DEC-003 |
| Option `code` | Same value rules. Unique within its Product across history. | A§5.2 |
| Option Value `code` | Same value rules. Unique within its Option across history. | A§5.2 |
| Variant `sku`, `barcode` | Each is `NULL` or a non-empty, non-whitespace-only string of at most 128 characters. Unique in the package namespace while assigned (collation `utf8mb4_unicode_ci`). A soft-deleted Variant keeps its values reserved. Removing or replacing a value releases the old one. | A§4.2 |
| Image Role `role_key` | Required. At most 100 characters. Non-empty after trimming. Immutable. Unique across history under `utf8mb4_unicode_ci`. | A§9.2 |
| `language_code` (all Content, and Image Assignments) | `NULL` or a non-empty, non-whitespace string of at most 16 characters. `NULL` is exactly the unlocalized scope, and is distinct from an empty string. The package does no normalization or semantic validation. Case variants compare equal under the collation. | A§4.1, A§6.4, DEC-004 |
| `platform` (Image Assignments only) | `NULL` or a non-empty, non-whitespace string of at most 255 characters. No hard-coded platform values. Core Content has no `platform` (DEC-004). | A§9.3 |
| `media_asset_id` | Host-owned, external. Persisted `BIGINT UNSIGNED` with `> 0`. Exposed as PHP `int`. Command input is `int` or `string`. An `int` must be at least 1. A `string` must match `^[1-9][0-9]*\z` and must not exceed `PHP_INT_MAX`, checked before the cast. No foreign key, no join, no existence check. | A§9.1, DEC-005 |
| `role_id`, `variant_id` (Assignments) | Positive when non-NULL. | A§9.3 |
| `display_order` | Positive. Never accepted by Create or Update Commands. Changed only by a dedicated typed ordering command (see [§1.4](#14-ordering)). | A§11.3, Package Building §16 |

For Product `code` the collation behavior, the definition of whitespace, the length unit, and the integer input type are settled and implemented. For the other identifiers they remain open (`GAP-13`, `GAP-23`, `GAP-24`).

#### 1.2 Status, soft deletion, and restore

| Entity | Has `status` | Soft-deletable | Default at creation |
|---|---|---|---|
| Product | yes | yes | `inactive` (A§6.1) |
| Variant | yes | yes | The initial empty-composition Variant is `active`. Non-empty Variants are created only by reconfiguration as active members of the validated set (A§4.5). |
| Option | yes | yes | `inactive` |
| Option Value | yes | yes | `inactive` |
| Image Role | yes | yes | `active` |
| Content (three tables) | no | yes | n/a |
| Image Assignment | no (`is_default` only) | yes | not default (see `GAP-14`, `GAP-18`) |
| Variant composition pair | no | no. It has no independent update, delete, or restore lifecycle (A§11.3). | n/a |

- Status values are exactly lowercase `active` and `inactive`. Public mutations take a typed enum. The database must reject other casings (A§6.1, A§11.3).
- `status` and `deleted_at` are independent dimensions. `deleted_at IS NULL` means not soft-deleted. Runtime hard delete is not used (A§6.1).
- **No cascade.** Deactivating or deleting a parent never changes a child's `status` or `deleted_at`. A parent that is inactive or deleted only suppresses the child's consumer visibility (A§6.1).
- **Restore** reuses the original identity and preserves the saved `status`. Restoring an `inactive` row never activates it. Restore must revalidate both entity-level and aggregate invariants, and is rejected if they fail (A§6.1, A§6.5).
- **Re-adding a soft-deleted identity** must never insert a second row (A§6.1). The two readings in the sources differ for Content (reject, restore explicitly) and for Image Assignments (restore or reuse). See `GAP-09`.
- A status change on a soft-deleted row is "not a substitute for restore" (A§6.1). Whether it is rejected or merely discouraged is unresolved (`GAP-10`).
- A soft-deleted Image Assignment is never the default: soft deletion clears the marker, restore leaves the row non-default, and the database rejects a soft-deleted default row. The retention of that database `CHECK` awaits an Owner decision.
- No identity-correction API exists in V1. `id` and `code` are never updated (A§4.1). Corrections use an explicit migration or replacement process outside this package.

#### 1.3 Timestamps and the clock

`created_at`, `updated_at`, and `deleted_at` are assigned by the application from the Host-injected `ClockInterface` (`maatify/shared-common`). There is no database timestamp default or `ON UPDATE`. The package performs no timezone normalization and must not claim UTC (A§10, A§11.3). Public timestamps are `DateTimeImmutable` values hydrated in the Host Clock timezone and serialized as `DATE_ATOM`, the same representation as `php-category`. This is implemented for `ProductDTO` and applies to every DTO.

#### 1.4 Ordering

Entities with persistent ordering: Option (scope: Product), Option Value (scope: Option), Variant (scope: Product), Image Assignment (scope: target plus role, language, and platform). `Product` has none (DEC-006).

- Positions are positive and managed by the shared `maatify/persistence` Ordering API (`ScopedOrderingManager`), not by database defaults and not by a package-local engine (A§11.3, Package Building §11, §16).
- Create Commands do not accept a position. A new row gets its position through the Ordering API.
- Movement is a dedicated typed ordering command per entity. The repository returns `bool`, and the service turns `false` into a not-found failure (Package Building §10, §16).
- A target above the current maximum is clamped to the maximum by the shared API. A non-positive target is rejected before clamping (persistence reference). The Command must reject a non-positive position itself (`FC-VAL`).
- Every list whose entity has persistent ordering ends with `display_order, id` (A§12). The default order of lists with no `display_order` (Product, Role, Content) is open (`GAP-05`).
- Open: the position given to a restored row (`GAP-11`), and how a multi-dimensional Image Assignment scope maps onto the shared API's single scope column (`GAP-12`).

#### 1.5 Pagination and list bounds

- Every **unpaginated** consumer or management list returns at most 100 records (A§12).
- Large management lists use `maatify/persistence` `PageRequest` and `PageResult`. A package-local pagination engine is forbidden (A§12, Package Building §11).
- The package owns the query semantics: searchable fields, filters, matching, count alignment, and the result contract. The shared package owns only the pagination mechanics (Package Building §11).
- Which lists are paginated, what happens when an unpaginated list would exceed 100, the page-size limit, search semantics, and the default order of lists that have no `display_order` (Product, Role, Content) are not specified (`GAP-04`, `GAP-05`).

#### 1.6 Exact scope and `NULL`

`NULL` is an exact value, never a wildcard, fallback, "all", or "default" (A§9.3, A§12, Package Building §24).

- **Content** language: three mutually exclusive selectors (exact language, unlocalized only, all languages). "No language filter" and "`NULL`" must not be the same parameter (A§12).
- **Image assignment** role, language, and platform: each dimension is matched exactly. `NULL` means exactly `NULL`. Non-`NULL` strings compare under `utf8mb4_unicode_ci`. There is no fallback (A§9.3).
- No Product-level fallback for Variant-level assignments, and no Variant-level substitution for Product-level assignments (A§9.4).
- Management criteria for image-assignment dimensions (omitted versus `NULL`) are not yet specified (`GAP-19`).

#### 1.7 Atomicity, locking, and transactions

- Every multi-table Product mutation is atomic locally (A§14). The package never claims cross-domain atomicity. Pricing, Inventory, Supplier, and fulfilment reconciliation belongs to the Host/Catalog coordinator (A§5.4, A§14).
- Any mutation that can affect Product content validity, Option/Value activation, Variant composition or status, or configuration serializes on the **Product row** before it reads current state (A§6.4, A§11.6). Read-before-write without locking and database uniqueness is not sufficient.
- Image default replacement locks the owning target scope and uses the database unique key as the final guard (A§9.4, A§11.4). Lock order across Product, Variant, Role, and Assignment rows must be stable (A§11.6).
- The package must not commit or roll back an outer transaction it does not own (A§11.6, A§14). It cooperates with the shared transaction contract. No automatic retry is claimed, because the sources permit retry only where the shared contract explicitly allows it (A§11.6).
- Duplicate identity is blocked by database-enforced keys. Application pre-checks are advisory (A§9.4, A§11.1).
- **Implemented for Product creation, and the intended mechanism for every multi-table mutation:** the operation runs through `PdoSavepointTransactionRunner` (`maatify/persistence` `^1.4`). It owns and commits a transaction when none is active. Inside a Host transaction on the same connection it runs in an operation-local savepoint, rolls back to it on failure, and never commits or rolls back the Host transaction, so a failed call leaves none of its writes behind. The lock order across Product, Option, Value, Variant, Role, and Assignment rows is still open (`GAP-21`).

#### 1.8 Failure classes

The package exposes the marker interface `Maatify\Product\Exception\ProductExceptionInterface` (Package Building §7). Every package-defined exception implements it and uses a `maatify/exceptions` family. The implemented classes are named in the table; the classes for the unimplemented capabilities are not fixed.

| Class | Family | Meaning |
|---|---|---|
| `FC-VAL` | Validation | **Implemented as `ProductInvalidArgumentException`.** An input violates the contract at construction. Examples: empty or whitespace-only code, overlength value, malformed language code or `media_asset_id`, non-positive position, unknown status, duplicate Option in a selection. |
| `FC-NF` | NotFound | **Implemented as `ProductNotFoundException`.** The target identity does not exist, or is not visible to the read that asked. An invisible or soft-deleted record reads as not found for a single get; a list returns an empty collection (see 3.9). |
| `FC-CONF` | Conflict | **Implemented for the Product code as `ProductCodeAlreadyExistsException`.** An identity is already taken. This covers duplicate code, role key, SKU, barcode, content identity, and Image Assignment identity. It also covers a composition that already exists in history (the conflict must identify the existing Variant, A§5.3). |
| `FC-INV` | BusinessRule | The operation would leave an invalid configuration, or a precondition fails. See [§2](#2-structural-invariants). |
| `FC-INT` | System | **Implemented as `ProductPersistenceException` for storage data that breaks the schema contract.** Persisted state is inconsistent. Examples: a composition digest matches but the pairs differ (A§5.3), or the expected original empty-composition Variant is missing or invalid during a configurable-to-simple transition (A§4.5). |

Infrastructure failures: an unclassified `PDOException` or other `Throwable` propagates unchanged and is never blanket-wrapped (implemented). A MySQL duplicate-key error (driver code `1062`) becomes a conflict only for a unique key the package owns and has index-level evidence for; SQLSTATE class `23` is never mapped blindly to a conflict (Package Building §7).

#### 1.9 Shared enums and value objects

| Contract | Kind | Basis |
|---|---|---|
| Status | Enum (`active`, `inactive`) | A§6.1 |
| Management deleted-state filter | `ProductDeletedStateEnum` (`NON_DELETED` by default, `INCLUDE_DELETED`, `DELETED_ONLY`). **Implemented**, same values as `php-category`. | A§12 |
| Management status filter | The family's status enum, nullable: `null` means any status. **Settled** (implemented for Variants). | A§12 |
| Content language selector | Criteria selector (three states) | A§12 |
| Image dimension selector | Criteria selector | `GAP-19` |
| Option/Value selection pair | Value object (`option_id`, `option_value_id`, both positive) | A§5.5 |
| Composition fingerprint | Internal only, not a consumer-facing identifier | A§5.3 |
| `media_asset_id` | Validated at the Command boundary | A§9.1, DEC-005 |

Public contracts never expose SQL rows or associative arrays as domain contracts (A§2 principle 9, A§13).

### 2. Structural Invariants

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

#### 2.1 Composition fingerprint (informative)

The algorithm is normative in A§5.3. The vectors below are SHA-256 over the exact UTF-8 string (recomputed and matching). The empty-composition vector is asserted by `tests/Unit/Variant/CompositionFingerprintTest.php`; the other two are provided to help the tests of the composition capability.

| Composition (sorted by numeric `option_id`) | Canonical string | SHA-256 (lowercase hex) |
|---|---|---|
| empty | `v1` | `3bfc269594ef649228e9a74bab00f042efc91d5acc6fbee31a382e80d42388fe` |
| `12=58`, `13=70` | `v1\|12=58\|13=70` | `496340f00cb8b95f0534b757625de9b9bd38d6bcd8173b49dcabaa3dcf229d2c` |
| `9=1`, `10=2` (numeric, not lexicographic) | `v1\|9=1\|10=2` | `d855b95fc8dfd49bc8b26ac1b606737bf38a2bce6c161f3d0f54ffcf5b24794e` |

Input order must not change the result: `(10=2, 9=1)` sorts to the third row.

### 3. Public API Inventory

Capability IDs are stable references. "Kind" uses the Package Building responsibility vocabulary. Each row is a Management or Consumer contract as noted. Inputs and outputs are logical, not final signatures.

#### 3.1 Product

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `PRD-01` | Command | **Implemented** (`ProductServiceInterface::create`). Create a Product and its single empty-composition Variant in one local atomic operation. Product starts `inactive`. The Variant starts `active`. | `code` only | Typed result carrying both identities (A§4.3) | `FC-VAL`, `FC-CONF` |
| `PRD-02` | Command | Change Product status. Activation needs `INV-06`, a valid configuration, and a non-deleted row. | product id, status enum | none or updated state | `FC-NF`, `FC-INV` |
| `PRD-03` | Command | Soft-delete a Product. Children are not changed. | product id | none | `FC-NF` |
| `PRD-04` | Command | Restore a Product. Saved status is preserved, and invariants are revalidated. | product id | none | `FC-NF`, `FC-INV` |
| `PRD-R1` | Management read | Get one Product: **implemented** as `getByIdForManagement(int $productId, ProductDeletedStateEnum = NON_DELETED)`. List and search Products with explicit status and deleted-state filters: not implemented. Unpaginated lists return at most 100. Large lists use `PageRequest`/`PageResult`. | criteria | DTO, or page of DTOs | `FC-NF` for a single get |
| `PRD-R2` | Consumer read | **Implemented** as `getById(int $productId)`: returns only an active, non-deleted Product. | product id | `ProductDTO` | `FC-NF` (a missing or not-visible Product raises `ProductNotFoundException`) |

Rules and edge cases:

- The initial Variant is the only Variant that may exist outside reconfiguration (A§4.5). Creating a Product never activates it, even though its Variant is active.
- `code` is never silently trimmed or rewritten. Case-only differences are not a new code. Equality follows `utf8mb4_unicode_ci`, proven for the Product code by Integration tests. Surrounding whitespace on an otherwise valid code is kept verbatim.
- A Product has no mutable business field other than `status` and lifecycle. There is no slug, price, or `display_order` (A§4.1, DEC-006). So no generic "update Product" command exists in V1 **(derived, `GAP-17`)**.
- Deactivating or soft-deleting a Product never changes its Variants, Options, Values, Content, or Image Assignments (A§6.1, A§17).
- Activating an already active Product, or deactivating an inactive one, is not specified (`GAP-14`).

#### 3.2 Variant

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `VAR-01` | Command | Update SKU and/or barcode with collision validation. The Variant `id` never changes. | variant id, optional new `sku`, optional new `barcode` | none | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `VAR-02` | Command | Change Variant status. Activation only when the full composition satisfies current invariants (`INV-09`). Deactivation is rejected for the last valid active Variant (`INV-07`). | variant id, status enum | none | `FC-NF`, `FC-INV` |
| `VAR-03` | Command | Soft-delete a Variant. Rejected for the last valid active Variant. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-04` | Command | Restore a Variant. Saved status is preserved. Invariants and fingerprint uniqueness are revalidated. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-05` | Command | Move a Variant within its Product's ordering scope. | variant id, positive position | none | `FC-NF`, `FC-VAL` |
| `VAR-R1` | Management read | **Implemented.** `getByIdForManagement(int $variantId, ProductDeletedStateEnum = NON_DELETED)` returns one Variant whatever its status. `listByProductIdForManagement(int $productId, VariantListCriteriaDTO)` lists the Variants of a Product filtered by status (`null` means any) and deleted state, ordered `display_order, id`, at most 100. | variant id, or product id and criteria | `VariantDTO`, or `VariantCollectionDTO` | `FC-NF` for a single get |
| `VAR-R2` | Consumer read | **Implemented.** `getById(int $variantId)` and `listByProductId(int $productId, VariantVisibleListCriteriaDTO)` return only consumer-visible Variants (see below), at most 100, ordered `display_order, id`. | variant id, or product id and criteria | `VariantDTO`, or `VariantCollectionDTO` | `FC-NF` for a single get; a list of a missing or not-visible Product is empty |
| `VAR-R3` | Consumer read | Resolve a composition to one Variant. See `RES-01` in [§3.8](#38-cross-family-contracts). | | | |

Rules and edge cases:

- A Variant belongs to exactly one Product. It carries no price, quantity, or account-type field (DEC-001, DEC-002).
- Non-empty Variants are created only by reconfiguration (`CFG-01`). There is no standalone Create Variant command **(A§4.5)**.
- Composition is immutable after creation. There is no update command for it, and no API accepts a changed composition (A§5.3).
- A change of composition means a new Variant identity and deactivation of the old one. The old identity is never repurposed (A§5.3).
- **SKU/barcode edge cases.** Each is `NULL` or a non-empty string, and an empty string is not stored. A soft-deleted Variant still reserves its values. Replacing or clearing releases the old value. Case-only differences collide. The Command must distinguish "leave unchanged", "set", and "clear to NULL" for each of the two fields (`GAP-14`).
- **Consumer visibility of a Variant:** the Product is active and not deleted. The Variant is active, not deleted, and structurally valid. Every Option Value in its composition is active, not deleted, belongs to the selected Option and to the Product, and the composition covers exactly the Product's active Options. A simple Product's single empty-composition Variant is valid because it has no active Options. Every active Value must be represented by at least one visible Variant (A§12, A§6.2).
- Management reads can include inactive and soft-deleted Variants. Consumer reads cannot request deleted records.
- **`VariantDTO` fields (settled).** `id`, `productId`, `sku`, `barcode`, `status`, `displayOrder`, `composition` (the `(optionId, optionValueId)` pairs ordered by numeric `optionId`, empty for a simple Product's Variant), `createdAt`, `updatedAt`, `deletedAt`. The composition is exposed because the Host needs it to map shopper choices to a Variant; the fingerprint is internal and is not exposed (A§5.3). The consumer and management reads return the same DTO.

#### 3.3 Option

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OPT-01` | Command | Create an Option under a Product. Created `inactive`. Position comes from the Ordering API. | product id, `code` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `OPT-02` | Command | Change Option status. Rejected if the result would break an active configuration unless done by reconfiguration. | option id, status enum | none | `FC-NF`, `FC-INV` |
| `OPT-03` | Command | Soft-delete an Option. Same constraint as `OPT-02`. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-04` | Command | Restore an Option. Saved status is preserved and invariants are revalidated. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-05` | Command | Move an Option within its Product's ordering scope. | option id, positive position | none | `FC-NF`, `FC-VAL` |
| `OPT-R1` | Management read | Get an Option. List Options of a Product with status and deleted-state filters, ordered `display_order, id`. | criteria | DTO, or list/page | `FC-NF` |
| `OPT-R2` | Consumer read | Selectable Options of an active, visible Product (see 3.9). | product id, bounded criteria | collection of Option DTOs | empty collection |

Rules and edge cases:

- The Option `code` is immutable and stays reserved after soft deletion. An Option has no mutable business field besides `status` and lifecycle, so no generic update command exists **(derived, `GAP-17`)**.
- V1 Options are always Variant-defining and required for every active Variant while active. Optional per-Variant Options are not supported (A§5.1). Descriptive attributes are not Options.
- **Effective status behavior (derived, `GAP-16`).** `INV-01`, `INV-02`, and `INV-07` together mean any standalone change of an Option between `active` and `inactive` leaves an invalid configuration. This is because an active Product configuration always has at least one active Variant. So such a change succeeds only inside reconfiguration. A same-status request is a no-op, and its handling is unspecified.
- An Option can be activated only if it has at least one active, non-deleted Value (`INV-04`) and a label (`INV-05`).

#### 3.4 Option Value

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OVL-01` | Command | Create a Value under an Option. Created `inactive`. Position within the Option scope. | option id, `code` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `OVL-02` | Command | Change Value status. Same constraint as `OPT-02`. | value id, status enum | none | `FC-NF`, `FC-INV` |
| `OVL-03` | Command | Soft-delete a Value. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-04` | Command | Restore a Value. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-05` | Command | Move a Value within its Option's ordering scope. | value id, positive position | none | `FC-NF`, `FC-VAL` |
| `OVL-R1` | Management read | Get a Value. List Values of an Option with status and deleted-state filters, ordered `display_order, id`. | criteria | DTO, or list/page | `FC-NF` |
| `OVL-R2` | Consumer read | Selectable Values of a visible Option (see 3.9). | option id, bounded criteria | collection of Option Value DTOs | empty collection |

Rules and edge cases:

- The Value `code` is immutable, unique within its Option across history. The Value belongs to the Product through its Option.
- New Values are created `inactive` so codes and labels can be prepared without changing the current configuration (A§5.4).
- **Effective status behavior (derived, `GAP-16`).** A standalone status change is meaningful only for a Value of an **inactive** Option. For an active Option, `INV-04` and `INV-02` make a change invalid. Deactivating a used Value strands an active Variant, and activating an unused Value leaves it in no Variant.
- A Value is not selectable while its Option is inactive (A§4.5).

#### 3.5 Content (Product, Option, Option Value)

The three Content tables share one contract. The owner is a Product, an Option, or an Option Value.

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `CNT-01` | Command | Add a Content record for `(owner, language_code)`, including the `NULL` identity. | owner id, `language_code` or `NULL`, content fields | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `CNT-02` | Command | Update content fields by Content record id. The owner and language never change. | content id, fields | none | `FC-NF`, `FC-VAL` |
| `CNT-03` | Command | Soft-delete a Content record. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-04` | Command | Restore a Content record. This is the only way to bring back a soft-deleted identity. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-R1` | Management read | List Content of an owner with one of three selectors: exact language, unlocalized only, all languages. | owner id, selector | list of DTOs | |
| `CNT-R2` | Consumer read | Exact-language read, or exact unlocalized read. The all-languages selector is not available to consumers (A§12). | owner id, exact-language or unlocalized selector | collection of zero or one DTO | empty collection |

Rules and edge cases:

- **Fields.** Product Content has `name` (required, non-empty, at most 255), `short_description` (optional, at most 500), and `description` (optional text). `name` is a label, not HTML. The package stores and returns values only, and never renders or sanitizes markup (A§4.1, A§11). Option and Option Value Content have a "non-empty name/label"; their full field list and length limits are not specified (`GAP-13`).
- **Identity.** `(owner_id, language_code)` is immutable and unique across soft-deleted history, including the single `NULL` row (A§4.1, A§6.4, A§11.2).
- **Exactness.** An exact-language read for `'ar'` never returns `NULL` content or another language. An exact unlocalized read never returns a localized record. There is no implicit fallback of any kind (A§12, DEC-004).
- **Selectors.** The three selectors are distinct, mutually exclusive typed states. Omitting a language filter and passing `NULL` are different intents and must not share a parameter (A§12).
- **Language edge cases.** `'EN'` and `'en'` are the same identity under the collation. Creating the second is a `FC-CONF`. An empty or whitespace-only non-`NULL` language is a `FC-VAL`.
- **Required labels.** Removing or soft-deleting the last non-empty name of an **active** Product is rejected (`INV-06`, `FC-INV`). The same protection applies to the last label of an active Option or Value used by an active Variant (`INV-05`, **derived**).
- **Consumer visibility.** A Content record is visible only if it is not deleted and its owner chain is visible: an active, non-deleted Product, and for Option/Value Content an active, non-deleted Option (and Value).
- **Re-adding.** Creating a Content identity that exists in soft-deleted history must be rejected and an explicit restore required (A§17). `GAP-09` records that other families are worded differently.

#### 3.6 Image Roles

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `IRL-01` | Command | Create a Product Image Role. Created `active`. The package ships no seeded roles. | `role_key` | typed result with the new id | `FC-VAL`, `FC-CONF` |
| `IRL-02` | Command | Change Role status. | role id, status enum | none | `FC-NF` |
| `IRL-03` | Command | Soft-delete a Role. | role id | none | `FC-NF` |
| `IRL-04` | Command | Restore a Role. Same identity, same saved status. | role id | none | `FC-NF`, `FC-INV` |
| `IRL-R1` | Management read | Get a Role. List and search Roles with status and deleted-state filters. | criteria | DTO, or page | `FC-NF` |

Rules and edge cases:

- Role IDs are Product-owned. They are never Category Image Role IDs, and the two namespaces are never mixed (A§9, DEC-005).
- A Role has no mutable business field besides `status`. The Host defines what a role means. There is no `hero` or `gallery` meaning in the package **(A§9.2; no update command, derived, `GAP-17`)**.
- A new role-scoped assignment needs an active, non-deleted Role. Existing assignments are not changed when the Role becomes inactive or deleted. They disappear from consumer reads and stay in management reads (A§9.2).
- `role_id = NULL` on an assignment is the exact generic/unclassified scope, not a "no role requested" fallback.
- No consumer read of the Role registry is enumerated. Consumers read assignments, not roles (see 3.9).

#### 3.7 Image Assignments

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `IAS-01` | Command | Create an assignment of one external Media Asset to a Product (target `variant_id = NULL`) or to one Variant of that Product, within an exact `(role, language, platform)` scope. | product id, optional variant id, `media_asset_id`, optional role id, `language_code`, `platform` | typed result with the new id | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` |
| `IAS-02` | Command | Set the default assignment of an exact scope: atomically clears the previous default of that scope and sets the requested one in one transaction, serialized on the target scope, with the database unique key as the final guard. Applies to a non-deleted assignment whatever the status of its Role or target; consumer reads hide it while the Role or target is inactive. | assignment id | none | `FC-NF`, `FC-INV` |
| `IAS-03` | Command | Clear the default of an exact scope: clears the marker of the given assignment, promotes no other row, and is a no-op for a non-default row. Adopted from `php-category` (`clearDefault`) as an independent, explicit operation. | assignment id | none | `FC-NF` |
| `IAS-04` | Command | Soft-delete an assignment. If it was the default, the default marker is cleared and no other row is promoted. | assignment id | none | `FC-NF` |
| `IAS-05` | Command | Restore an assignment. It is always restored as non-default. | assignment id | none | `FC-NF`, `FC-INV` |
| `IAS-06` | Command | Move an assignment within its exact ordering scope. | assignment id, positive position | none | `FC-NF`, `FC-VAL` |
| `IAS-R1` | Management read | List assignments of a target, with deleted-state filter, ordered `display_order, id`. | criteria | list/page of DTOs | |
| `IAS-R2` | Consumer read | Exact-scope read: target (Product or Variant) plus `role_id`, `language_code`, `platform`, each matched exactly. | exact scope | list of DTOs, at most 100 | |

Rules and edge cases:

- **Target and integrity.** A target is the Product or one Variant of that same Product. A Variant of another Product is rejected by the database through the composite key `(variant_id, product_id)`. A Product-level assignment is still anchored to an existing Product through the independent `product_id` key (A§9.3, A§11.1).
- **Identity.** `(target kind, target identity, media_asset_id, role_id, language_code, platform)` is immutable and unique across soft-deleted history. The same Media Asset may be assigned to another target or another exact scope (A§9.3).
- **Default.** At most one active default per exact `(target, role, language, platform)` scope. Default is independent of `display_order`. Soft-deleting or clearing a default never promotes another row. Restore always leaves a row non-default; making it default again is the explicit set-default operation (`IAS-02`), exactly as in `php-category` (A§9.4, A§11.4).
- **Concurrency.** A default replacement is protected by transaction locking and by a database unique key that treats `NULL` as a real scope value. A plain unique key containing `NULL` is not sufficient (A§9.4, A§11.4).
- **Consumer visibility.** The Product is active and not deleted. For a Variant target, the Variant is active and not deleted. If `role_id` is non-`NULL`, the Role is active and not deleted. The assignment is not deleted. An inactive or deleted ancestor hides the assignment without changing it (A§9.3, A§12).
- **No inheritance.** A Product-level read does not return Variant-level assignments, and a Variant-level read does not return Product-level assignments (A§9.4).
- **No media check.** The package never uploads, resolves, transforms, or verifies the Media Asset (A§9.4, DEC-005).
- **Default operations (settled).** `IAS-02` and `IAS-03` apply to a non-deleted assignment whatever the status of its Role or target (the sources restrict only the creation of a role-scoped assignment to an active Role, A§9.2), and an inactive Role or target only hides the assignment from consumer reads. A soft-deleted assignment cannot be made default (the database rejects it). Set and clear are independent explicit operations, as in `php-category`; creation never selects a default and `display_order` changes never alter it.
- Whether a create can set `is_default`, whether the default operations are allowed when the target Product or Variant is soft-deleted (the parent-state family of `GAP-10`), and the management criteria are open (`GAP-12`, `GAP-18`, `GAP-19`).

#### 3.8 Cross-family contracts

##### `RES-01` Variant composition resolution (Consumer read, read-only)

Resolves a shopper's option choices to one concrete `variant_id`. The Host owns presentation and when to call it. The package owns whether the selection is a real, active, valid Variant (A§5.5).

| Aspect | Contract |
|---|---|
| Input | `product_id` and a typed collection of selection pairs `(option_id, option_value_id)`. No associative array is accepted. An empty collection is valid input. |
| Order | Order-independent. Pairs are canonicalized by numeric `option_id` (A§5.3). |
| Output | The single matching active, non-deleted Variant identity (`VariantDTO`, see 3.9). |
| Rejects (`FC-VAL` or `FC-INV`, exact split `GAP-08`) | A repeated Option. An Option or Value from another Product. A Value paired with the wrong Option. An inactive or soft-deleted Option or Value. |
| Coverage | For a configurable Product, exactly one active Value for every active Option, with none missing and none extra. |
| Simple Product | An empty selection returns the single active empty-composition Variant, provided the Product and Variant are visible to consumers. A non-empty selection names Options that are not active, so it is rejected **(derived from A§5.5 items 3 and 4)**. |
| No match | A valid composition with no matching active Variant fails closed as a documented typed result or a typed exception (for example `VariantNotFoundException`). The sources allow both (`GAP-08`). |
| Never | It never creates a Variant, never reactivates a historical one, and never invents a default. A matching digest with different pairs is `FC-INT`. |

Examples of composition safety:

- A configurable Product with active Options `{color, size}` and a request carrying only `color` is rejected as partial coverage, even if exactly one Variant has that color.
- A request containing a Value of an inactive Option is rejected, even though a historical inactive Variant still contains it.
- A request equal to a historical inactive Variant's composition returns no active Variant. It does not reactivate it.

##### `CFG-01` Product reconfiguration (Management command, atomic)

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
- Its result must let the Host see the deterministic old and new Variant identities (A§14): a typed result listing the Variant identities it created, deactivated, and reactivated, and the Option and Option Value identities whose status it changed. The exact type is chosen when the operation is implemented.

**Reconfiguration model (derived from the architecture; each answer cites its source, and the points marked open need a Lead decision before implementation, `GAP-15`):**

| Question | Answer | Basis |
|---|---|---|
| Target state or deltas | **Declarative target state.** The input is the intended active Option and Value set plus the proposed Variant compositions; validation is against that whole set. | A§5.4 items 1 to 3 validate "the intended new active set" and "every proposed composition" |
| An active Variant omitted from the proposed set | It is **deactivated, never deleted**; its identity and history stay. | A§5.4 item 4 ("deactivates replaced Variants without deleting historical identities") |
| Creating Options and Values inside the operation | **Not allowed.** They are created inactive through their own commands so codes and labels can be prepared; the operation only activates or deactivates prepared ones. | A§5.4 first bullet, A§6.1 |
| Soft delete and restore inside the operation | **No soft delete.** Options and Values are retained as history and deactivated. The only restore is the explicit restore of the Product's original empty-composition Variant (and of a named historical Variant). | A§4.5, A§6.5 |
| State of replacement Variants | Non-empty Variants are created **active** as members of the validated set; a historical Variant becomes active again only when named explicitly and revalidated. | A§4.5, A§5.3, A§6.3 |
| Transaction and locking | One local transaction through the savepoint runner; the Product row is locked first, before any read of the current configuration; all invariants (`INV-01` to `INV-10`) are validated before commit and any failure rejects the whole operation. | A§6.5, A§11.6, A§14 |
| Open | The exact input and result type shapes, and the lock order after the Product row across Options, Values, and Variants. | A§11.6 asks for a stable order without fixing it |

#### 3.9 Consumer read contracts

The consumer reads the architecture requires (A§12, A§13), with their input, result, and absence semantics. A single get raises a typed not-found exception, a list returns an empty collection, and every list is bounded to at most 100 records. The exact PHP names of the unimplemented types follow the suffix rules of Package Building §4 and the `php-category` convention.

| Read | Input | Result | Absent or not visible | Visibility rule | State |
|---|---|---|---|---|---|
| Product (`PRD-R2`) | product id | `ProductDTO` | `ProductNotFoundException` | Product active and not deleted | Implemented |
| Variant (`VAR-R2`) | variant id | `VariantDTO` | `VariantNotFoundException` | Product, Variant, Options, Values valid and active (see Variant rules) | Implemented |
| Variant list (`VAR-R2`) | product id, bounded criteria | `VariantCollectionDTO` | empty collection | as above | Implemented |
| Composition resolution (`RES-01`) | product id and a typed collection of `(optionId, optionValueId)` selections, never an array | `VariantDTO` of the single matching visible Variant | `VariantNotFoundException` when a structurally valid composition matches no visible Variant; the split between validation and business-rule failures for a structurally invalid selection is open (`GAP-08`) | as `VAR-R2`; an empty selection resolves a simple Product's Variant | Specified |
| Selectable Options (`OPT-R2`) | product id, bounded criteria | collection of Option DTOs (`id`, `productId`, `code`, `displayOrder`; the label comes from the exact Content read) | empty collection | Product visible; Option active, not deleted, labelled | Specified |
| Selectable Option Values (`OVL-R2`) | option id, bounded criteria | collection of Option Value DTOs (`id`, `optionId`, `code`, `displayOrder`) | empty collection | Option visible; Value active, not deleted, labelled, and represented by at least one visible Variant (A§12) | Specified |
| Exact Content (`CNT-R2`) | owner id and one of two selectors: exact language, or unlocalized only (never all languages) | collection of zero or one Content DTO | empty collection; never falls back to another language or to the unlocalized record | owner chain visible; record not deleted | Specified |
| Exact-scope Image Assignments (`IAS-R2`) | target (Product or Variant) plus `role_id`, `language_code`, `platform`, each matched exactly | collection of Image Assignment DTOs, `display_order, id` | empty collection | Product, target Variant, and Role (when set) active and not deleted; assignment not deleted | Specified |

The Variant DTO is settled (see the Variant rules); the field lists of the other DTOs are the persisted business columns of their table plus the timestamps, without internal keys. These specifications are derived from A§12 and A§13 and the `php-category` read pairs; implementing a row needs no further contract decision except where a gap names one.

### 4. Management and Consumer Behavior

| Aspect | Management reads | Consumer reads |
|---|---|---|
| Records | Can include `inactive` and soft-deleted records, with explicit status and deleted-state filters. | Only records valid under the visibility invariants. They cannot request deleted records or bypass an inactive ancestor. |
| Ancestors | Not suppressed. | An inactive or deleted Product, Variant, Option, Value, or Role hides its children without changing them. |
| Language | Exact language, unlocalized only, or all languages. | Exact language or unlocalized only. |
| Image Assignments | Target listing across scopes (`GAP-19`). | Exact target, role, language, and platform. |
| Bounds | Unpaginated lists return at most 100. Large lists use `PageRequest`/`PageResult`. | Bounded to at most 100. |
| Fallback | None. | None. The Host owns language, platform, and role fallback. |
| Claims about sale | None. | None. The package never states that an item is "in stock" or "sellable" (A§12, A§15). |

### 5. Consumer Workflow (logical)

This is the contract path required by Package Building §25. Runnable code for the implemented part is in [`examples/`](examples/) and the [Usage Guide](docs/guides/USAGE_GUIDE.md); the remaining flows are logical.

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
4. **Presentation.** The Host reads exact Content (`CNT-R2`) and exact-scope image-assignment (`IAS-R2`), applying its own fallback.

### 6. Open Contract Gaps

Each gap is open until a decision or evidence closes it, and the closing change updates this file in place; a closed gap is removed (the source topology, the timestamp representation, infrastructure-exception propagation, the status filter, absence semantics, and the consumer read surface are closed and appear above as settled contracts). "Needs" names the kind of resolution. It is advisory, and authority follows `std-ai-collaboration-workflow` §3.2.1: a public-contract choice belongs to the Lead and, where it changes the approved contract, the Owner. An implementer must not guess on any of these.

| ID | Gap | Sources and what is missing | Impact | Needs |
|---|---|---|---|---|
| `GAP-02` | Exact PHP type names for the unimplemented capabilities. The root namespace `Maatify\Product\` is fixed by Package Building §4 and the production autoload in `composer.json`; the implemented types are listed in the Public API table. | Illustrative names only (the illustrative-names table above). | Each new contract signature. | Technical decision when each type is written |
| `GAP-04` | Management search semantics. Searchable fields, matching mode, escaping, sort whitelist, count alignment. Lookup by business key (Product code, SKU, barcode, role key, Option/Value code) is unspecified. | Package Building §11 makes these package-owned. A§12 names "search" without defining it. | Management list/search contracts. | Contract decision |
| `GAP-05` | List bounding and pagination. Which lists are paginated. Behavior when an unpaginated list would exceed 100 (truncate or reject). Page-size limit versus the shared default of 200. Default order for lists with no `display_order` (Product, Role, Content). | A§12, DEC-006, persistence reference. | Every list. | Contract decision |
| `GAP-08` | Resolution failure contract. Typed result or exception is open (A§5.5). The classes `FC-VAL` versus `FC-INV` for each rejection, and the error for a Product that is not visible, are not fixed. | A§5.5(6), A§12. | `RES-01`. | Contract decision |
| `GAP-09` | Re-adding a soft-deleted identity: implicit restore on Create, or a typed conflict with an explicit restore? **The approved text is not uniform.** For implicit or unspecified: A§6.1 last bullet ("re-adding a soft-deleted logical identity restores/reuses that identity; it never inserts a second row") and A§9.3 ("re-adding the same logical assignment restores/reuses its original identity"). For explicit restore: A§6.4 and A§17 for Content ("re-addition requires explicit restoration"; the database and application "reject re-creating" the identity), and A§5.3 for compositions ("must not ... silently reactivate"; a typed conflict identifies the existing Variant). Current behavior: Product `code` creation rejects a soft-deleted code with `ProductCodeAlreadyExistsException` (tested); `php-category` rejects a duplicate Image Assignment and Content identity with a typed conflict and offers an explicit restore. No other family is implemented. The two readings are not equivalent, so no implementation chooses between them. **Question for the Lead (the Owner only if the Lead reads A§6.1/A§9.3 as requiring implicit restore):** may Create of a soft-deleted identity fail with a typed conflict that names the existing identity, with the explicit restore as the only way back, for Option, Option Value, Role, Content, and Image Assignment? That reading satisfies "restores/reuses ... never a second row" through the explicit restore, matches A§6.4, A§17, A§5.3, Category, and the implemented Product `code`, and contradicts no approved sentence. | A§5.3, A§6.1, A§6.4, A§9.3, A§17; `php-category` repositories. | Create semantics of five families. | Lead interpretation; Owner only if implicit restore is intended |
| `GAP-10` | Mutations under inactive or soft-deleted parents. May Options, Values, Content, or Image Assignments be added under a soft-deleted Product? Is a status change on a soft-deleted row rejected? | A§6.1 ("where allowed"). | Error semantics of every child command. | Contract decision |
| `GAP-11` | Restore and `display_order`. A restored row's position (original, or next position) and duplicate positions are unspecified. The shared Ordering API ignores soft-deleted rows. | A§9.4, A§11.3, persistence reference. | Restore of Option, Value, Variant, Assignment. | Contract decision |
| `GAP-12` | Ordering and default scope key for Image Assignments. The shared Ordering API takes one scope column. The approved scope is a composite with `NULL`s, expressed as a generated key. Compatibility with that API, and the lock order against the Product lock, are unproven. | A§9.4, A§11.4, A§11.6, persistence reference (`ScopedOrderingConfig`). | Assignment ordering and primary. | Evidence (MySQL integration) |
| `GAP-13` | Content fields and value limits. Fields and lengths of Option and Option Value Content, and whether identifier codes other than the Product code follow the Product code rules. The Product code rules are settled. | A§11 table, A§11.2. | Content Commands, other identifier Commands. | Technical decision |
| `GAP-14` | Command shapes the sources leave open. Product creation takes only `code` (settled). Open: whether reconfiguration-created Variants take SKU/barcode, whether `IAS-01` can set `is_default`, "leave, set, clear" for identifiers, and the behavior of a same-status request. | A§4.5, A§4.2, A§9.4. | Create and update contracts. | Contract decision |
| `GAP-15` | Reconfiguration input and result shapes, and the lock order after the Product row. The model itself (declarative target state, omitted active Variants deactivated, Options and Values not created inside, no soft delete, replacement Variants created active, one savepoint transaction under the Product lock) is derived from the architecture in `CFG-01`. Open: the exact typed input and result, and the stable lock order across Options, Values, and Variants (A§11.6). Needs the Lead to confirm the derived model before `CFG-01` is implemented. | A§4.5, A§5.4, A§6.1, A§6.5, A§11.6, A§14. | `CFG-01`. | Lead confirmation of the derived model; Owner only if the Lead finds it changes an approved invariant or scope |
| `GAP-16` | Standalone Option and Value status changes. Derived: an Option status change always breaks an invariant outside reconfiguration, and a Value status change is valid only under an inactive Option. Whether these are public commands or only reconfiguration internals is not stated. | A§5.4, A§6.2, A§6.4, A§6.5 (derivation in §3.3, §3.4). | `OPT-02`, `OVL-02`. | Lead confirmation |
| `GAP-17` | No generic update commands. Derived: Product, Option, Option Value, and Role have no mutable business field. Confirm no `Update*` command is intended. | A§4.1, A§5.2, A§9.2, DEC-006. | Command inventory. | Lead confirmation |
| `GAP-18` | Default operations. Settled: clear-default is an independent operation; set-default is an atomic replacement; both apply to a non-deleted assignment whatever the status of its Role or target; the soft-deleted-default, no-promotion, and restore-as-non-default rules (see Image Assignments). Open: whether they are allowed when the target Product or Variant is soft-deleted, which is the parent-state question of `GAP-10`. Runtime verification of the default operations (atomic replacement under concurrency, no promotion) awaits the Image Assignment implementation; the schema-level guarantees are verified now. | A§9.4, A§11.4, `php-category`. | `IAS-02`, `IAS-03`. | Resolved with `GAP-10` |
| `GAP-19` | Image assignment management criteria. Omitted dimension versus `NULL` versus value for role, language, platform. Whether a Role is identified by id or by key. | A§9.3, A§12. | `IAS-R1`, `IAS-R2`. | Contract decision |
| `GAP-21` | Lock order across Product, Option, Value, Variant, Role, and Assignment rows for the unimplemented multi-table mutations. The transaction runner and Host-transaction behavior are settled (see the Atomicity section). | A§11.6, A§14, Package Building §25, persistence reference. | Every multi-table mutation. | Contract decision with Integration evidence |
| `GAP-23` | Collation consequences for identity beyond the Product code. Equality under `utf8mb4_unicode_ci` (including accent-insensitive and trailing-space behavior) versus the PHP pre-check, so the PHP layer does not apply a different rule. Proven for the Product code only. | A§4.1, A§5.2, A§9.2. | Other identifier Commands and conflicts. | Evidence (MySQL integration) |
| `GAP-24` | Input type of package-owned IDs at Command boundaries. `getById` takes `int`. Only `media_asset_id` is fixed for the commands. If raw `int\|string` is accepted, Package Building §8 applies. | DEC-005, Package Building §8. | Every Command with an id. | Technical decision |
| `GAP-25` | Compatibility with `maatify/php-category`. The naming, default invariant, read pair, DTO timestamp, and Media Asset ID contracts were compared with the released `v1.0.0-rc.1` during the cross-package alignment. Content semantics and the remaining families were not. | A§9.1, A§10, DEC-005. | Cross-package alignment claim. | Evidence |

## Operational Read / Reporting Classification

This section records the classification required by Package Building Standard §15.

**Classification: In Scope.**

**Reason.** §15 decides applicability by what the package owns, not by whether it uses a database. `php-product` owns persisted domain state with its own lifecycle and invariants (architecture §3.1, §4–§6, §9):

- Product identity and its `active`/`inactive` status and soft-deletion state;
- Variant identity and lifecycle, including the immutable composition;
- Option and Option Value identity and lifecycle;
- optionally localized Product, Option, and Option Value Content;
- Product Image Roles and Product/Variant Image Assignments, including default and ordering state.

These are package-owned statuses and assignments of the kind §15 names, so the capability cannot be classified Not Applicable. The persistence engine is not the justification.

### Read Contracts

The Host must be able to understand and manage this state without querying Product tables or reimplementing Product invariants. The approved architecture (§12, §13) establishes two distinct use cases, so the contracts stay separate. They are PHP-level, framework-agnostic, typed, and read-only. The capability IDs in the [Approved Contract Inventory](#approved-contract-inventory) are the inventory of these contracts. Exact names and signatures are owned by the implementation and recorded here when they exist.

| Use case | What it covers | Visibility boundary |
|---|---|---|
| **Management reads** | Listing and search of Products, Variants, Options, Option Values, Content, Image Roles, and Image Assignments, with explicit status and deleted-state filters. | Can include `inactive` and soft-deleted records, because the Host needs to administer them. Unpaginated lists return at most 100 records. Large lists use the shared `maatify/persistence` pagination (`PageRequest`/`PageResult`), not a package-local engine. |
| **Consumer reads** | Variant listing for a Product, Variant composition resolution (§5.5), exact Content reads, and exact-scope image-assignment reads. | Return only records valid under the visibility invariants: an active, non-deleted Product with structurally valid, active, non-deleted Variants and Options. A Variant appears only if every Option Value in its composition is active and not soft-deleted, belongs to the selected Option and to that Product, and the composition covers exactly the Product's active Options, one value per active Option with none missing, extra, or invalid (§5.5, §6.2, §6.3). A simple Product's single empty-composition Variant is valid because it has no active Options. An inactive or deleted ancestor suppresses its children without changing them. Consumer reads cannot request deleted records or bypass these rules. |

Both use cases keep the architecture's read semantics:

- Content reads are exact. The typed criteria distinguish an exact language, unlocalized-only (`language_code IS NULL`), and all languages (management only). There is no implicit fallback to `NULL` or another language.
- Image assignment reads use the exact target, role, language, and platform scope, where `NULL` means exactly `NULL`.
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

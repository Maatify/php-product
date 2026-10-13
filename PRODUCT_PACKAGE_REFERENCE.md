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

This is the current public runtime inventory, as implemented by the first Work Unit (Product creation with its simple Variant). It is not a released contract. Everything under `Maatify\Product\` that is not listed here is internal.

Requirements: PHP `^8.4`, `ext-pdo`, `ext-pdo_mysql`, `ext-mbstring`, MySQL `8.0.16+` with `schema/product.sql` installed, `maatify/exceptions`, `maatify/persistence` `^1.4` (Ordering and Savepoint Transaction APIs), and `maatify/shared-common` (`ClockInterface`).

| Type | Role |
|---|---|
| `Factory\ProductFactory::createProductService(PDO, ClockInterface): ProductServiceInterface` | The only construction entry point. It rejects a PDO that is not in `ERRMODE_EXCEPTION`. |
| `Product\Service\ProductServiceInterface` | `create(CreateProductCommand): CreatedProductDTO`, `getById(int $productId): ProductDTO` (consumer-visible), and `getByIdForManagement(int $productId, ProductDeletedStateEnum = NON_DELETED): ProductDTO`. |
| `Product\Command\CreateProductCommand` | Validated input: `code` only. |
| `Common\Enum\ProductDeletedStateEnum` | Soft-deletion visibility for management reads. |
| `Product\DTO\CreatedProductDTO`, `Product\DTO\ProductDTO` | Typed results; both are `JsonSerializable`. |
| `Product\Enum\ProductStatusEnum` | `active`, `inactive`. |
| `Product\Exception\ProductCodeAlreadyExistsException`, `Product\Exception\ProductNotFoundException` | Named failures. |
| `Common\Exception\ProductInvalidArgumentException` | Input contract violations. |
| `Common\Exception\ProductPersistenceException` | Storage returned data that does not match the schema contract (see Failure contract). Only the class and the `ProductExceptionInterface` marker are public; its named constructors are internal. |
| `Exception\ProductExceptionInterface` | Marker implemented by every package-defined exception. |

Consumer walkthroughs are in the [Usage Guide](docs/guides/USAGE_GUIDE.md) and runnable code is in [`examples/`](examples/); neither creates a contract beyond this file.

### Behavior and guarantees

- **Create.** `create()` writes an **inactive** Product and its single **active** empty-composition Variant in one local transaction; both rows exist or neither does. The Variant fingerprint is the SHA-256 of `v1`, its `display_order` is allocated through the shared `maatify/persistence` Ordering API under the Product row lock, and it has no SKU or barcode. The result carries both identities. The operation is not idempotent. It performs no Pricing, Inventory, or other cross-domain work.
- **Code.** `CreateProductCommand::$code` must be valid UTF-8, at most 100 characters, and not empty or whitespace only (any Unicode whitespace). It is stored exactly as supplied, never trimmed. Uniqueness uses the database collation `utf8mb4_unicode_ci`, so `P-1` and `p-1` conflict, and a soft-deleted Product keeps its code reserved.
- **Read.** `getById()` is the consumer read: it returns only an active, non-deleted Product, and any other identity raises `ProductNotFoundException`. `getByIdForManagement()` ignores status and filters only by soft-deletion state (`NON_DELETED` by default, `INCLUDE_DELETED`, `DELETED_ONLY`), matching `php-category`'s read pair. `ProductDTO` timestamps are `DateTimeImmutable` values hydrated in the Host Clock timezone, with no normalization, and serialize as `DATE_ATOM`; `ProductDTO` rejects a non-positive `id`.
- **Transactions.** The package never commits or rolls back a transaction it did not start. When the Host already has an active transaction on the same PDO connection, `create()` runs inside an operation-local savepoint in it (`PdoSavepointTransactionRunner`, `maatify/persistence` `^1.4`), so the Host still decides commit or rollback of its own transaction. Atomic composition requires every participant to use that one connection.
- **Failure contract.** A duplicate code (MySQL driver error 1062) becomes `ProductCodeAlreadyExistsException`, with the original `PDOException` as `previous`. A storage value the package cannot interpret raises the public, package-owned `ProductPersistenceException` (a `maatify/exceptions` `SystemMaatifyException` that implements `ProductExceptionInterface`): an unknown `status`, an unparseable timestamp, a column returned with an unexpected PHP type, or an `AUTO_INCREMENT` that did not yield a positive identity. It reports a broken storage contract, not a caller error, so it is not retryable by the caller, and it carries no Product data. During `create()` it is raised inside the operation's transaction or savepoint, so the failed operation is rolled back like any other failure. **Every other `PDOException` and `Throwable` propagates unchanged**; nothing is swallowed or blanket-wrapped. When the package started the transaction, it is rolled back before the exception propagates. When the Host already had a transaction on the connection, the failed operation is rolled back to its savepoint before the exception propagates: none of its writes remain in the Host transaction, so a Host that catches the exception and still commits cannot persist a Product without its Variant. The Host transaction stays active, neither committed nor rolled back by the package, and the Host's other work in it is unaffected.
- **Timestamps.** `created_at`, `updated_at`, and `deleted_at` are taken from the Host-injected `ClockInterface` and stored and returned as the `Y-m-d H:i:s` string of that instant. The package performs no timezone normalization and makes no UTC claim.
- **Concurrency.** Product creation serializes on the database unique key for the code and on the Product row lock for the Variant. Concurrent creates of one code yield exactly one Product; concurrent creates of different codes each yield exactly one Variant. This is proven against real MySQL by `tests/Integration/Runtime/ConcurrentCreationTest.php`.

### Not yet implemented

Content, Options, Option Values, non-empty Variant composition and reconfiguration, status mutations, soft delete and restore, Image Roles and Assignments, SKU and barcode mutation, Variant composition resolution, consumer-visibility reads, and pagination. They follow the architecture contract in later Work Units.

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

The Host must be able to understand and manage this state without querying Product tables or reimplementing Product invariants. The approved architecture (§12, §13) establishes two distinct use cases, so the contracts stay separate. They are PHP-level, framework-agnostic, typed, and read-only. Exact names and signatures are owned by the implementation and recorded here when they exist.

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

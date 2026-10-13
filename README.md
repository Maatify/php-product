<div align="center">

# Maatify Product

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://www.maatify.dev/company-profile/maatify_logo_white.svg">
  <source media="(prefers-color-scheme: light)" srcset="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
  <img alt="Maatify.dev" src="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
</picture>

[![Status](https://img.shields.io/badge/Status-Development-blue)](README.md)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)

[![Usage Guide](https://img.shields.io/badge/Docs-Usage%20Guide-informational)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Docs-Examples-informational)](examples/)
[![Package Reference](https://img.shields.io/badge/Docs-Package%20Reference-informational)](PRODUCT_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Docs-Changelog-informational)](CHANGELOG.md)

Framework-agnostic PHP library for Product and Variant identity on MySQL: a stable Product code, sellable Variant identities with immutable compositions, atomic reconfiguration, composition resolution, Image Roles and Assignments, and a MySQL schema that enforces the package's invariants.

</div>

---

## Key Features

- **Product with its Variant, atomically.** `create()` writes an inactive Product and its single active empty-composition Variant together: both rows exist or neither does, whether the package or the Host owns the transaction.
- **Stable identity.** A Product `code` is validated, immutable, unique case-insensitively, and stays reserved after soft deletion; the same holds for Option and Option Value codes, Image Role keys, Variant compositions, and Image Assignment identities.
- **Atomic reconfiguration.** `reconfigure()` replaces the active Option, Value, and Variant set of a Product in one Product-local operation over a complete target state: it creates new Variant identities, deactivates the replaced ones without deleting history, reuses a historical identity only when the command names it, and rejects the whole operation when an invariant would break.
- **Composition resolution.** `resolveByComposition()` turns a shopper's typed `(optionId, optionValueId)` choices into the one consumer-visible Variant, in any pair order, and fails closed with a typed exception.
- **One lifecycle contract.** Status change, soft delete, and restore for Products, Variants, Options, Option Values, Content, Image Roles, and Image Assignments: a soft-deleted row accepts nothing but restore, a soft-deleted owner blocks its children, restore keeps the saved status and revalidates the invariants, and inactivity alone blocks no management operation.
- **Exact Content.** Product names and descriptions and Option and Option Value labels are Content records per language, with `null` as the single unlocalized record; reads select an exact language, the unlocalized record, or (for management only) all languages, and never fall back.
- **Two reads with one meaning each.** `getById()` is the consumer read (active, not deleted, structurally valid); `getByIdForManagement()` filters only by soft-deletion state.
- **Host-owned boundaries.** The Host supplies the PDO connection and the Clock. The package performs no Pricing, Inventory, Supplier, Category, or media storage work.
- **Database-enforced invariants.** `schema/product.sql` defines ten tables with foreign keys, case-sensitive status checks, and exact-scope uniqueness, verified against real MySQL, including concurrent writers.

The package is in development. Standalone Option and Option Value status changes and the consumer reads of Options, Option Values, and Image Assignments are specified by the architecture but not yet implemented. See [Not yet implemented](PRODUCT_PACKAGE_REFERENCE.md#not-yet-implemented).

## Requirements

- PHP `^8.4` with `ext-pdo`, `ext-pdo_mysql`, and `ext-mbstring`
- MySQL `8.0.16` or later (verified on `8.0.16` and `8.0.46`)
- `maatify/exceptions` `^1.0`, `maatify/persistence` `^1.4`, `maatify/shared-common` `^1.0`

## Installation

No version of this package is published to a Composer distribution source yet, so there is no registry install command. Until a release exists, consume a checkout of this repository through a Composer path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/php-product",
            "options": { "symlink": false }
        }
    ],
    "require": { "maatify/php-product": "*" },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then install the schema once into your database:

```bash
mysql your_database < schema/product.sql
```

## Quick Usage

```php
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\SharedCommon\Infrastructure\SystemClock;

// $pdo: your PDO connection with PDO::ERRMODE_EXCEPTION, on a database that has schema/product.sql.
$products = ProductFactory::createProductService($pdo, new SystemClock(new DateTimeZone('UTC')));

$created = $products->create(new CreateProductCommand('SIMPLE-1')); // Product + its Variant
$product = $products->getByIdForManagement($created->productId);    // inactive until activated
```

Runnable versions are in [`examples/`](examples/); the walkthroughs are in the [Usage Guide](docs/guides/USAGE_GUIDE.md).

## Public Runtime API

This is an overview; the complete inventory and the stable contract are in the [Package Reference](PRODUCT_PACKAGE_REFERENCE.md#implemented-public-api).

| Type | Role |
|---|---|
| `Factory\ProductFactory` | The only construction entry point: `createProductService()`, `createVariantService()`, `createOptionService()`, `createOptionValueService()`, `createImageRoleService()`, `createImageAssignmentService()`, `createProductContentService()`, `createOptionContentService()`, and `createOptionValueContentService()`. |
| `Product\Service\ProductServiceInterface` | `create()`, `getById()`, `getByIdForManagement()`, `getByCodeForManagement()`, `listForManagement()`, `paginateForManagement()`, `updateStatus()`, `softDelete()`, `restore()`, `reconfigure()`. |
| `Variant\Service\VariantServiceInterface` | `getById()`, `getByIdForManagement()`, `listByProductId()`, `listByProductIdForManagement()`, `paginateByProductIdForManagement()`, `resolveByComposition()`, `updateStatus()`, `updateIdentifiers()`, `updateDisplayOrder()`, `softDelete()`, `restore()`. |
| `Option\Service\OptionServiceInterface`, `OptionValue\Service\OptionValueServiceInterface` | `create()`, `updateDisplayOrder()`, `softDelete()`, `restore()`, `getByIdForManagement()`, and the owner-scoped `list…ForManagement()` and `paginate…ForManagement()`. |
| `ImageRole\Service\ImageRoleServiceInterface` | `create()`, `updateStatus()`, `softDelete()`, `restore()`, `getByIdForManagement()`, `getByKeyForManagement()`, `listForManagement()`, `paginateForManagement()`. |
| `ImageAssignment\Service\ImageAssignmentServiceInterface` | `create()`, `setDefault()`, `clearDefault()`, `updateDisplayOrder()`, `softDelete()`, `restore()`, `getByIdForManagement()`, `listForManagement()`, `paginateForManagement()`. |
| `Content\Service\ProductContentServiceInterface`, `Content\Service\OptionContentServiceInterface`, `Content\Service\OptionValueContentServiceInterface` | `create()`, `update()`, `softDelete()`, `restore()`, `getByIdForManagement()`, `listForManagement()`, `paginateForManagement()`, `listVisible()`. |
| Commands | `CreateProductCommand`, `ReconfigureProductCommand` (with `ReconfigureOptionTarget`, `ReconfigureVariantTarget`), `CreateOptionCommand`, `CreateOptionValueCommand`, `CreateImageRoleCommand`, `CreateImageAssignmentCommand`, `UpdateVariantIdentifiersCommand` (with `Variant\ValueObject\VariantIdentifierChange`), the `Update*DisplayOrderCommand` of Variants, Options, Option Values, and Image Assignments, the `Create*ContentCommand` and `Update*ContentCommand` of each Content owner; `Content\ValueObject\ContentLanguageFilter` and `Content\ValueObject\ExactContentLanguage` for language selection; `Variant\ValueObject\OptionValueSelection` and `Variant\DTO\VariantCompositionCriteriaDTO` for compositions. |
| DTOs and enums | Typed results (`CreatedProductDTO`, `ProductDTO`, `ProductReconfigurationResultDTO`, `VariantDTO` and its collection and criteria types, the list criteria and collection types of every family, the exact image-assignment selectors (`ImageAssignmentTargetFilter`, `ImageAssignmentRoleFilter`, `ImageAssignmentLanguageFilter`, `ImageAssignmentPlatformFilter`), `OptionDTO`, `OptionValueDTO`, `ImageRoleDTO`, `ImageAssignmentDTO`, the Content DTOs with their collection and criteria types, and the `Created*DTO` identities), the status enums of each family, and `Common\Enum\ProductDeletedStateEnum`. |
| Exceptions | Named failures for not-found, conflict, validation (`ProductInvalidArgumentException`), business-rule (`ProductBusinessRuleException`), and storage-contract (`ProductPersistenceException`) conditions; all implement `Exception\ProductExceptionInterface`. |

Everything under `Maatify\Product\` that the Package Reference does not list is internal.

## Critical Runtime Behavior

- **Transactions.** The package never commits or rolls back a transaction it did not start. Inside a Host transaction on the same connection, every mutation runs in an operation-local savepoint, so a failed call leaves none of its writes behind and the Host can still commit its other work.
- **Locking.** Every mutation that can affect a Product's graph locks the Product row first, then its Options, Option Values, and Variants by ascending identity, then the Image Role, Image Assignment, or Content rows it touches; it reads the state it validates only after taking the Product lock.
- **Concurrency.** Concurrent creates of one code yield exactly one Product; concurrent reconfigurations of one Product serialize into one winner and typed conflicts; concurrent default replacements within one exact scope leave exactly one default. All proven against real MySQL with separate processes.
- **Timestamps.** Values come from the Host Clock and are returned as `DateTimeImmutable` in the Host Clock's timezone, without normalization.

## Exception and Error Propagation

A taken identity becomes the family's conflict exception (`ProductCodeAlreadyExistsException`, `OptionCodeAlreadyExistsException`, `OptionValueCodeAlreadyExistsException`, `ImageRoleKeyAlreadyExistsException`, `ImageAssignmentAlreadyExistsException`, `VariantSkuAlreadyExistsException`, `VariantBarcodeAlreadyExistsException`, the `*ContentAlreadyExistsException` of each Content owner, `VariantCompositionConflictException`); a missing or not-visible identity becomes its not-found exception; invalid input, including a structurally invalid composition selection, becomes `ProductInvalidArgumentException`; a soft-deleted row or owner, or an operation that would break a Product invariant, becomes `ProductBusinessRuleException`; data the schema contract does not allow becomes `ProductPersistenceException`. Every other `PDOException` or `Throwable` propagates unchanged. Details: [Failure contract](PRODUCT_PACKAGE_REFERENCE.md#behavior-and-guarantees).

## Schema

[`schema/product.sql`](schema/product.sql) creates ten tables with the `maa_product_` prefix and defines no triggers, routines, or seed data. See [`schema/README.md`](schema/README.md).

## Examples

- [`01-create-and-read-product.php`](examples/01-create-and-read-product.php): create, read, consumer visibility, duplicate handling.
- [`02-host-transaction.php`](examples/02-host-transaction.php): composing `create()` inside a Host-owned transaction.
- [`03-read-variants.php`](examples/03-read-variants.php): management and consumer Variant reads, and the SKU and barcode update.
- [`04-reconfigure-and-resolve.php`](examples/04-reconfigure-and-resolve.php): prepare Options and Values, name and label them with Content commands, make the Product configurable, resolve a shopper's choices, read exact Content, and go back to simple.

## Documentation

- [Usage Guide](docs/guides/USAGE_GUIDE.md)
- [Package Reference](PRODUCT_PACKAGE_REFERENCE.md)
- [Changelog](CHANGELOG.md)
- [CI gates and local verification](docs/verification/CI_GATES.md)

## Quality Status

Continuous integration runs Composer validation, PHP syntax, PHPStan at level max with no baseline, PER-CS 3.1 style, Unit tests, Integration tests on real MySQL (`8.0.16` and `8.0.46`, PHP 8.4 and 8.5), lowest-dependency resolution, a Composer security audit, a Consumer Verification Harness that also smoke-executes every example, workflow lint, and whitespace verification. The stable `Final Gate` job aggregates them and is a required check on `main`. No release, stability, or support claim is made.

## Development and Testing

Every gate has a local command; see [CI gates and local verification](docs/verification/CI_GATES.md). Typical commands:

```bash
composer update --no-interaction --prefer-dist
composer analyse
composer format:check
composer test:unit
composer test:integration   # needs Docker with the Compose plugin
composer verify:consumer    # needs Docker with the Compose plugin
```

## License

Released under the [MIT License](LICENSE), as declared in [`composer.json`](composer.json).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>

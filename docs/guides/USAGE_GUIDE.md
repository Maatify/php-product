# Usage Guide

This guide shows how a Host application consumes `maatify/php-product`. The technical contract (the complete Public Runtime API and its guarantees) is owned by the [Package Reference](../../PRODUCT_PACKAGE_REFERENCE.md); this guide does not redefine it. Runnable code is in [`examples/`](../../examples/).

The package is in development and no version is published. This guide covers exactly what is implemented today: Product creation, lifecycle, and reconfiguration; Variant reads, composition resolution, and lifecycle; Options and Option Values; Product, Option, and Option Value Content; Image Roles and Image Assignments.

## Fit

Use the package when your Host needs a stable Product identity and a stable sellable-unit (Variant) identity that Pricing, Inventory, and orders in other packages can reference, stored in MySQL through your own PDO connection.

It does not fit if you need prices, stock, suppliers, Categories, orders, URL slugs, HTTP routes, UI, or storage and upload of media; those stay outside the package (see the Package Reference, *Domain Boundaries and Non-Goals*).

## Requirements

- PHP `^8.4` with `ext-pdo`, `ext-pdo_mysql`, `ext-mbstring`.
- MySQL `8.0.16` or later with [`schema/product.sql`](../../schema/product.sql) installed. The schema creates tables only.
- A PDO connection with `PDO::ERRMODE_EXCEPTION`, and a Host Clock implementing `Maatify\SharedCommon\Contracts\ClockInterface` (for example `SystemClock`). The factory rejects a connection that does not throw.

## Capability map

| Consumer capability | Walkthrough | Example |
|---|---|---|
| Create a Product together with its Variant | [1. Create a Product](#1-create-a-product) | [`01-create-and-read-product.php`](../../examples/01-create-and-read-product.php) |
| Read a Product for consumers or for management | [2. Read a Product](#2-read-a-product) | [`01-create-and-read-product.php`](../../examples/01-create-and-read-product.php) |
| Read the Variants of a Product for management or consumers | [3. Read Variants](#3-read-variants) | [`03-read-variants.php`](../../examples/03-read-variants.php) |
| Handle duplicate codes and other failures | [4. Handle failures](#4-handle-failures) | [`01-create-and-read-product.php`](../../examples/01-create-and-read-product.php) |
| Create inside a Host-owned transaction | [5. Compose with a Host transaction](#5-compose-with-a-host-transaction) | [`02-host-transaction.php`](../../examples/02-host-transaction.php) |
| Activate, deactivate, soft-delete, and restore | [6. Lifecycle](#6-lifecycle) | [`04-reconfigure-and-resolve.php`](../../examples/04-reconfigure-and-resolve.php) |
| Prepare Options and Values, then make a Product configurable | [7. Reconfigure a Product](#7-reconfigure-a-product) | [`04-reconfigure-and-resolve.php`](../../examples/04-reconfigure-and-resolve.php) |
| Resolve a shopper's choices to one Variant | [8. Resolve a composition](#8-resolve-a-composition) | [`04-reconfigure-and-resolve.php`](../../examples/04-reconfigure-and-resolve.php) |
| Set, replace, or clear a Variant's SKU and barcode | [11. Variant SKU and barcode](#11-variant-sku-and-barcode) | [`03-read-variants.php`](../../examples/03-read-variants.php) |
| List, search, and page through Products, Variants, Options, Values, Image Roles, and Image Assignments | [13. Management lists](#13-management-lists) | none yet |
| Reorder Variants, Options, Values, and Image Assignments | [12. Ordering](#12-ordering) | [`04-reconfigure-and-resolve.php`](../../examples/04-reconfigure-and-resolve.php) |
| Name Products and label Options and Values per language, and read them exactly | [10. Content](#10-content) | [`04-reconfigure-and-resolve.php`](../../examples/04-reconfigure-and-resolve.php) |
| Assign Media Assets with roles, scopes, and one default | [9. Image Roles and Assignments](#9-image-roles-and-assignments) | none yet |

Standalone Option and Option Value status changes and the consumer reads of Options, Option Values, and Image Assignments are not implemented yet and have no walkthrough.

## Setup

```php
use Maatify\Product\Factory\ProductFactory;
use Maatify\SharedCommon\Infrastructure\SystemClock;

$products = ProductFactory::createProductService($pdo, new SystemClock(new DateTimeZone('UTC')));
```

`ProductFactory` is the only construction entry point; it builds one service per capability (`createProductService()`, `createVariantService()`, `createOptionService()`, `createOptionValueService()`, `createImageRoleService()`, `createImageAssignmentService()`, `createProductContentService()`, `createOptionContentService()`, `createOptionValueContentService()`) on the connection and Clock you pass. All writes of one operation use that connection, so a transaction you already hold on it is joined, never committed by the package.

## 1. Create a Product

- **Input:** a Product `code`: valid UTF-8, at most 100 characters, not empty or whitespace only. It is stored exactly as supplied and is never trimmed.
- **Public call:** `ProductServiceInterface::create(new CreateProductCommand($code))`.
- **Result:** a `CreatedProductDTO` with `productId` and `variantId`. The Product is **inactive**; its single Variant is **active** and has no SKU or barcode.
- **Boundary:** the call is not idempotent (repeating a code conflicts), performs no Pricing or Inventory work, and either writes both rows or neither.

```php
$created = $products->create(new CreateProductCommand('SIMPLE-1'));
// $created->productId, $created->variantId
```

## 2. Read a Product

- **Input:** a Product identity.
- **Public call:** `getById(int $productId)` for consumers, or `getByIdForManagement(int $productId, ProductDeletedStateEnum $deletedState = NON_DELETED)` for management.
- **Result:** a `ProductDTO` (`id`, `code`, `status`, `createdAt`, `updatedAt`, `deletedAt`). Timestamps are `DateTimeImmutable` values in the Host Clock's timezone.
- **Boundary:** `getById()` returns only an active, non-deleted Product; any other identity raises `ProductNotFoundException`. A newly created Product is inactive, so it is visible to `getByIdForManagement()` only. `getByIdForManagement()` ignores status and filters by soft-deletion state: `NON_DELETED` (default), `INCLUDE_DELETED`, or `DELETED_ONLY`.

```php
$product = $products->getByIdForManagement($created->productId);
```

## 3. Read Variants

- **Input:** a Product or Variant identity.
- **Public call:** `VariantServiceInterface` from `ProductFactory::createVariantService($pdo, $clock)`: `getById()` and `listByProductId()` for consumers, `getByIdForManagement()` and `listByProductIdForManagement()` for management.
- **Result:** a `VariantDTO` (`id`, `productId`, `sku`, `barcode`, `status`, `displayOrder`, `composition`, timestamps) or a `VariantCollectionDTO`, ordered by `display_order` then `id`, at most 100.
- **Boundary:** consumer reads return only visible Variants: the Product and the Variant are active and not deleted, the composition is valid (every selected Option and Value active, not deleted, and labelled, covering exactly the Product's active Options), and the Product's configuration is valid as a whole (every active Value of an active Option is represented by an active valid Variant; otherwise every Variant of the Product is hidden). A single get of a missing or not-visible Variant raises `VariantNotFoundException`; a list never fails for a missing Product and returns an empty collection. Management reads ignore visibility and filter by status (`null` means any) and `ProductDeletedStateEnum`.

```php
$variants = ProductFactory::createVariantService($pdo, $clock);
$managed = $variants->getByIdForManagement($created->variantId);
$visible = $variants->listByProductId($created->productId); // empty until the Product is active
```

## 4. Handle failures

- **Input:** any call.
- **Public call:** catch the named exceptions you care about; all package-defined failures implement `Maatify\Product\Exception\ProductExceptionInterface`.
- **Result:** a conflict exception for a taken identity (`ProductCodeAlreadyExistsException`, `OptionCodeAlreadyExistsException`, `OptionValueCodeAlreadyExistsException`, `ImageRoleKeyAlreadyExistsException`, `ImageAssignmentAlreadyExistsException`, `ProductContentAlreadyExistsException`, `OptionContentAlreadyExistsException`, `OptionValueContentAlreadyExistsException`, `VariantSkuAlreadyExistsException`, `VariantBarcodeAlreadyExistsException`, `VariantCompositionConflictException`; identities compare case-insensitively and stay reserved across soft-deleted history); a not-found exception for a missing or not-visible identity (`ProductNotFoundException`, `VariantNotFoundException`, `OptionNotFoundException`, `OptionValueNotFoundException`, `ImageRoleNotFoundException`, `ImageAssignmentNotFoundException`, `ProductContentNotFoundException`, `OptionContentNotFoundException`, `OptionValueContentNotFoundException`); `ProductInvalidArgumentException` for invalid input, including a structurally invalid composition selection; `ProductBusinessRuleException` when the row or its owner is soft deleted or the operation would break a Product invariant; `ProductPersistenceException` when storage returns data the schema contract does not allow.
- **Boundary:** every other `PDOException` or `Throwable` propagates unchanged. See the Package Reference, *Failure contract*.

## 5. Compose with a Host transaction

- **Input:** a transaction you opened on the same PDO connection you gave the factory.
- **Public call:** any mutation inside it (`create()`, `reconfigure()`, the lifecycle commands, the image-assignment commands).
- **Result:** the call runs in an operation-local savepoint. If it fails, none of its writes remain in your transaction. After a statement-level failure (for example a duplicate identity) your transaction stays active, you decide whether to commit or roll back, and your other work in it is unaffected. A Host that catches the failure and commits can never persist a Product without its Variant.
- **Whole-transaction failures:** when MySQL rolls back the whole transaction itself, such as on a deadlock (driver error 1213), your transaction and your earlier work in it are gone on the server. The package propagates that `PDOException` unchanged; treat it as a failure of your whole transaction, not only of the call.
- **Boundary:** the package never commits or rolls back your transaction. Composition requires every participant to use that one connection.

```php
$pdo->beginTransaction();
$products->create(new CreateProductCommand('A'));
try {
    $products->create(new CreateProductCommand('a')); // duplicate: rolled back to its savepoint
} catch (ProductCodeAlreadyExistsException) {
}
$pdo->commit(); // commits Product 'A' and its Variant only
```

## 6. Lifecycle

- **Input:** a Product, Variant, Option, Option Value, Content, Image Role, or Image Assignment identity.
- **Public call:** `updateStatus(id, StatusEnum)` (Products, Variants, Image Roles), `softDelete(id)`, and `restore(id)` on the capability's service.
- **Result:** `void`; read the new state back through the management read.
- **Boundary:** one contract for every family. A soft-deleted row accepts nothing but `restore()`; a status change, re-delete, default operation, or other change on it is a `ProductBusinessRuleException`. A child under a soft-deleted owner (an Option of a deleted Product, a Value of a deleted Option, an Assignment of a deleted Product or Variant) cannot be created or changed until the owner chain is restored. `restore()` keeps the saved status (an inactive row stays inactive), puts an ordered row (Option, Value, Variant, Image Assignment) at the end of its scope, and revalidates the Product invariants first. A missing identity is the family's not-found exception. Inactivity alone blocks no management operation. Product activation needs a non-empty name and a valid configuration; deactivating or deleting the last valid active Variant, or deleting an Option or Value an active Variant needs, is rejected: those changes belong to reconfiguration.

```php
$products->updateStatus($created->productId, ProductStatusEnum::ACTIVE); // needs a name and a valid configuration
$products->softDelete($created->productId);
$products->restore($created->productId);                                  // status preserved, invariants revalidated
```

## 7. Reconfigure a Product

- **Input:** a `ReconfigureProductCommand`: the Product, the complete set of Options that must be active (`ReconfigureOptionTarget`, each with the complete set of its active Values), and the complete set of Variant compositions that must be active (`ReconfigureVariantTarget`, each a list of `OptionValueSelection` pairs and, when that composition already exists in the Product's history, its `reuseVariantId`). Options and Values are created inactive beforehand with `OptionServiceInterface::create()` and `OptionValueServiceInterface::create()` and need a label (an Option or Option Value Content record, see [10. Content](#10-content)); the command never creates them and carries no SKU or barcode.
- **Public call:** `ProductServiceInterface::reconfigure($command)`.
- **Result:** a `ProductReconfigurationResultDTO` with the Variant identities created (in request order), reactivated, restored, and deactivated, and the Option and Value identities whose status changed.
- **Boundary:** one atomic Product-local operation: the Product row is locked before anything is read, and any failure rejects the whole operation. An active Variant absent from the target becomes inactive and keeps its history. A composition that already exists (active, inactive, or soft-deleted) is a `VariantCompositionConflictException` unless `reuseVariantId` names that Variant; the named Variant is then reactivated or restored inside the operation. The empty composition is the simple Product: going back to simple means naming the original empty-composition Variant, never creating a second one (a missing original is a `ProductPersistenceException`). A target that would violate an invariant (an Option of another Product, a deleted or unlabelled Option or Value, incomplete or extra coverage, an unrepresented Value, no Variant) is a `ProductBusinessRuleException`; a repeated element in the command is a `ProductInvalidArgumentException`.

```php
$result = $products->reconfigure(new ReconfigureProductCommand(
    $productId,
    [new ReconfigureOptionTarget($color, [$red, $blue])],
    [
        new ReconfigureVariantTarget([new OptionValueSelection($color, $red)]),
        new ReconfigureVariantTarget([new OptionValueSelection($color, $blue)]),
    ],
));
// $result->createdVariantIds, $result->deactivatedVariantIds (the empty-composition Variant)
```

## 8. Resolve a composition

- **Input:** the Product identity and a `VariantCompositionCriteriaDTO` holding the shopper's `OptionValueSelection` pairs, in any order; an empty selection is valid and names a simple Product's Variant.
- **Public call:** `VariantServiceInterface::resolveByComposition($productId, $criteria)`.
- **Result:** the `VariantDTO` of the one consumer-visible Variant with exactly that composition.
- **Boundary:** read-only: it never creates or reactivates a Variant and never invents a default. A Product that does not exist or is not consumer-visible is a `ProductNotFoundException`. A structurally invalid selection (a repeated Option, an Option or Value that is not an active member of the Product, a Value paired with the wrong Option, an active Option left unselected) is a `ProductInvalidArgumentException`. A valid, complete selection that matches no visible Variant, including a historical inactive composition, is a `VariantNotFoundException`.

```php
$variant = $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO([
    new OptionValueSelection($size, $medium),
    new OptionValueSelection($color, $blue),
]));
```

## 9. Image Roles and Assignments

- **Input:** a `CreateImageRoleCommand` (the role key), and a `CreateImageAssignmentCommand`: the Product, an optional Variant of that Product, the Host-owned `mediaAssetId` (`int`, or a string of digits), and the exact `roleId`, `languageCode`, and `platform` scope where `null` means exactly `null`.
- **Public call:** `ImageRoleServiceInterface::create()`, `updateStatus()`, `softDelete()`, `restore()`; `ImageAssignmentServiceInterface::create()`, `setDefault()`, `clearDefault()`, `softDelete()`, `restore()`; `getByIdForManagement()` on both.
- **Result:** `CreatedImageRoleDTO` and `CreatedImageAssignmentDTO` identities; `ImageRoleDTO` and `ImageAssignmentDTO` snapshots.
- **Boundary:** the package never resolves or verifies the Media Asset. A new assignment is not the default and gets its position within its exact scope from the shared Ordering API. `setDefault()` makes a non-deleted assignment the single default of its exact `(target, role, language, platform)` scope, clearing the previous one in the same operation; it needs a non-deleted target Product and Variant and a non-deleted Role, while an inactive Product, Variant, or Role does not block it. `clearDefault()` works even under a deleted target or Role and promotes nothing. Soft-deleting a default clears the marker and promotes nothing; `restore()` leaves the row non-default. An identity that exists in soft-deleted history is an `ImageAssignmentAlreadyExistsException`: restore it instead.

## 10. Content

- **Input:** for Product Content, a `CreateProductContentCommand` (`productId`, `languageCode`, `name`, optional `shortDescription` and `description`); for Option and Option Value labels, a `CreateOptionContentCommand` or `CreateOptionValueContentCommand` (owner identity, `languageCode`, `name`). `languageCode` `null` is the single unlocalized record; any other value is one exact language (not empty or whitespace only, at most 16 characters). `name` is required, at most 255 characters, not empty or whitespace only; `shortDescription` is at most 500 characters and `description` at most 65535 bytes. Nothing is trimmed. Updates take the Content identity and the complete replacement fields.
- **Public call:** `ProductFactory::createProductContentService()`, `createOptionContentService()`, or `createOptionValueContentService()`, then `create()`, `update()`, `softDelete()`, `restore()`, `getByIdForManagement()`, `listForManagement()`, and `paginateForManagement()` for management, and `listVisible()` for consumers.
- **Result:** a `Created*ContentDTO` identity; `*ContentDTO` snapshots in a `*ContentCollectionDTO` (ordered by language, the unlocalized record first, then identity) or in a `maatify/persistence` `PageResult` (20 per page by default, at most 100).
- **Boundary:** the identity `(owner, language)` is unique across soft-deleted history under the collation (`EN` and `en` are one identity): a second record, or re-adding a deleted one, is the owner's `*ContentAlreadyExistsException`, and `restore()` brings the original back. An update equal to the stored fields writes nothing. Soft-deleting the last name of an active Product, or the last label of an active Option or of an active Value of an active Option, is a `ProductBusinessRuleException`; an inactive owner may lose its last label. Records of a soft-deleted owner chain cannot be created or changed. Management reads take a `ContentLanguageFilter` (`exactLanguage()`, `unlocalizedOnly()`, or `allLanguages()`) and a deleted-state filter. The consumer read takes an `ExactContentLanguage` (`exactLanguage()` or `unlocalizedOnly()`, never all languages) and returns zero or one record only while the owner chain is active and not deleted. No read falls back: a request for `ar` never returns the unlocalized record or another language; the Host owns any fallback policy.

```php
$content = ProductFactory::createProductContentService($pdo, $clock);
$content->create(new CreateProductContentCommand($productId, null, 'Shirt'));
$content->create(new CreateProductContentCommand($productId, 'ar', 'قميص', 'قميص قطني'));
$arabic = $content->listVisible($productId, ExactContentLanguage::exactLanguage('ar')); // empty until the Product is active
$all = $content->listForManagement(new ProductContentListCriteriaDTO($productId, ContentLanguageFilter::allLanguages()));
$page = $content->paginateForManagement(new ProductContentListCriteriaDTO($productId, ContentLanguageFilter::allLanguages()), new PageRequest(1, 20));
```

## 11. Variant SKU and barcode

- **Input:** an `UpdateVariantIdentifiersCommand` with the Variant identity and one `VariantIdentifierChange` each for the SKU and the barcode: `unchanged()`, `set($value)`, or `clear()`. A set value is not empty or whitespace only, at most 128 characters, and stored exactly as supplied (a barcode keeps its leading zeroes).
- **Public call:** `VariantServiceInterface::updateIdentifiers($command)`.
- **Result:** `void`; read the values back through `getByIdForManagement()`.
- **Boundary:** the Variant identity never changes. A soft-deleted Variant or Product is a `ProductBusinessRuleException`; an inactive one is not. Each value is unique across all Variants under the collation (`SKU-1` and `sku-1` collide), and a soft-deleted Variant keeps its values reserved: a taken value is a `VariantSkuAlreadyExistsException` or `VariantBarcodeAlreadyExistsException`. Replacing or clearing a value releases it. A request that changes nothing writes nothing.

```php
$variants->updateIdentifiers(new UpdateVariantIdentifiersCommand(
    $variantId,
    VariantIdentifierChange::set('SHIRT-RED-M'),
    VariantIdentifierChange::set('0012345678905'),
));
$variants->updateIdentifiers(new UpdateVariantIdentifiersCommand($variantId, VariantIdentifierChange::clear(), VariantIdentifierChange::unchanged()));
```

## 12. Ordering

- **Input:** an `UpdateVariantDisplayOrderCommand`, `UpdateOptionDisplayOrderCommand`, `UpdateOptionValueDisplayOrderCommand`, or `UpdateImageAssignmentDisplayOrderCommand`: the row identity and a position of at least 1.
- **Public call:** `updateDisplayOrder($command)` on the Variant, Option, Option Value, or Image Assignment service.
- **Result:** `void`; the row is at the requested position within its scope and the other rows of that scope shifted to keep the positions contiguous. Read the positions back through the management reads (`displayOrder`).
- **Boundary:** a Variant or an Option moves within its Product, a Value within its Option, an assignment within its exact `(target, role, language, platform)` scope; nothing outside that scope changes. A position above the maximum is clamped to it, and the current position writes nothing. The move goes through the shared `maatify/persistence` Ordering API and joins a Host transaction you hold on the connection. A soft-deleted row or owner is a `ProductBusinessRuleException`; a missing identity is the family's not-found exception. A restored row always goes to the end of its scope, because a newer row may hold its old position.

```php
$options->updateDisplayOrder(new UpdateOptionDisplayOrderCommand($size, 1));   // size first
$variants->updateDisplayOrder(new UpdateVariantDisplayOrderCommand($variantId, 99)); // clamped to the last position
```

## 13. Management lists

- **Input:** the family's list criteria: `ProductListCriteriaDTO` (status, deleted state, `maxResults`, optional `search`), `VariantListCriteriaDTO`, `OptionListCriteriaDTO`, `OptionValueListCriteriaDTO`, `ImageRoleListCriteriaDTO` (status, deleted state, `maxResults`), or `ImageAssignmentListCriteriaDTO` (the Product and exact target, role, language, and platform selectors); for a page, also a `maatify/persistence` `PageRequest` (page, per page, sort key, direction).
- **Public call:** `listForManagement()`/`paginateForManagement()` on the Product, Image Role, and Image Assignment services; `listByProductIdForManagement()`/`paginateByProductIdForManagement()` on the Variant and Option services; `listByOptionIdForManagement()`/`paginateByOptionIdForManagement()` on the Option Value service; `getByCodeForManagement()` (Product) and `getByKeyForManagement()` (Image Role) for one record by business key.
- **Result:** a `*CollectionDTO` of at most `maxResults` (1 to 100, default 100, silently truncated), or a `PageResult` of the family's DTO with `total`, `filtered`, page metadata, and the applied sort.
- **Boundary:** pages hold 20 records by default and at most 100; an unknown sort key falls back to the list's default order (Products by `id`, Variants, Options, and Values by `display_order`, Image Roles by the binary role key, Image Assignments by exact scope then position). `null` status means any status; the deleted state is non-deleted by default; nothing is hidden because of an owner's state, and a missing owner gives an empty result. Only Products can be searched: a literal, case-insensitive substring of `code` (`%`, `_`, `\`, and `!` are plain characters, whatever the connection's `sql_mode`); `total` then counts the Products without the search and `filtered` with it. For Image Assignments, an omitted selector applies no condition, `exactNull()` matches only `NULL`, and a value never matches `NULL`. There is no lookup by SKU, barcode, or Option or Value code.

```php
$page = $products->paginateForManagement(new ProductListCriteriaDTO(status: ProductStatusEnum::ACTIVE, search: 'shirt'), new PageRequest(1, 20, 'code', 'ASC'));
$product = $products->getByCodeForManagement('SHIRT-1');
$heroImages = $assignments->listForManagement(new ImageAssignmentListCriteriaDTO(
    $productId,
    ImageAssignmentTargetFilter::productLevel(),
    ImageAssignmentRoleFilter::forRole($heroRoleId),
    ImageAssignmentLanguageFilter::exactNull(),
    ImageAssignmentPlatformFilter::omitted(),
));
```

## Running the examples

All examples need a MySQL database with `schema/product.sql` installed and these environment variables: `PRODUCT_EXAMPLE_DSN`, `PRODUCT_EXAMPLE_USER`, `PRODUCT_EXAMPLE_PASSWORD`, and optionally `PRODUCT_EXAMPLE_AUTOLOAD` (the Composer autoloader of your project). The repository smoke-executes them against its own MySQL service in `composer verify:consumer`.

## Where to go next

- [Package Reference](../../PRODUCT_PACKAGE_REFERENCE.md): the complete Public Runtime API and its guarantees.
- [README](../../README.md): overview and requirements.
- [CHANGELOG](../../CHANGELOG.md): release history.

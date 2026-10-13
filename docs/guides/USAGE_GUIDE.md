# Usage Guide

This guide shows how a Host application consumes `maatify/php-product`. The technical contract (the complete Public Runtime API and its guarantees) is owned by the [Package Reference](../../PRODUCT_PACKAGE_REFERENCE.md); this guide does not redefine it. Runnable code is in [`examples/`](../../examples/).

The package is in development and no version is published. This guide covers exactly what is implemented today: Product creation with its Variant, the Product reads, and the Variant reads.

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

Options, Option Values, Content, Image Roles and Assignments, status changes, soft delete, and restore are not implemented yet and have no walkthrough.

## Setup

```php
use Maatify\Product\Factory\ProductFactory;
use Maatify\SharedCommon\Infrastructure\SystemClock;

$products = ProductFactory::createProductService($pdo, new SystemClock(new DateTimeZone('UTC')));
```

`ProductFactory::createProductService()` is the only construction entry point. All writes of one operation use the connection you pass, so a transaction you already hold on it is joined, never committed by the package.

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
- **Boundary:** consumer reads return only visible Variants: the Product and the Variant are active and not deleted and the composition is valid (every selected Option and Value active, not deleted, and labelled, covering exactly the Product's active Options). A single get of a missing or not-visible Variant raises `VariantNotFoundException`; a list never fails for a missing Product and returns an empty collection. Management reads ignore visibility and filter by status (`null` means any) and `ProductDeletedStateEnum`.

```php
$variants = ProductFactory::createVariantService($pdo, $clock);
$managed = $variants->getByIdForManagement($created->variantId);
$visible = $variants->listByProductId($created->productId); // empty until the Product is active
```

## 4. Handle failures

- **Input:** any call.
- **Public call:** catch the named exceptions you care about; all package-defined failures implement `Maatify\Product\Exception\ProductExceptionInterface`.
- **Result:** `ProductCodeAlreadyExistsException` for a duplicate code (compared case-insensitively across all history, including soft-deleted Products); `ProductNotFoundException` for a missing or not-visible Product; `VariantNotFoundException` for a missing or not-visible Variant; `ProductInvalidArgumentException` for invalid input; `ProductPersistenceException` when storage returns data the schema contract does not allow.
- **Boundary:** every other `PDOException` or `Throwable` propagates unchanged. See the Package Reference, *Failure contract*.

## 5. Compose with a Host transaction

- **Input:** a transaction you opened on the same PDO connection you gave the factory.
- **Public call:** `create()` inside it.
- **Result:** the call runs in an operation-local savepoint. If it fails, none of its writes remain in your transaction, which stays active; you decide whether to commit or roll back, and your other work in it is unaffected. A Host that catches the failure and commits can never persist a Product without its Variant.
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

## Running the examples

All examples need a MySQL database with `schema/product.sql` installed and these environment variables: `PRODUCT_EXAMPLE_DSN`, `PRODUCT_EXAMPLE_USER`, `PRODUCT_EXAMPLE_PASSWORD`, and optionally `PRODUCT_EXAMPLE_AUTOLOAD` (the Composer autoloader of your project). The repository smoke-executes them against its own MySQL service in `composer verify:consumer`.

## Where to go next

- [Package Reference](../../PRODUCT_PACKAGE_REFERENCE.md): the complete Public Runtime API and its guarantees.
- [README](../../README.md): overview and requirements.
- [CHANGELOG](../../CHANGELOG.md): release history.

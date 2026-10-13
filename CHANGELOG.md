# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

No version has been released.

### Added

- MySQL schema `schema/product.sql`: ten `maa_product_` tables for Product, Variant, Option, Option Value, Content, Image Role, and Image Assignment, with foreign keys, case-sensitive status checks, exact-scope uniqueness, and a database-enforced rule that a soft-deleted Image Assignment is never the default.
- Product creation: `ProductServiceInterface::create()` writes an inactive Product and its single active empty-composition Variant atomically, including inside a Host-owned transaction (operation-local savepoint).
- Product reads: `getById()` (active, not deleted) and `getByIdForManagement()` with `ProductDeletedStateEnum`.
- `ProductFactory::createProductService()` as the framework-agnostic construction entry point.
- Public failures: `ProductCodeAlreadyExistsException`, `ProductNotFoundException`, `ProductInvalidArgumentException`, and `ProductPersistenceException`, all implementing `ProductExceptionInterface`.
- Consumer documentation: Usage Guide, runnable examples, and `llms.txt`.
- Verification: Unit and real-MySQL Integration suites, a Consumer Verification Harness that smoke-executes the examples, and the CI `Final Gate`.

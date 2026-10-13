# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

No version has been released.

### Added

- MySQL schema `schema/product.sql`: ten `maa_product_` tables for Product, Variant, Option, Option Value, Content, Image Role, and Image Assignment, with foreign keys, case-sensitive status checks, exact-scope uniqueness, and a database-enforced rule that a soft-deleted Image Assignment is never the default.
- Product creation: `ProductServiceInterface::create()` writes an inactive Product and its single active empty-composition Variant atomically, including inside a Host-owned transaction (operation-local savepoint).
- Product reads: `getById()` (active, not deleted) and `getByIdForManagement()` with `ProductDeletedStateEnum`.
- Variant reads: `VariantServiceInterface` (`getById`, `getByIdForManagement`, `listByProductId`, `listByProductIdForManagement`), `VariantDTO` with its composition pairs, bounded list criteria, and `VariantNotFoundException`; `ProductFactory::createVariantService()`. Consumer reads hide every Variant of a Product while an active Value of an active Option is represented by no active valid Variant.
- Variant composition resolution: `VariantServiceInterface::resolveByComposition()` with `VariantCompositionCriteriaDTO` and `OptionValueSelection`; a structurally invalid selection is `ProductInvalidArgumentException`, an invisible Product is `ProductNotFoundException`, a valid composition with no visible Variant is `VariantNotFoundException`, and a stored fingerprint that does not match its pairs is `ProductPersistenceException`.
- Product reconfiguration: `ProductServiceInterface::reconfigure()` with `ReconfigureProductCommand`, `ReconfigureOptionTarget`, `ReconfigureVariantTarget`, and `ProductReconfigurationResultDTO`; one atomic Product-local operation over a complete target state, explicit reuse of historical and soft-deleted Variant identities, the simple/configurable transitions, and `VariantCompositionConflictException`.
- Lifecycle commands: Product `updateStatus`, `softDelete`, `restore`; Variant `updateStatus`, `softDelete`, `restore`; and `ProductBusinessRuleException` (`FC-INV`) for a soft-deleted row, a soft-deleted owner chain, or an invariant the operation would break.
- Options and Option Values: `OptionServiceInterface` and `OptionValueServiceInterface` (`create`, `softDelete`, `restore`, `getByIdForManagement`), their commands, DTOs, status enums, and named failures; `ProductFactory::createOptionService()` and `createOptionValueService()`.
- Image Roles and Image Assignments: `ImageRoleServiceInterface` (`create`, `updateStatus`, `softDelete`, `restore`, `getByIdForManagement`) and `ImageAssignmentServiceInterface` (`create`, `setDefault`, `clearDefault`, `softDelete`, `restore`, `getByIdForManagement`), their commands, DTOs, and named failures; `ProductFactory::createImageRoleService()` and `createImageAssignmentService()`.
- `ProductFactory::createProductService()` as the framework-agnostic construction entry point.
- Public failures: `ProductCodeAlreadyExistsException`, `ProductNotFoundException`, `ProductInvalidArgumentException`, and `ProductPersistenceException`, all implementing `ProductExceptionInterface`.
- Consumer documentation: Usage Guide, runnable examples, and `llms.txt`.
- MIT `LICENSE`, matching the `license` field in `composer.json`.
- Approved Contract Inventory in the Package Reference: capability IDs, structural invariants, management and consumer behavior, and the open contract gaps for the capabilities not yet implemented.
- Verification: Unit and real-MySQL Integration suites, including concurrent reconfiguration and concurrent default replacement with separate processes, a Consumer Verification Harness that smoke-executes the examples, and the CI `Final Gate`.

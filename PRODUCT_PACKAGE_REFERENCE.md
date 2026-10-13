# Product Package Reference

**Package:** `maatify/php-product`
**Status:** Implementation in progress, nothing released. This file is the canonical root Package Reference path required by the Package Building Standard (`/{PACKAGE_NAME}_PACKAGE_REFERENCE.md`). The implemented runtime is recorded under [Implemented Public API](#implemented-public-api): Product creation, reads, lifecycle, and reconfiguration; Variant reads, composition resolution, and lifecycle; Option and Option Value creation and lifecycle; Product, Option, and Option Value Content; Image Roles and Image Assignments. The remaining capabilities are added in place by later Work Units, and nothing here is a released contract.
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
- Content
```

`php-product` is one domain, so no `Domain` directory is used; `src/` holds one directory per capability, each containing only the responsibilities that capability actually owns (`Command`, `Criteria`, `DTO`, `Enum`, `Repository`, `Service`, and so on), per the Package Building Standard §5. Classes live under `src/` with the PSR-4 prefix `Maatify\Product\`.

The capability list is the one enumerated by the Owner-approved architecture (§13), and the Lead confirmed it for this package. `src/` contains the seven capability directories (`Product`, `Variant`, `Option`, `OptionValue`, `ImageRole`, `ImageAssignment`, `Content`) plus the package-wide `Common`, `Exception`, and `Factory` responsibilities; no empty or ceremonial folder is created. `Content` owns the Content records of all three owners (Product, Option, Option Value), because they share one identity, lifecycle, and read contract (§3.5). The architecture allows capabilities to be merged or split "if the public contract and implementation justify" it (§13), so any change to this list requires a recorded decision, and every Work Unit must place code according to this declaration.

## Implemented Public API

This is the current public runtime inventory. It is not a released contract. Everything under `Maatify\Product\` that is not listed here is internal.

Requirements: PHP `^8.4`, `ext-pdo`, `ext-pdo_mysql`, `ext-mbstring`, MySQL `8.0.16+` with `schema/product.sql` installed, `maatify/exceptions`, `maatify/persistence` `^1.4` (Ordering, Pagination, and Savepoint Transaction APIs), and `maatify/shared-common` (`ClockInterface`).

| Type | Role |
|---|---|
| `Factory\ProductFactory` | The only construction entry point, one service per capability on the Host's PDO and Clock: `createProductService(PDO, ClockInterface): ProductServiceInterface`, `createVariantService(PDO, ClockInterface): VariantServiceInterface`, `createOptionService(PDO, ClockInterface): OptionServiceInterface`, `createOptionValueService(PDO, ClockInterface): OptionValueServiceInterface`, `createImageRoleService(PDO, ClockInterface): ImageRoleServiceInterface`, `createImageAssignmentService(PDO, ClockInterface): ImageAssignmentServiceInterface`, `createProductContentService(PDO, ClockInterface): ProductContentServiceInterface`, `createOptionContentService(PDO, ClockInterface): OptionContentServiceInterface`, and `createOptionValueContentService(PDO, ClockInterface): OptionValueContentServiceInterface`. Each rejects a PDO that is not in `ERRMODE_EXCEPTION`. |
| `Product\Service\ProductServiceInterface` | `create(CreateProductCommand): CreatedProductDTO`, `getById(int $productId): ProductDTO` (consumer-visible), `getByIdForManagement(int $productId, ProductDeletedStateEnum = NON_DELETED): ProductDTO`, `updateStatus(int $productId, ProductStatusEnum): void`, `softDelete(int $productId): void`, `restore(int $productId): void`, and `reconfigure(ReconfigureProductCommand): ProductReconfigurationResultDTO`. |
| `Product\Command\CreateProductCommand` | Validated input: `code` only. |
| `Product\Command\ReconfigureProductCommand`, `Product\Command\ReconfigureOptionTarget`, `Product\Command\ReconfigureVariantTarget` | Validated reconfiguration input: the Product, the complete target set of active Options each with its complete set of active Values, and the complete target set of Variant compositions each with an optional `reuseVariantId`. All are `JsonSerializable`. |
| `Variant\ValueObject\OptionValueSelection` | One `(optionId, optionValueId)` pair of a composition; the typed selection element of resolution and reconfiguration. `JsonSerializable`. |
| `Common\Enum\ProductDeletedStateEnum` | Soft-deletion visibility for management reads. |
| `Product\DTO\CreatedProductDTO`, `Product\DTO\ProductDTO`, `Product\DTO\ProductReconfigurationResultDTO` | Typed results; all are `JsonSerializable`. |
| `Product\Enum\ProductStatusEnum`, `Variant\Enum\VariantStatusEnum`, `Option\Enum\OptionStatusEnum`, `OptionValue\Enum\OptionValueStatusEnum`, `ImageRole\Enum\ImageRoleStatusEnum` | `active`, `inactive`, one enum per status-bearing family. |
| `Variant\Service\VariantServiceInterface` | `getById(int $variantId): VariantDTO` (consumer-visible), `getByIdForManagement(int $variantId, ProductDeletedStateEnum = NON_DELETED): VariantDTO`, `listByProductId(int $productId, VariantVisibleListCriteriaDTO): VariantCollectionDTO` (consumer), `listByProductIdForManagement(int $productId, VariantListCriteriaDTO): VariantCollectionDTO`, `resolveByComposition(int $productId, VariantCompositionCriteriaDTO): VariantDTO` (consumer), `updateStatus(int $variantId, VariantStatusEnum): void`, `softDelete(int $variantId): void`, and `restore(int $variantId): void`. |
| `Variant\DTO\VariantDTO`, `Variant\DTO\VariantCompositionPairDTO`, `Variant\DTO\VariantCollectionDTO` | Typed results; all are `JsonSerializable`. |
| `Variant\DTO\VariantListCriteriaDTO`, `Variant\DTO\VariantVisibleListCriteriaDTO`, `Variant\DTO\VariantCompositionCriteriaDTO` | Bounded read criteria, and the typed selection of composition resolution. |
| `Option\Service\OptionServiceInterface` | `create(CreateOptionCommand): CreatedOptionDTO`, `softDelete(int $optionId): void`, `restore(int $optionId): void`, and `getByIdForManagement(int $optionId, ProductDeletedStateEnum = NON_DELETED): OptionDTO`. |
| `Option\Command\CreateOptionCommand`, `Option\DTO\OptionDTO`, `Option\DTO\CreatedOptionDTO` | Validated input (`productId`, `code`) and typed results; all are `JsonSerializable`. |
| `OptionValue\Service\OptionValueServiceInterface` | `create(CreateOptionValueCommand): CreatedOptionValueDTO`, `softDelete(int $optionValueId): void`, `restore(int $optionValueId): void`, and `getByIdForManagement(int $optionValueId, ProductDeletedStateEnum = NON_DELETED): OptionValueDTO`. |
| `OptionValue\Command\CreateOptionValueCommand`, `OptionValue\DTO\OptionValueDTO`, `OptionValue\DTO\CreatedOptionValueDTO` | Validated input (`optionId`, `code`) and typed results; all are `JsonSerializable`. |
| `ImageRole\Service\ImageRoleServiceInterface` | `create(CreateImageRoleCommand): CreatedImageRoleDTO`, `updateStatus(int $roleId, ImageRoleStatusEnum): void`, `softDelete(int $roleId): void`, `restore(int $roleId): void`, and `getByIdForManagement(int $roleId, ProductDeletedStateEnum = NON_DELETED): ImageRoleDTO`. |
| `ImageRole\Command\CreateImageRoleCommand`, `ImageRole\DTO\ImageRoleDTO`, `ImageRole\DTO\CreatedImageRoleDTO` | Validated input (`roleKey`) and typed results; all are `JsonSerializable`. |
| `ImageAssignment\Service\ImageAssignmentServiceInterface` | `create(CreateImageAssignmentCommand): CreatedImageAssignmentDTO`, `setDefault(int $assignmentId): void`, `clearDefault(int $assignmentId): void`, `softDelete(int $assignmentId): void`, `restore(int $assignmentId): void`, and `getByIdForManagement(int $assignmentId, ProductDeletedStateEnum = NON_DELETED): ImageAssignmentDTO`. |
| `ImageAssignment\Command\CreateImageAssignmentCommand`, `ImageAssignment\DTO\ImageAssignmentDTO`, `ImageAssignment\DTO\CreatedImageAssignmentDTO` | Validated input (`productId`, optional `variantId`, `mediaAssetId` as `int` or digit string, optional `roleId`, `languageCode`, `platform`) and typed results; all are `JsonSerializable`. |
| `Content\Service\ProductContentServiceInterface` | `create(CreateProductContentCommand): CreatedProductContentDTO`, `update(UpdateProductContentCommand): void`, `softDelete(int $contentId): void`, `restore(int $contentId): void`, `getByIdForManagement(int $contentId, ProductDeletedStateEnum = NON_DELETED): ProductContentDTO`, `listForManagement(ProductContentListCriteriaDTO): ProductContentCollectionDTO`, `paginateForManagement(ProductContentListCriteriaDTO, PageRequest): PageResult` (of `ProductContentDTO`), and `listVisible(int $productId, ExactContentLanguage): ProductContentCollectionDTO` (consumer). |
| `Content\Service\OptionContentServiceInterface` | `create(CreateOptionContentCommand): CreatedOptionContentDTO`, `update(UpdateOptionContentCommand): void`, `softDelete(int $contentId): void`, `restore(int $contentId): void`, `getByIdForManagement(int $contentId, ProductDeletedStateEnum = NON_DELETED): OptionContentDTO`, `listForManagement(OptionContentListCriteriaDTO): OptionContentCollectionDTO`, `paginateForManagement(OptionContentListCriteriaDTO, PageRequest): PageResult` (of `OptionContentDTO`), and `listVisible(int $optionId, ExactContentLanguage): OptionContentCollectionDTO` (consumer). |
| `Content\Service\OptionValueContentServiceInterface` | `create(CreateOptionValueContentCommand): CreatedOptionValueContentDTO`, `update(UpdateOptionValueContentCommand): void`, `softDelete(int $contentId): void`, `restore(int $contentId): void`, `getByIdForManagement(int $contentId, ProductDeletedStateEnum = NON_DELETED): OptionValueContentDTO`, `listForManagement(OptionValueContentListCriteriaDTO): OptionValueContentCollectionDTO`, `paginateForManagement(OptionValueContentListCriteriaDTO, PageRequest): PageResult` (of `OptionValueContentDTO`), and `listVisible(int $optionValueId, ExactContentLanguage): OptionValueContentCollectionDTO` (consumer). |
| `Content\Command\CreateProductContentCommand`, `Content\Command\UpdateProductContentCommand`, `Content\Command\CreateOptionContentCommand`, `Content\Command\UpdateOptionContentCommand`, `Content\Command\CreateOptionValueContentCommand`, `Content\Command\UpdateOptionValueContentCommand` | Validated input. Create: the owner identity, `languageCode` (`null` is the unlocalized record), and the fields; update: the Content identity and the complete replacement fields (the owner and language are not part of it). Product Content fields are `name`, `shortDescription`, and `description`; Option and Option Value Content have `name` only. All are `JsonSerializable`. |
| `Content\DTO\ProductContentDTO`, `Content\DTO\OptionContentDTO`, `Content\DTO\OptionValueContentDTO`, `Content\DTO\CreatedProductContentDTO`, `Content\DTO\CreatedOptionContentDTO`, `Content\DTO\CreatedOptionValueContentDTO`, `Content\DTO\ProductContentCollectionDTO`, `Content\DTO\OptionContentCollectionDTO`, `Content\DTO\OptionValueContentCollectionDTO` | Typed results; all are `JsonSerializable`. A Content DTO carries `id`, its owner identity (`productId`, `optionId`, or `optionValueId`), `languageCode`, the fields, and the three timestamps. |
| `Content\DTO\ProductContentListCriteriaDTO`, `Content\DTO\OptionContentListCriteriaDTO`, `Content\DTO\OptionValueContentListCriteriaDTO` | Management criteria: the owner identity, a `ContentLanguageFilter`, `ProductDeletedStateEnum` (`NON_DELETED` by default), and `maxResults` (1 to 100, default 100, applied to the unpaginated list only). |
| `Content\ValueObject\ContentLanguageFilter` | Management language selector with three exclusive states: `exactLanguage(string)`, `unlocalizedOnly()`, and `allLanguages()`; read with `isExactLanguage()`, `isUnlocalizedOnly()`, `isAllLanguages()`, and `languageCode()`; `jsonSerialize()`. |
| `Content\ValueObject\ExactContentLanguage` | Consumer language selector with two states only: `exactLanguage(string)` and `unlocalizedOnly()`; read with `isUnlocalizedOnly()` and `languageCode()`; `jsonSerialize()`. It cannot express all languages. |
| `Product\Exception\ProductNotFoundException`, `Variant\Exception\VariantNotFoundException`, `Option\Exception\OptionNotFoundException`, `OptionValue\Exception\OptionValueNotFoundException`, `ImageRole\Exception\ImageRoleNotFoundException`, `ImageAssignment\Exception\ImageAssignmentNotFoundException`, `Content\Exception\ProductContentNotFoundException`, `Content\Exception\OptionContentNotFoundException`, `Content\Exception\OptionValueContentNotFoundException` | Not-found failures (`FC-NF`): the identity does not exist or is not visible to the read that asked. |
| `Product\Exception\ProductCodeAlreadyExistsException`, `Option\Exception\OptionCodeAlreadyExistsException`, `OptionValue\Exception\OptionValueCodeAlreadyExistsException`, `ImageRole\Exception\ImageRoleKeyAlreadyExistsException`, `ImageAssignment\Exception\ImageAssignmentAlreadyExistsException`, `Content\Exception\ProductContentAlreadyExistsException`, `Content\Exception\OptionContentAlreadyExistsException`, `Content\Exception\OptionValueContentAlreadyExistsException`, `Variant\Exception\VariantCompositionConflictException` | Conflict failures (`FC-CONF`): an identity is already taken, including by a soft-deleted row, or a composition exists in history without an explicit reuse identity. |
| `Common\Exception\ProductInvalidArgumentException` | Input contract violations (`FC-VAL`), including a structurally invalid composition selection. |
| `Common\Exception\ProductBusinessRuleException` | Business-rule failures (`FC-INV`): the row or its owner chain is soft deleted, the row is not soft deleted when restore is asked, a Role is not active for a new assignment, or the operation would leave an invalid Product configuration, including the removal of the last name of an active Product or the last label of an active Option or Value. |
| `Common\Exception\ProductPersistenceException` | Storage returned data that does not match the schema contract, or stored state is inconsistent (`FC-INT`; see Failure contract). Consumers depend on the class and the `ProductExceptionInterface` marker only. The class exposes no named constructors: the package raises it through an internal factory, which is not part of this inventory. |
| `Exception\ProductExceptionInterface` | Marker implemented by every package-defined exception. |

Consumer walkthroughs are in the [Usage Guide](docs/guides/USAGE_GUIDE.md) and runnable code is in [`examples/`](examples/); neither creates a contract beyond this file.

### Behavior and guarantees

- **Create.** `create()` writes an **inactive** Product and its single **active** empty-composition Variant in one local transaction; both rows exist or neither does. The Variant fingerprint is the SHA-256 of `v1`, its `display_order` is allocated through the shared `maatify/persistence` Ordering API under the Product row lock, and it has no SKU or barcode. The result carries both identities. The operation is not idempotent. It performs no Pricing, Inventory, or other cross-domain work.
- **Codes and keys.** `CreateProductCommand::$code`, Option and Option Value codes, and the Image Role key must be valid UTF-8, at most 100 characters, and not empty or whitespace only (any Unicode whitespace). They are stored exactly as supplied, never trimmed. Uniqueness uses the database collation `utf8mb4_unicode_ci` (the Product code in the package namespace, the Option code within its Product, the Value code within its Option, the role key in the role namespace), so `P-1` and `p-1` conflict, and a soft-deleted row keeps its code or key reserved; the only way back is the explicit restore.
- **Read.** `getById()` is the consumer read: it returns only an active, non-deleted Product, and any other identity raises `ProductNotFoundException`. `getByIdForManagement()` ignores status and filters only by soft-deletion state (`NON_DELETED` by default, `INCLUDE_DELETED`, `DELETED_ONLY`), matching `php-category`'s read pair; the same pair exists for Variants, Options, Option Values, Image Roles, and Image Assignments. DTO timestamps are `DateTimeImmutable` values hydrated in the Host Clock timezone, with no normalization, and serialize as `DATE_ATOM`; every DTO rejects a non-positive identity.
- **Variant reads.** `getById()` and `listByProductId()` return only consumer-visible Variants: the Variant and its Product are active and not deleted, every selected Option and Option Value is active, not deleted, and has a non-deleted label, the composition covers exactly the Product's active Options (none missing, none extra), and the Product's configuration is valid as a whole: every active Value of an active Option is represented by at least one active, structurally valid Variant. When that product-wide rule is broken (possible only through direct SQL, because no package write path breaks it), **every** Variant of the Product is hidden from the single get and from the list alike, so the two reads never disagree; management reads keep showing the stored state; and the reads return the correct result again as soon as every active Value is represented (Lead decision on `GAP-26`, proven by `tests/Integration/Runtime/VariantReadTest.php`). `getByIdForManagement()` and `listByProductIdForManagement()` ignore visibility and filter by status (`null` means any) and soft-deletion state. A missing or not-visible identity raises `VariantNotFoundException`; a list read never fails for a missing Product and returns an empty collection. Lists are ordered by `display_order, id` and bounded to at most 100 (`maxResults` 1 to 100). `VariantDTO` carries `id`, `productId`, `sku`, `barcode`, `status`, `displayOrder`, `composition` (the Option and Option Value pairs ordered by numeric Option identity, empty for a simple Product's Variant), and the three timestamps; the composition fingerprint is internal and never exposed. Every identity argument must be a positive integer, otherwise `ProductInvalidArgumentException`.
- **Composition resolution** (`resolveByComposition()`, consumer read, read-only). The selection is a `VariantCompositionCriteriaDTO` of `OptionValueSelection` pairs in any order; it is canonicalized by numeric Option identity, and an empty selection is valid. The classes are the Lead decision on `GAP-08`: a Product that does not exist or is not consumer-visible (inactive or soft deleted) raises `ProductNotFoundException`, judged before the selection. A structurally invalid selection raises `ProductInvalidArgumentException`: a repeated Option (raised by the criteria), an Option that is not an active, non-deleted Option of the Product (including an Option of another Product or an unknown identity), a Value that is not an active, non-deleted Value of the selected Option (including a Value of another Product or paired with the wrong Option), and incomplete coverage (an active Option left unselected; so a non-empty selection on a simple Product and an empty selection on a configurable Product are both rejected). A structurally valid, complete selection that matches no consumer-visible Variant raises `VariantNotFoundException`, including a historical inactive composition and a match hidden by the product-wide rule; the match is looked up by fingerprint among the Product's active, non-deleted Variants and confirmed against the actual pairs, and a fingerprint that matches with different pairs raises `ProductPersistenceException`. It never creates a Variant, never reactivates one, and never invents a default. Proven by `tests/Integration/Runtime/VariantResolutionTest.php`.
- **Lifecycle contract** (Lead decision on `GAP-10`, every family). `updateStatus()`, `softDelete()`, and `restore()` exist for Products, Variants, and Image Roles; `softDelete()` and `restore()` for Options, Option Values, and Image Assignments (their status is changed only by reconfiguration, and an Assignment has no status). A missing identity raises the family's not-found exception; a non-positive identity raises `ProductInvalidArgumentException`. A soft-deleted row accepts nothing but `restore()`: a status change, a re-delete, a default operation, or any other change raises `ProductBusinessRuleException`. A child under a soft-deleted owner cannot be created or changed until the owner chain is restored: an Option or Variant of a deleted Product, a Value of a deleted Option or Product, an Image Assignment of a deleted Product or target Variant (`ProductBusinessRuleException`); `clearDefault()` is the one exception, see the Image Assignments bullet. `restore()` requires the row to be soft deleted and its owner chain not deleted, preserves the saved status (an inactive row stays inactive), and revalidates the Product invariants before writing; a rejected restore leaves the row deleted. Deactivating or deleting a parent never changes a child. Inactivity alone blocks no management operation. A request for the current status changes nothing (no write, `updated_at` unchanged). Product activation requires a non-empty name in a non-deleted Product Content record and a valid configuration (`INV-01` to `INV-06`); Product deactivation needs nothing. A Variant is activated only when its full composition satisfies the current configuration (`INV-09`), and deactivating or deleting it is rejected when the Product would be left without a valid active Variant or a Value would be left unrepresented (`INV-01`, `INV-04`, `INV-07`); such changes belong to reconfiguration. An Option or Value that is part of the active configuration cannot be soft deleted outside reconfiguration, because an active Variant would select a deleted member. Every validation runs on the whole Product graph read under the Product lock. Proven by `tests/Integration/Runtime/ProductLifecycleTest.php`, `VariantLifecycleTest.php`, and `OptionLifecycleTest.php`.
- **Options and Values.** `create()` writes an **inactive** Option under a non-deleted Product (or an inactive Value under a non-deleted Option of a non-deleted Product) with its position from the shared Ordering API within its owner scope, under the Product lock. A duplicate code raises `OptionCodeAlreadyExistsException` or `OptionValueCodeAlreadyExistsException` (MySQL driver error 1062, original `PDOException` as `previous`). A missing owner raises `ProductNotFoundException` or `OptionNotFoundException`; a soft-deleted owner raises `ProductBusinessRuleException`. Their labels are Option and Option Value Content records (see Content).
- **Reconfiguration** (`reconfigure()`, management command, Lead decision on `GAP-15`). The command is the complete target state: the set of Options that must be active with each one's complete set of active Values, and the set of Variant compositions that must be active. Options and Values must exist under the Product, be non-deleted, and be labelled; the operation activates and deactivates them, it never creates them, and it carries no SKU or barcode (new Variants start with `NULL`, `VAR-01` owns them). Every target composition must select exactly the target Options with target Values, no composition may repeat, every target Value must be represented by at least one target Variant, and at least one Variant is required; an empty composition is valid only for a simple target (no Options), and a simple target allows no other composition. An active Variant absent from the target becomes **inactive** and keeps its identity and history. A target composition that already exists in the Product's history (active, inactive, or soft deleted) must name that Variant through `reuseVariantId`, otherwise `VariantCompositionConflictException` identifying the existing Variant; a named Variant that does not carry the composition, carries another composition, or belongs to another Product is also `VariantCompositionConflictException`, and an unknown reuse identity is `VariantNotFoundException`. The match is confirmed against the actual pairs, and a fingerprint that matches with different pairs is `ProductPersistenceException`. A reused inactive Variant is reactivated; a reused soft-deleted Variant is restored and activated inside the same operation; a reused active Variant is unchanged. A new composition becomes a new **active** Variant with its position from the Ordering API. The transition to a simple Product requires the original empty-composition Variant to be named for reuse; when no empty-composition Variant exists in the Product's history, the operation fails closed with `ProductPersistenceException` and never creates a second one (`INV-10`). Options outside the target set are deactivated and their Values are retained unchanged as history; Values of a target Option that are not listed are deactivated. A target that would violate an invariant (an Option or Value of another Product, a deleted or unlabelled Option or Value, incomplete or extra coverage, an unrepresented Value, no Variant, an empty composition in a configurable target or a non-empty one in a simple target) raises `ProductBusinessRuleException`; a repeated Option, Value, composition, or reuse identity in the command raises `ProductInvalidArgumentException` at construction; a missing Product raises `ProductNotFoundException` and a soft-deleted one `ProductBusinessRuleException`. The result lists the Variant identities created (in request order), reactivated, restored, and deactivated, and the Option and Value identities activated and deactivated (ascending); a reused Variant that was already active appears in no list. Every failure rejects the whole operation: nothing is written. Proven by `tests/Integration/Runtime/ProductReconfigurationTest.php` and `ConcurrentReconfigurationTest.php`.
- **Image Roles.** `create()` writes an **active** Role; a duplicate key raises `ImageRoleKeyAlreadyExistsException`. Role IDs are a Product-owned namespace, never Category Image Role IDs. Changing a Role's status or deleting it never changes an assignment.
- **Image Assignments** (Lead decision on `GAP-18`). `create()` assigns a Media Asset to a non-deleted Product (`variantId` null) or to a non-deleted Variant of that Product within an exact `(roleId, languageCode, platform)` scope, where `NULL` is an exact value. A Variant of another Product raises `ProductInvalidArgumentException`; a missing Product, Variant, or Role raises its not-found exception; a soft-deleted Product or Variant, or a Role that is inactive or soft deleted, raises `ProductBusinessRuleException`. The identity `(target, mediaAssetId, roleId, languageCode, platform)` is unique across soft-deleted history under the collation (`ImageAssignmentAlreadyExistsException`, naming the identity; restore is the way back). A new assignment is not the default and takes its position within its exact scope from the shared Ordering API over the generated `ordering_scope` column; the package computes the same key, and an Integration test asserts it equals the stored generated value. `setDefault()` makes a non-deleted assignment the single default of its exact scope, clearing the previous default of that scope in the same transaction, with the database unique key on the default scope as the final guard; it requires a non-deleted target Product and Variant and a non-deleted Role when one is set, while an inactive Product, Variant, or Role does not block it; the current default is a no-op. `clearDefault()` removes the marker of a non-deleted assignment even when its target or Role is soft deleted, promotes nothing, and is a no-op for a non-default; on a soft-deleted assignment it is rejected like every other change. `softDelete()` clears the marker and promotes nothing; `restore()` always leaves the row non-default; a soft-deleted assignment can never become default. The package never resolves or verifies the Media Asset. Proven by `tests/Integration/Runtime/ImageAssignmentCommandTest.php` and `ConcurrentDefaultReplacementTest.php`.
- **Content** (Lead decision on `GAP-13`; `CNT-01` to `CNT-04`, `CNT-R1`, `CNT-R2`). One service per owner (`ProductContentServiceInterface`, `OptionContentServiceInterface`, `OptionValueContentServiceInterface`). A record's identity is `(owner, languageCode)`, where `null` is the single unlocalized record; it is immutable and unique across soft-deleted history under the collation, so `EN` and `en` conflict and a deleted identity is brought back only by `restore()`. `create()` locks the owner's Product configuration, raises the owner's not-found exception (`ProductNotFoundException`, `OptionNotFoundException`, `OptionValueNotFoundException`) for a missing owner and `ProductBusinessRuleException` when the owner or an ancestor is soft deleted (an inactive owner blocks nothing), and maps only MySQL driver error 1062 on the identity key to `ProductContentAlreadyExistsException`, `OptionContentAlreadyExistsException`, or `OptionValueContentAlreadyExistsException` with the original `PDOException` as `previous`; there is no pre-check and no implicit restore. `update()` replaces every field of a non-deleted record by its identity and writes nothing when the fields equal the stored ones. `softDelete()` is rejected when it would remove the last non-deleted name of an active Product (`INV-06`) or the last non-deleted label of an active Option or of an active Value of an active Option (`INV-05`), validated on the locked configuration graph; an inactive owner may lose its last label. `restore()` requires a soft-deleted record and a live owner chain and revalidates the invariants. A soft-deleted record accepts nothing but `restore()`, and no record of a soft-deleted owner chain can be changed. A missing record raises `ProductContentNotFoundException`, `OptionContentNotFoundException`, or `OptionValueContentNotFoundException`. Management reads take a `ContentLanguageFilter` (exact language, unlocalized only, or all languages) and a deleted-state filter, order by `language_code` (the unlocalized record first) then `id`, list at most `maxResults` (1 to 100), and paginate through `maatify/persistence` (`PageRequest`/`PageResult`, 20 per page by default, 1 to 100, sortable by `language_code`, `id`, `created_at`, or `updated_at`, ties by `id`). The consumer read `listVisible()` takes an `ExactContentLanguage` (exact language or unlocalized only) and returns zero or one record: not deleted, under an active, non-deleted Product, and for Option and Value Content an active, non-deleted Option (and Value); otherwise an empty collection. Exact matching never falls back: `ar` never returns the unlocalized record and the unlocalized selector never returns a localized one. Proven by `tests/Integration/Runtime/ContentCommandTest.php`.
- **Identity stability.** Enforced by the application contract (architecture 4.1), not by triggers, and the three layers are distinct. *Schema:* uniqueness of each identity (including soft-deleted history), restricted foreign keys, no triggers or routines; the database does not stop an `UPDATE` of an identity column issued outside the package. *Application:* no package write path updates `id`, `code`, or any other identity column, and no service method replaces one. *Tests:* `tests/Unit/IdentityStabilityTest.php` scans the package's SQL string literals for an `UPDATE ... SET` of an identity column and every service interface for an identity-replacing method; it cannot see SQL assembled at run time or writes made by the Host outside the package, and a schema test asserts that no trigger or routine exists. Corrections use an explicit migration or replacement process outside the package.
- **Transactions.** The package never commits or rolls back a transaction it did not start. Every mutation runs through `PdoSavepointTransactionRunner` (`maatify/persistence` `^1.4`): it owns and commits a transaction when none is active, and when the Host already has an active transaction on the same PDO connection it runs inside an operation-local savepoint in it, so the Host still decides commit or rollback of its own transaction. Atomic composition requires every participant to use that one connection.
- **Locking.** Every mutation that can affect a Product's graph takes the Product row lock (`SELECT ... FOR UPDATE`) before reading any state it validates, then locks, in this stable order, the Product's Options, Option Values, and Variants (ascending identity within each table) when it reads the configuration, and the Image Role and Image Assignment rows it touches (Product, Variant, Role, Assignment); a Content mutation locks the owner's configuration the same way and then its Content row. The configuration graph is read with locking reads only, so it reflects the latest committed state even inside a Host transaction that established an earlier consistent-read snapshot. Validation runs on that graph in memory before any write.
- **Failure contract.** A duplicate identity (MySQL driver error 1062 on a unique key the package owns) becomes the family's conflict exception, with the original `PDOException` as `previous`. A storage value the package cannot interpret, or stored state that is inconsistent (a composition fingerprint that does not match its pairs, a missing original empty-composition Variant during the transition to simple), raises the public, package-owned `ProductPersistenceException` (a `maatify/exceptions` `SystemMaatifyException` that implements `ProductExceptionInterface`). It reports a broken storage contract, not a caller error, so it is not retryable by the caller, and it carries no Product data. Inside a mutation it is raised within the operation's transaction or savepoint, so the failed operation is rolled back like any other failure. `ProductBusinessRuleException` (a `BusinessRuleMaatifyException`) is the `FC-INV` class for every lifecycle precondition and invariant failure. **Every other `PDOException` and `Throwable` propagates unchanged**; nothing is swallowed or blanket-wrapped. When the package started the transaction, it is rolled back before the exception propagates. When the Host already had a transaction on the connection, the failed operation is rolled back to its savepoint before the exception propagates: none of its writes remain in the Host transaction, so a Host that catches the exception and still commits cannot persist a partial operation. The package never commits or rolls back the Host transaction. After a statement-level failure the Host transaction stays active and the Host's other work in it is unaffected. A failure that makes MySQL roll back the whole transaction, such as a deadlock (driver error 1213), ends the Host transaction on the server, including the Host's earlier work in it; the package propagates that `PDOException` unchanged, and the Host must treat it as a failure of its whole transaction.
- **Timestamps.** `created_at`, `updated_at`, and `deleted_at` are taken from the Host-injected `ClockInterface` and stored as the `Y-m-d H:i:s` string of that instant. Reads return `DateTimeImmutable` values hydrated in the Host Clock's timezone. The package performs no timezone normalization and makes no UTC claim.
- **Concurrency.** Product creation serializes on the database unique key for the code and on the Product row lock for the Variant: concurrent creates of one code yield exactly one Product, and concurrent creates of different codes each yield exactly one Variant. Concurrent reconfigurations of one Product serialize on the Product row lock: identical target sets yield one winner and typed conflicts for the rest (never a duplicate-key error), and different target sets leave exactly one valid committed configuration with at most one identity per composition. Concurrent default replacements within one exact scope leave exactly one default. All proven against real MySQL with separate processes and connections by `tests/Integration/Runtime/ConcurrentCreationTest.php`, `ConcurrentReconfigurationTest.php`, and `ConcurrentDefaultReplacementTest.php`.

### Not yet implemented

Management lists, search, and pagination outside Content, SKU and barcode mutation (`VAR-01`), the ordering commands (`VAR-05`, `OPT-05`, `OVL-05`, `IAS-06`), standalone Option and Option Value status changes (`OPT-02`, `OVL-02`), and the consumer reads of Options, Option Values, and Image Assignments (`OPT-R2`, `OVL-R2`, `IAS-R2`). They follow the architecture contract in later Work Units; their approved contract is inventoried in [Approved Contract Inventory](#approved-contract-inventory).

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
| Product Content `name` | Required. At most 255 characters. Not empty or whitespace only (any Unicode whitespace). Valid UTF-8. Stored verbatim, never trimmed. A label, not HTML. **Implemented.** | A§4.1, A§11.2, `GAP-13` decision |
| Product Content `short_description` | Optional (`null` allowed). At most 500 characters. Valid UTF-8. Stored verbatim; an empty string is kept. **Implemented.** | A§11, `GAP-13` decision |
| Product Content `description` | Optional (`null` allowed). At most 65535 bytes (the `TEXT` column). Valid UTF-8. Stored verbatim; an empty string is kept. **Implemented.** | A§11, `GAP-13` decision |
| Option and Option Value Content `name` | The only field. Same rules as the Product Content `name`. **Implemented.** | A§5.4, A§11.2, `GAP-13` decision |
| `display_order` | Positive. Never accepted by Create or Update Commands. Changed only by a dedicated typed ordering command (see [§1.4](#14-ordering)). | A§11.3, Package Building §16 |

For Product, Option, and Option Value codes and for the Image Role key, the collation behavior, the definition of whitespace, and the length unit are settled and implemented (the Option and Value codes and the role key follow the Product code rules); for `language_code` and `platform` the blank and length rules are implemented at the `CreateImageAssignmentCommand` boundary. Package-owned identities are `int` at every command boundary and `media_asset_id` is `int|string` as DEC-005 requires (settled by implementation). The Content field rules are settled by the Lead decision on `GAP-13` and implemented at the Content Command boundary, where the `language_code` rule is the same as for Image Assignments. Collation evidence beyond these identifiers is `GAP-23`.

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
- **Restore** reuses the original identity and preserves the saved `status`. Restoring an `inactive` row never activates it. Restore must revalidate both entity-level and aggregate invariants, and is rejected if they fail (A§6.1, A§6.5). **Implemented** for Products, Variants, Options, Option Values, Image Roles, and Image Assignments.
- **Re-adding a soft-deleted identity (Lead decision, settled).** Create never restores implicitly and never inserts a second row: it fails with a typed conflict that identifies the colliding identity, and the only way back is the explicit restore, which reuses the original identity and revalidates the invariants. This applies to every family that keeps identity across soft-deleted history: Product `code` (implemented: `ProductCodeAlreadyExistsException`, proven by an Integration test), and, when implemented, Option, Option Value, Role, Content, Variant composition, and Image Assignment (`FC-CONF`). It reads A§6.1 and A§9.3 ("restores/reuses ... never a second row") as satisfied by the explicit restore, consistently with A§6.4, A§17, A§5.3, and `php-category`.
- **Lifecycle rules under soft deletion (Lead decision, implemented).** Any change to a soft-deleted row other than the explicit `restore` is rejected with `FC-INV`: no status change, reorder, content change, default operation, or re-delete stands in for restore. Creating or changing a child under a soft-deleted owner (an Option or Variant of a deleted Product, a Value of a deleted Option, an Image Assignment of a deleted Product or target Variant) is rejected with `FC-INV`; the owner chain is restored first. `restore` preserves the saved status, requires the owner chain not to be deleted, and revalidates the invariants before completing. `FC-NF` is for an identity that does not exist; `FC-INV` is for a row that exists but whose state, or whose owner's state, prevents the operation. An `inactive` status alone blocks no management operation and no data preparation; what blocks an operation is this lifecycle contract and the invariants the operation would break.
- Content records (all three owners) follow the same lifecycle: a soft-deleted record accepts only `restore()`, and a record of a soft-deleted owner chain cannot be created or changed. **Implemented.**
- A soft-deleted Image Assignment is never the default: soft deletion clears the marker, restore leaves the row non-default, and the database rejects a soft-deleted default row. The `CHECK` is part of the approved schema contract and a schema guarantee; the operations that maintain it are implemented (`IAS-02` to `IAS-05`).
- No identity-correction API exists in V1. `id` and `code` are never updated (A§4.1). Corrections use an explicit migration or replacement process outside this package.

#### 1.3 Timestamps and the clock

`created_at`, `updated_at`, and `deleted_at` are assigned by the application from the Host-injected `ClockInterface` (`maatify/shared-common`). There is no database timestamp default or `ON UPDATE`. The package performs no timezone normalization and must not claim UTC (A§10, A§11.3). Public timestamps are `DateTimeImmutable` values hydrated in the Host Clock timezone and serialized as `DATE_ATOM`, the same representation as `php-category`. This is implemented for `ProductDTO` and applies to every DTO.

#### 1.4 Ordering

Entities with persistent ordering: Option (scope: Product), Option Value (scope: Option), Variant (scope: Product), Image Assignment (scope: target plus role, language, and platform). `Product` has none (DEC-006).

- Positions are positive and managed by the shared `maatify/persistence` Ordering API (`ScopedOrderingManager`), not by database defaults and not by a package-local engine (A§11.3, Package Building §11, §16).
- Create Commands do not accept a position. A new row gets its position through the Ordering API.
- Movement is a dedicated typed ordering command per entity. The repository returns `bool`, and the service turns `false` into a not-found failure (Package Building §10, §16).
- A target above the current maximum is clamped to the maximum by the shared API. A non-positive target is rejected before clamping (persistence reference). The Command must reject a non-positive position itself (`FC-VAL`).
- Every list whose entity has persistent ordering ends with `display_order, id` (A§12). Content lists are ordered by `language_code` (the unlocalized record first) then `id` (Lead decision, implemented). The default order of the other lists with no `display_order` (Product, Role) is open (`GAP-05`).
- The multi-dimensional Image Assignment scope maps onto the shared API's single scope column through the generated `ordering_scope` key; the package computes the same key and an Integration test asserts equality with the stored value (**implemented**, evidence closes the former `GAP-12`). Open: the position given to a restored row (`GAP-11`).

#### 1.5 Pagination and list bounds

- Every **unpaginated** consumer or management list returns at most 100 records (A§12).
- Large management lists use `maatify/persistence` `PageRequest` and `PageResult`. A package-local pagination engine is forbidden (A§12, Package Building §11).
- The package owns the query semantics: searchable fields, filters, matching, count alignment, and the result contract. The shared package owns only the pagination mechanics (Package Building §11).
- Which lists are paginated, what happens when an unpaginated list would exceed 100, the page-size limit, search semantics, and the default order of lists that have no `display_order` (Product, Role) are not specified (`GAP-04`, `GAP-05`).
- **Content (implemented, Lead decision).** The management Content list is bounded by `maxResults` (1 to 100, default 100). The paginated management Content read uses `PdoPaginator` with 20 records per page by default, 1 to 100, the sort whitelist `language_code` (default, ascending), `id`, `created_at`, `updated_at`, and `id` ascending as the tie-breaker; the total and filtered counts use the same filter as the data, and `maxResults` does not apply to it.

#### 1.6 Exact scope and `NULL`

`NULL` is an exact value, never a wildcard, fallback, "all", or "default" (A§9.3, A§12, Package Building §24).

- **Content** language: three mutually exclusive selectors (exact language, unlocalized only, all languages). "No language filter" and "`NULL`" must not be the same parameter (A§12). **Implemented** as `ContentLanguageFilter` (management) and `ExactContentLanguage` (consumer, without the all-languages state).
- **Image assignment** role, language, and platform: each dimension is matched exactly. `NULL` means exactly `NULL`. Non-`NULL` strings compare under `utf8mb4_unicode_ci`. There is no fallback (A§9.3).
- No Product-level fallback for Variant-level assignments, and no Variant-level substitution for Product-level assignments (A§9.4).
- Management criteria for image-assignment dimensions (omitted versus `NULL`) are not yet specified (`GAP-19`).

#### 1.7 Atomicity, locking, and transactions

- Every multi-table Product mutation is atomic locally (A§14). The package never claims cross-domain atomicity. Pricing, Inventory, Supplier, and fulfilment reconciliation belongs to the Host/Catalog coordinator (A§5.4, A§14).
- Any mutation that can affect Product content validity, Option/Value activation, Variant composition or status, or configuration serializes on the **Product row** before it reads current state (A§6.4, A§11.6). Read-before-write without locking and database uniqueness is not sufficient.
- Image default replacement locks the owning target scope and uses the database unique key as the final guard (A§9.4, A§11.4). Lock order across Product, Variant, Role, and Assignment rows must be stable (A§11.6).
- The package must not commit or roll back an outer transaction it does not own (A§11.6, A§14). It cooperates with the shared transaction contract. No automatic retry is claimed, because the sources permit retry only where the shared contract explicitly allows it (A§11.6).
- Duplicate identity is blocked by database-enforced keys. Application pre-checks are advisory (A§9.4, A§11.1).
- **Implemented for every mutation:** the operation runs through `PdoSavepointTransactionRunner` (`maatify/persistence` `^1.4`). It owns and commits a transaction when none is active. Inside a Host transaction on the same connection it runs in an operation-local savepoint, rolls back to it on failure, and never commits or rolls back the Host transaction, so a failed call leaves none of its writes behind. This keeps the Host transaction usable only after a statement-level failure: when MySQL itself rolls back the whole transaction, such as on a deadlock (driver error 1213), the Host transaction and its earlier work are gone, the `PDOException` propagates unchanged, and the Host must treat it as a failure of its whole transaction (see Failure contract).
- **Lock order (settled, implemented).** For every multi-table mutation: the Product row first, then Options in ascending `id`, then Option Values, then Variants, then Image Roles, then Image Assignments, ascending `id` within each group. The state a mutation builds on is read only after the Product lock is taken, with locking reads.

#### 1.8 Failure classes

The package exposes the marker interface `Maatify\Product\Exception\ProductExceptionInterface` (Package Building §7). Every package-defined exception implements it and uses a `maatify/exceptions` family. The implemented classes are named in the table; the classes for the unimplemented capabilities are not fixed.

| Class | Family | Meaning |
|---|---|---|
| `FC-VAL` | Validation | **Implemented as `ProductInvalidArgumentException`.** An input violates the contract at construction, or a composition selection is structurally invalid. Examples: empty or whitespace-only code, overlength value, malformed language code or `media_asset_id`, non-positive identity, a repeated Option, Value, composition, or reuse identity in one request, an Option or Value that is not an active member of the Product, a Value paired with the wrong Option, incomplete coverage of the active Options. |
| `FC-NF` | NotFound | **Implemented as `ProductNotFoundException`, `VariantNotFoundException`, `OptionNotFoundException`, `OptionValueNotFoundException`, `ImageRoleNotFoundException`, `ImageAssignmentNotFoundException`, `ProductContentNotFoundException`, `OptionContentNotFoundException`, `OptionValueContentNotFoundException`.** The target identity does not exist, or is not visible to the read that asked. An invisible or soft-deleted record reads as not found for a single get; a list returns an empty collection (see 3.9). |
| `FC-CONF` | Conflict | **Implemented as `ProductCodeAlreadyExistsException`, `OptionCodeAlreadyExistsException`, `OptionValueCodeAlreadyExistsException`, `ImageRoleKeyAlreadyExistsException`, `ImageAssignmentAlreadyExistsException`, `ProductContentAlreadyExistsException`, `OptionContentAlreadyExistsException`, `OptionValueContentAlreadyExistsException`, and `VariantCompositionConflictException`.** An identity is already taken, including by a soft-deleted row (SKU and barcode await their commands). A composition that already exists in history without an explicit reuse identity, or a reuse identity that does not match the composition or the Product, is a conflict that identifies the existing Variant (A§5.3). |
| `FC-INV` | BusinessRule | **Implemented as `ProductBusinessRuleException`.** The operation would leave an invalid configuration, or a lifecycle precondition fails: the row or its owner chain is soft deleted, restore is asked for a live row, a new role-scoped assignment names an inactive or deleted Role, a Content deletion would remove a required name or label. See [§2](#2-structural-invariants). |
| `FC-INT` | System | **Implemented as `ProductPersistenceException`.** Storage data breaks the schema contract, or persisted state is inconsistent: a composition digest matches but the pairs differ (A§5.3), or the expected original empty-composition Variant is missing during a configurable-to-simple transition (A§4.5). |

Infrastructure failures: an unclassified `PDOException` or other `Throwable` propagates unchanged and is never blanket-wrapped (implemented). A MySQL duplicate-key error (driver code `1062`) becomes a conflict only for a unique key the package owns and has index-level evidence for; SQLSTATE class `23` is never mapped blindly to a conflict (Package Building §7).

#### 1.9 Shared enums and value objects

| Contract | Kind | Basis |
|---|---|---|
| Status | Enum (`active`, `inactive`) | A§6.1 |
| Management deleted-state filter | `ProductDeletedStateEnum` (`NON_DELETED` by default, `INCLUDE_DELETED`, `DELETED_ONLY`). **Implemented**, same values as `php-category`. | A§12 |
| Management status filter | The family's status enum, nullable: `null` means any status. **Settled** (implemented for Variants). | A§12 |
| Content language selector | `ContentLanguageFilter` (management: exact language, unlocalized only, all languages) and `ExactContentLanguage` (consumer: exact language or unlocalized only). **Implemented.** | A§12 |
| Image dimension selector | Criteria selector | `GAP-19` |
| Option/Value selection pair | `OptionValueSelection` (`optionId`, `optionValueId`, both positive). **Implemented.** | A§5.5 |
| Composition fingerprint | Internal only, not a consumer-facing identifier. **Implemented.** | A§5.3 |
| `media_asset_id` | Validated at the `CreateImageAssignmentCommand` boundary (`int` or canonical digit string, checked before the cast). **Implemented.** | A§9.1, DEC-005 |

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
| `PRD-02` | Command | **Implemented** (`updateStatus(int $productId, ProductStatusEnum $status)`). Change Product status. Activation needs `INV-06`, a valid configuration, and a non-deleted row; a request for the current status changes nothing. | product id, status enum | none | `FC-NF`, `FC-INV` |
| `PRD-03` | Command | **Implemented** (`softDelete(int $productId)`). Soft-delete a non-deleted Product. Children are not changed. | product id | none | `FC-NF`, `FC-INV` (already deleted) |
| `PRD-04` | Command | **Implemented** (`restore(int $productId)`). Restore a soft-deleted Product. Saved status is preserved, and invariants are revalidated (the name requirement when the saved status is active). | product id | none | `FC-NF`, `FC-INV` |
| `PRD-R1` | Management read | Get one Product: **implemented** as `getByIdForManagement(int $productId, ProductDeletedStateEnum = NON_DELETED)`. List and search Products with explicit status and deleted-state filters: not implemented. Unpaginated lists return at most 100. Large lists use `PageRequest`/`PageResult`. | criteria | DTO, or page of DTOs | `FC-NF` for a single get |
| `PRD-R2` | Consumer read | **Implemented** as `getById(int $productId)`: returns only an active, non-deleted Product. | product id | `ProductDTO` | `FC-NF` (a missing or not-visible Product raises `ProductNotFoundException`) |

Rules and edge cases:

- The initial Variant is the only Variant that may exist outside reconfiguration (A§4.5). Creating a Product never activates it, even though its Variant is active.
- `code` is never silently trimmed or rewritten. Case-only differences are not a new code. Equality follows `utf8mb4_unicode_ci`, proven for the Product code by Integration tests. Surrounding whitespace on an otherwise valid code is kept verbatim.
- A Product has no mutable business field other than `status` and lifecycle. There is no slug, price, or `display_order` (A§4.1, DEC-006). So no generic "update Product" command exists in V1 **(derived, `GAP-17`)**.
- Deactivating or soft-deleting a Product never changes its Variants, Options, Values, Content, or Image Assignments (A§6.1, A§17).
- Activating an already active Product, or deactivating an inactive one, changes nothing (implemented as a no-op without a write; see `GAP-14` for the remaining open command shapes).

#### 3.2 Variant

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `VAR-01` | Command | Update SKU and/or barcode with collision validation. The Variant `id` never changes. | variant id, optional new `sku`, optional new `barcode` | none | `FC-NF`, `FC-VAL`, `FC-CONF` |
| `VAR-02` | Command | **Implemented** (`updateStatus(int $variantId, VariantStatusEnum $status)`). Change Variant status. Activation only when the full composition satisfies current invariants (`INV-09`). Deactivation is rejected when it would leave no valid active Variant or an unrepresented Value (`INV-07`). | variant id, status enum | none | `FC-NF`, `FC-INV` |
| `VAR-03` | Command | **Implemented** (`softDelete(int $variantId)`). Soft-delete a Variant. Rejected under the same invariants as deactivation. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-04` | Command | **Implemented** (`restore(int $variantId)`). Restore a Variant. Saved status is preserved. Invariants are revalidated; fingerprint uniqueness is intrinsic to the kept identity. | variant id | none | `FC-NF`, `FC-INV` |
| `VAR-05` | Command | Move a Variant within its Product's ordering scope. | variant id, positive position | none | `FC-NF`, `FC-VAL` |
| `VAR-R1` | Management read | **Implemented.** `getByIdForManagement(int $variantId, ProductDeletedStateEnum = NON_DELETED)` returns one Variant whatever its status. `listByProductIdForManagement(int $productId, VariantListCriteriaDTO)` lists the Variants of a Product filtered by status (`null` means any) and deleted state, ordered `display_order, id`, at most 100. | variant id, or product id and criteria | `VariantDTO`, or `VariantCollectionDTO` | `FC-NF` for a single get |
| `VAR-R2` | Consumer read | **Implemented.** `getById(int $variantId)` and `listByProductId(int $productId, VariantVisibleListCriteriaDTO)` return only consumer-visible Variants (see below), at most 100, ordered `display_order, id`. | variant id, or product id and criteria | `VariantDTO`, or `VariantCollectionDTO` | `FC-NF` for a single get; a list of a missing or not-visible Product is empty |
| `VAR-R3` | Consumer read | **Implemented** (`resolveByComposition(int $productId, VariantCompositionCriteriaDTO $criteria)`). Resolve a composition to one Variant. See `RES-01` in [§3.8](#38-cross-family-contracts). | product id, typed selection | `VariantDTO` | `FC-VAL`, `FC-NF`, `FC-INT` |

Rules and edge cases:

- A Variant belongs to exactly one Product. It carries no price, quantity, or account-type field (DEC-001, DEC-002).
- Non-empty Variants are created only by reconfiguration (`CFG-01`). There is no standalone Create Variant command **(A§4.5)**.
- Composition is immutable after creation. There is no update command for it, and no API accepts a changed composition (A§5.3).
- A change of composition means a new Variant identity and deactivation of the old one. The old identity is never repurposed (A§5.3).
- **SKU/barcode edge cases.** Each is `NULL` or a non-empty string, and an empty string is not stored. A soft-deleted Variant still reserves its values. Replacing or clearing releases the old value. Case-only differences collide. The Command must distinguish "leave unchanged", "set", and "clear to NULL" for each of the two fields (`GAP-14`).
- **Consumer visibility of a Variant (settled, implemented):** the Product is active and not deleted. The Variant is active, not deleted, and structurally valid. Every Option Value in its composition is active, not deleted, labelled, belongs to the selected Option and to the Product, and the composition covers exactly the Product's active Options. A simple Product's single empty-composition Variant is valid because it has no active Options. The Product's configuration must be valid as a whole: when an active Value of an active Option is represented by no active valid Variant, every Variant of the Product is hidden from the consumer reads, the single get and the list alike, and management reads still show the stored state (A§12, A§6.2; Lead decision on the former `GAP-26`).
- Management reads can include inactive and soft-deleted Variants. Consumer reads cannot request deleted records.
- **`VariantDTO` fields (settled).** `id`, `productId`, `sku`, `barcode`, `status`, `displayOrder`, `composition` (the `(optionId, optionValueId)` pairs ordered by numeric `optionId`, empty for a simple Product's Variant), `createdAt`, `updatedAt`, `deletedAt`. The composition is exposed because the Host needs it to map shopper choices to a Variant; the fingerprint is internal and is not exposed (A§5.3). The consumer and management reads return the same DTO.

#### 3.3 Option

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OPT-01` | Command | **Implemented** (`create(CreateOptionCommand)`). Create an Option under a non-deleted Product. Created `inactive`. Position comes from the Ordering API. | product id, `code` | `CreatedOptionDTO` | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` (deleted Product) |
| `OPT-02` | Command | Change Option status. Rejected if the result would break an active configuration unless done by reconfiguration. Not implemented (`GAP-16`); reconfiguration changes Option status. | option id, status enum | none | `FC-NF`, `FC-INV` |
| `OPT-03` | Command | **Implemented** (`softDelete(int $optionId)`). Soft-delete an Option of a non-deleted Product. Rejected while the Option is part of the active configuration. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-04` | Command | **Implemented** (`restore(int $optionId)`). Restore an Option. Saved status is preserved and invariants are revalidated. | option id | none | `FC-NF`, `FC-INV` |
| `OPT-05` | Command | Move an Option within its Product's ordering scope. | option id, positive position | none | `FC-NF`, `FC-VAL` |
| `OPT-R1` | Management read | Get an Option: **implemented** as `getByIdForManagement(int $optionId, ProductDeletedStateEnum = NON_DELETED)`. List Options of a Product with status and deleted-state filters, ordered `display_order, id`: not implemented. | criteria | `OptionDTO`, or list/page | `FC-NF` |
| `OPT-R2` | Consumer read | Selectable Options of an active, visible Product (see 3.9). | product id, bounded criteria | collection of Option DTOs | empty collection |

Rules and edge cases:

- The Option `code` is immutable and stays reserved after soft deletion. An Option has no mutable business field besides `status` and lifecycle, so no generic update command exists **(derived, `GAP-17`)**.
- V1 Options are always Variant-defining and required for every active Variant while active. Optional per-Variant Options are not supported (A§5.1). Descriptive attributes are not Options.
- **Effective status behavior (derived, `GAP-16`).** `INV-01`, `INV-02`, and `INV-07` together mean any standalone change of an Option between `active` and `inactive` leaves an invalid configuration. This is because an active Product configuration always has at least one active Variant. So such a change succeeds only inside reconfiguration. A same-status request is a no-op, and its handling is unspecified.
- An Option can be activated only if it has at least one active, non-deleted Value (`INV-04`) and a label (`INV-05`).

#### 3.4 Option Value

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `OVL-01` | Command | **Implemented** (`create(CreateOptionValueCommand)`). Create a Value under a non-deleted Option of a non-deleted Product. Created `inactive`. Position within the Option scope. | option id, `code` | `CreatedOptionValueDTO` | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` (deleted owner) |
| `OVL-02` | Command | Change Value status. Same constraint as `OPT-02`. Not implemented (`GAP-16`); reconfiguration changes Value status. | value id, status enum | none | `FC-NF`, `FC-INV` |
| `OVL-03` | Command | **Implemented** (`softDelete(int $optionValueId)`). Soft-delete a Value whose owner chain is not deleted. Rejected while an active Variant needs it. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-04` | Command | **Implemented** (`restore(int $optionValueId)`). Restore a Value. Saved status is preserved and invariants are revalidated. | value id | none | `FC-NF`, `FC-INV` |
| `OVL-05` | Command | Move a Value within its Option's ordering scope. | value id, positive position | none | `FC-NF`, `FC-VAL` |
| `OVL-R1` | Management read | Get a Value: **implemented** as `getByIdForManagement(int $optionValueId, ProductDeletedStateEnum = NON_DELETED)`. List Values of an Option with status and deleted-state filters, ordered `display_order, id`: not implemented. | criteria | `OptionValueDTO`, or list/page | `FC-NF` |
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
| `CNT-01` | Command | **Implemented** (`create(Create{Owner}ContentCommand)` on each owner's service). Add a Content record for `(owner, language_code)`, including the `NULL` identity, under a non-deleted owner chain. | owner id, `language_code` or `NULL`, content fields | `Created{Owner}ContentDTO` | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` |
| `CNT-02` | Command | **Implemented** (`update(Update{Owner}ContentCommand)`). Replace every content field of a non-deleted record by its id. The owner and language never change; a request equal to the stored fields writes nothing. | content id, fields | none | `FC-NF`, `FC-VAL`, `FC-INV` |
| `CNT-03` | Command | **Implemented** (`softDelete(int $contentId)`). Soft-delete a non-deleted Content record, unless it is the last required name or label. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-04` | Command | **Implemented** (`restore(int $contentId)`). Restore a soft-deleted Content record under a live owner chain. This is the only way to bring back a soft-deleted identity. | content id | none | `FC-NF`, `FC-INV` |
| `CNT-R1` | Management read | **Implemented** (`getByIdForManagement(int $contentId, ProductDeletedStateEnum)`, `listForManagement({Owner}ContentListCriteriaDTO)`, `paginateForManagement({Owner}ContentListCriteriaDTO, PageRequest)`). List Content of an owner with one of three selectors: exact language, unlocalized only, all languages, and a deleted-state filter. | owner id, `ContentLanguageFilter`, deleted state, bound or page | `{Owner}ContentCollectionDTO` or `PageResult` | `FC-NF` (single get), `FC-VAL` |
| `CNT-R2` | Consumer read | **Implemented** (`listVisible(int $ownerId, ExactContentLanguage)`). Exact-language read, or exact unlocalized read. The all-languages selector is not available to consumers (A§12). | owner id, `ExactContentLanguage` | `{Owner}ContentCollectionDTO` of zero or one DTO | empty collection |

Rules and edge cases:

- **Fields (Lead decision on `GAP-13`, implemented).** Product Content has `name` (required, at most 255 characters, not empty or whitespace only under any Unicode whitespace), `shortDescription` (optional, at most 500 characters), and `description` (optional, at most 65535 bytes). Option and Option Value Content have `name` only, with the same rules. Values are valid UTF-8 and stored verbatim, never trimmed; an empty optional string is kept and differs from `null`. `name` is a label, not HTML. The package stores and returns values only, and never renders or sanitizes markup (A§4.1, A§11). A violation is `ProductInvalidArgumentException` at Command construction.
- **Identity.** `(owner_id, language_code)` is immutable and unique across soft-deleted history, including the single `NULL` row (A§4.1, A§6.4, A§11.2).
- **Exactness.** An exact-language read for `'ar'` never returns `NULL` content or another language. An exact unlocalized read never returns a localized record. There is no implicit fallback of any kind (A§12, DEC-004).
- **Selectors.** The three selectors are distinct, mutually exclusive typed states. Omitting a language filter and passing `NULL` are different intents and must not share a parameter (A§12).
- **Language edge cases.** `'EN'` and `'en'` are the same identity under the collation. Creating the second is a `FC-CONF`. An empty or whitespace-only non-`NULL` language is a `FC-VAL`.
- **Required labels.** Removing or soft-deleting the last non-empty name of an **active** Product is rejected (`INV-06`, `FC-INV`). The same protection applies to the last label of an active Option, and of an active Value of an active Option (`INV-05`). An inactive owner may lose its last label. **Implemented**, validated on the locked configuration graph.
- **Consumer visibility.** A Content record is visible only if it is not deleted and its owner chain is visible: an active, non-deleted Product, and for Option/Value Content an active, non-deleted Option (and Value).
- **Re-adding.** Creating a Content identity that exists in soft-deleted history is rejected with the owner's `*ContentAlreadyExistsException` and an explicit restore is required (A§17), as for every identity family (see the Lead decision in the status and restore rules). **Implemented.**
- **Order and bounds.** Management lists order by `language_code` (the unlocalized record first) then `id` and return at most `maxResults` (1 to 100); the paginated read uses `maatify/persistence` with 20 per page by default and at most 100 (see 1.5).

#### 3.6 Image Roles

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `IRL-01` | Command | **Implemented** (`create(CreateImageRoleCommand)`). Create a Product Image Role. Created `active`. The package ships no seeded roles. | `role_key` | `CreatedImageRoleDTO` | `FC-VAL`, `FC-CONF` |
| `IRL-02` | Command | **Implemented** (`updateStatus(int $roleId, ImageRoleStatusEnum $status)`). Change the status of a non-deleted Role; the current status is a no-op. | role id, status enum | none | `FC-NF`, `FC-INV` (deleted) |
| `IRL-03` | Command | **Implemented** (`softDelete(int $roleId)`). Soft-delete a non-deleted Role. | role id | none | `FC-NF`, `FC-INV` (already deleted) |
| `IRL-04` | Command | **Implemented** (`restore(int $roleId)`). Restore a Role. Same identity, same saved status. | role id | none | `FC-NF`, `FC-INV` (not deleted) |
| `IRL-R1` | Management read | Get a Role: **implemented** as `getByIdForManagement(int $roleId, ProductDeletedStateEnum = NON_DELETED)`. List and search Roles with status and deleted-state filters: not implemented. | criteria | `ImageRoleDTO`, or page | `FC-NF` |

Rules and edge cases:

- Role IDs are Product-owned. They are never Category Image Role IDs, and the two namespaces are never mixed (A§9, DEC-005).
- A Role has no mutable business field besides `status`. The Host defines what a role means. There is no `hero` or `gallery` meaning in the package **(A§9.2; no update command, derived, `GAP-17`)**.
- A new role-scoped assignment needs an active, non-deleted Role. Existing assignments are not changed when the Role becomes inactive or deleted. They disappear from consumer reads and stay in management reads (A§9.2).
- `role_id = NULL` on an assignment is the exact generic/unclassified scope, not a "no role requested" fallback.
- No consumer read of the Role registry is enumerated. Consumers read assignments, not roles (see 3.9).

#### 3.7 Image Assignments

| ID | Kind | Contract | Input | Output | Fails |
|---|---|---|---|---|---|
| `IAS-01` | Command | **Implemented** (`create(CreateImageAssignmentCommand)`). Create an assignment of one external Media Asset to a non-deleted Product (target `variant_id = NULL`) or to one non-deleted Variant of that Product, within an exact `(role, language, platform)` scope; a role-scoped assignment needs an active, non-deleted Role. Never the default. | product id, optional variant id, `media_asset_id`, optional role id, `language_code`, `platform` | `CreatedImageAssignmentDTO` | `FC-NF`, `FC-VAL`, `FC-CONF`, `FC-INV` |
| `IAS-02` | Command | **Implemented** (`setDefault(int $assignmentId)`). Set the default assignment of an exact scope: atomically clears the previous default of that scope and sets the requested one in one transaction, serialized on the target scope, with the database unique key as the final guard. Requires a non-deleted assignment, a non-deleted target Product and Variant, and a non-deleted Role when one is set; an inactive Product, Variant, or Role does not block it. The current default is a no-op. | assignment id | none | `FC-NF`, `FC-INV` |
| `IAS-03` | Command | **Implemented** (`clearDefault(int $assignmentId)`). Clear the default of an exact scope: clears the marker of the given non-deleted assignment even when its target or Role is soft deleted, promotes no other row, and is a no-op for a non-default row. Adopted from `php-category` (`clearDefault`) as an independent, explicit operation. | assignment id | none | `FC-NF`, `FC-INV` (deleted assignment) |
| `IAS-04` | Command | **Implemented** (`softDelete(int $assignmentId)`). Soft-delete a non-deleted assignment whose target is not deleted. If it was the default, the default marker is cleared and no other row is promoted. | assignment id | none | `FC-NF`, `FC-INV` |
| `IAS-05` | Command | **Implemented** (`restore(int $assignmentId)`). Restore a soft-deleted assignment whose target is not deleted. It is always restored as non-default. | assignment id | none | `FC-NF`, `FC-INV` |
| `IAS-06` | Command | Move an assignment within its exact ordering scope. | assignment id, positive position | none | `FC-NF`, `FC-VAL` |
| `IAS-R1` | Management read | Get an assignment: **implemented** as `getByIdForManagement(int $assignmentId, ProductDeletedStateEnum = NON_DELETED)`. List assignments of a target, with deleted-state filter, ordered `display_order, id`: not implemented. | criteria | `ImageAssignmentDTO`, or list/page | `FC-NF` for a single get |
| `IAS-R2` | Consumer read | Exact-scope read: target (Product or Variant) plus `role_id`, `language_code`, `platform`, each matched exactly. | exact scope | list of DTOs, at most 100 | |

Rules and edge cases:

- **Target and integrity.** A target is the Product or one Variant of that same Product. A Variant of another Product is rejected by the database through the composite key `(variant_id, product_id)`. A Product-level assignment is still anchored to an existing Product through the independent `product_id` key (A§9.3, A§11.1).
- **Identity.** `(target kind, target identity, media_asset_id, role_id, language_code, platform)` is immutable and unique across soft-deleted history. The same Media Asset may be assigned to another target or another exact scope (A§9.3).
- **Default.** At most one active default per exact `(target, role, language, platform)` scope. Default is independent of `display_order`. Soft-deleting or clearing a default never promotes another row. Restore always leaves a row non-default; making it default again is the explicit set-default operation (`IAS-02`), exactly as in `php-category` (A§9.4, A§11.4).
- **Concurrency.** A default replacement is protected by transaction locking and by a database unique key that treats `NULL` as a real scope value. A plain unique key containing `NULL` is not sufficient (A§9.4, A§11.4).
- **Consumer visibility.** The Product is active and not deleted. For a Variant target, the Variant is active and not deleted. If `role_id` is non-`NULL`, the Role is active and not deleted. The assignment is not deleted. An inactive or deleted ancestor hides the assignment without changing it (A§9.3, A§12).
- **No inheritance.** A Product-level read does not return Variant-level assignments, and a Variant-level read does not return Product-level assignments (A§9.4).
- **Re-adding.** Creating an assignment whose identity exists in soft-deleted history fails with `FC-CONF` identifying the identity; `IAS-05` is the explicit restore (Lead decision, as above), exactly as in `php-category`.
- **No media check.** The package never uploads, resolves, transforms, or verifies the Media Asset (A§9.4, DEC-005).
- **Default operations (settled).** `IAS-02` and `IAS-03` apply to a non-deleted assignment whatever the status of its Role or target (the sources restrict only the creation of a role-scoped assignment to an active Role, A§9.2), and an inactive Role or target only hides the assignment from consumer reads. A soft-deleted assignment cannot be made default (the database rejects it). Set and clear are independent explicit operations, as in `php-category`; creation never selects a default and `display_order` changes never alter it.
- **Default operations under a soft-deleted target (Lead decision, implemented).** `setDefault` requires a non-deleted assignment, a non-deleted target Product and Variant, and a non-deleted Role when one is set; `clearDefault` removes the marker of a non-deleted assignment even when its target or Role is soft deleted, promotes nothing, and creates no new default; a soft-deleted assignment can never become default, and no operation promotes a replacement row. A create never sets `is_default`. The management criteria remain open (`GAP-19`).

#### 3.8 Cross-family contracts

##### `RES-01` Variant composition resolution (Consumer read, read-only)

**Implemented** as `VariantServiceInterface::resolveByComposition(int $productId, VariantCompositionCriteriaDTO $criteria): VariantDTO`. Resolves a shopper's option choices to one concrete `variant_id`. The Host owns presentation and when to call it. The package owns whether the selection is a real, active, valid Variant (A§5.5).

| Aspect | Contract |
|---|---|
| Input | `product_id` and a typed collection of selection pairs `(option_id, option_value_id)`. No associative array is accepted. An empty collection is valid input. |
| Order | Order-independent. Pairs are canonicalized by numeric `option_id` (A§5.3). |
| Output | The single matching active, non-deleted Variant identity (`VariantDTO`, see 3.9). |
| Rejects (`FC-VAL`, `ProductInvalidArgumentException`; Lead decision, implemented) | A repeated Option. An Option or Value from another Product. A Value paired with the wrong Option. An inactive or soft-deleted Option or Value. Incomplete coverage of the active Options. |
| Coverage | For a configurable Product, exactly one active Value for every active Option, with none missing and none extra. |
| Simple Product | An empty selection returns the single active empty-composition Variant, provided the Product and Variant are visible to consumers. A non-empty selection names Options that are not active, so it is rejected **(derived from A§5.5 items 3 and 4)**. |
| Product not visible | A Product that does not exist or is not consumer-visible (inactive or soft deleted) is `FC-NF` (`ProductNotFoundException`), judged before the selection. |
| No match | A structurally valid, complete composition with no matching consumer-visible Variant is `VariantNotFoundException`, including a historical inactive composition and a match hidden by the product-wide visibility rule. **Implemented.** |
| Never | It never creates a Variant, never reactivates a historical one, and never invents a default. A matching digest with different pairs is `FC-INT` (`ProductPersistenceException`). |

Examples of composition safety:

- A configurable Product with active Options `{color, size}` and a request carrying only `color` is rejected as partial coverage, even if exactly one Variant has that color.
- A request containing a Value of an inactive Option is rejected, even though a historical inactive Variant still contains it.
- A request equal to a historical inactive Variant's composition returns no active Variant. It does not reactivate it.

##### `CFG-01` Product reconfiguration (Management command, atomic)

**Implemented** as `ProductServiceInterface::reconfigure(ReconfigureProductCommand $command): ProductReconfigurationResultDTO`. The only way to change the active Option set or the active Variant set, and the only way to create non-empty Variants. It is a Product-local atomic operation, never a sequence of ordinary Option calls (A§4.5, A§5.4, A§6.5).

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
- Its result lets the Host see the deterministic old and new Variant identities (A§14): `ProductReconfigurationResultDTO` lists the Variant identities created (in request order), reactivated, restored, and deactivated, and the Option and Option Value identities activated and deactivated.

**Reconfiguration contract (Lead decision on `GAP-15`, implemented):**

- **Input.** `ReconfigureProductCommand(productId, options, variants)`: `options` is a list of `ReconfigureOptionTarget(optionId, activeOptionValueIds)`, the complete target set of active Options each with the complete set of its active Values; `variants` is a list of `ReconfigureVariantTarget(composition, reuseVariantId)`, the complete target set of active Variant compositions as `OptionValueSelection` pairs with an optional reuse identity. The command is a complete target state, not a list of deltas. It carries no `sku` or `barcode`: new Variants start with `NULL`, and `VAR-01` owns those identifiers.
- **Prepared members only.** Options and Values must already exist under the Product, be non-deleted, and have a label; the operation activates and deactivates them and never creates them. Options outside the target set are deactivated and their Values are kept unchanged as history; Values of a target Option that are not listed are deactivated.
- **Variants.** An active Variant absent from the target becomes `inactive` and keeps its history. A composition that already exists in the Product's history (active, inactive, or soft deleted) is reused only when `reuseVariantId` names that Variant, after confirming the actual pairs and the owning Product; an inactive one is reactivated and a soft-deleted one is restored, revalidated, and activated inside the same operation. A new composition becomes a new active Variant. The transition to a simple Product names the original empty-composition Variant; a second empty-composition identity is never created, and a configurable target allows no empty composition.
- **Rejections.** Duplicate compositions, incomplete or extra coverage of the target Options, Values of another Product or outside the target set, an unrepresented target Value, and a target with no active valid Variant are rejected, and every failure rejects the whole operation with no partial write.
- **Failure classes.** A malformed typed input or a repetition inside the request: `FC-VAL`. An existing composition without a reuse identity, or an identity conflict: `FC-CONF` (`VariantCompositionConflictException`). A target that violates an invariant: `FC-INV`. A fingerprint that does not match the stored pairs, or a missing original empty-composition Variant for the transition to simple: `FC-INT`. A missing Product: `FC-NF`; an unknown reuse identity: `VariantNotFoundException`.
- **Transaction and locks.** `PdoSavepointTransactionRunner`; the Product row is locked first, then Options, Option Values, and Variants in ascending `id`, before any state the operation builds on is read; every Product-local invariant is validated before commit.

**Reconfiguration model (derived from the architecture, now settled as above):**

| Question | Answer | Basis |
|---|---|---|
| Target state or deltas | **Declarative target state.** The input is the intended active Option and Value set plus the proposed Variant compositions; validation is against that whole set. | A§5.4 items 1 to 3 validate "the intended new active set" and "every proposed composition" |
| An active Variant omitted from the proposed set | It is **deactivated, never deleted**; its identity and history stay. | A§5.4 item 4 ("deactivates replaced Variants without deleting historical identities") |
| Creating Options and Values inside the operation | **Not allowed.** They are created inactive through their own commands so codes and labels can be prepared; the operation only activates or deactivates prepared ones. | A§5.4 first bullet, A§6.1 |
| Soft delete and restore inside the operation | **No soft delete.** Options and Values are retained as history and deactivated. The only restore is the explicit restore of the Product's original empty-composition Variant (and of a named historical Variant). | A§4.5, A§6.5 |
| State of replacement Variants | Non-empty Variants are created **active** as members of the validated set; a historical Variant becomes active again only when named explicitly and revalidated. | A§4.5, A§5.3, A§6.3 |
| Transaction and locking | One local transaction through the savepoint runner; the Product row is locked first, before any read of the current configuration; all invariants (`INV-01` to `INV-10`) are validated before commit and any failure rejects the whole operation. | A§6.5, A§11.6, A§14 |
| Type shapes and lock order | Settled by the Lead decision above and implemented. | A§11.6 |

#### 3.9 Consumer read contracts

The consumer reads the architecture requires (A§12, A§13), with their input, result, and absence semantics. A single get raises a typed not-found exception, a list returns an empty collection, and every list is bounded to at most 100 records. The exact PHP names of the unimplemented types follow the suffix rules of Package Building §4 and the `php-category` convention.

| Read | Input | Result | Absent or not visible | Visibility rule | State |
|---|---|---|---|---|---|
| Product (`PRD-R2`) | product id | `ProductDTO` | `ProductNotFoundException` | Product active and not deleted | Implemented |
| Variant (`VAR-R2`) | variant id | `VariantDTO` | `VariantNotFoundException` | Product, Variant, Options, Values valid and active, and every active Value represented (see Variant rules) | Implemented |
| Variant list (`VAR-R2`) | product id, bounded criteria | `VariantCollectionDTO` | empty collection | as above | Implemented |
| Composition resolution (`RES-01`) | product id and `VariantCompositionCriteriaDTO` of `OptionValueSelection` pairs, never an array | `VariantDTO` of the single matching visible Variant | `ProductNotFoundException` for an invisible Product; `ProductInvalidArgumentException` for a structurally invalid selection; `VariantNotFoundException` when a valid composition matches no visible Variant | as `VAR-R2`; an empty selection resolves a simple Product's Variant | Implemented |
| Selectable Options (`OPT-R2`) | product id, bounded criteria | collection of Option DTOs (`id`, `productId`, `code`, `displayOrder`; the label comes from the exact Content read) | empty collection | Product visible; Option active, not deleted, labelled | Specified |
| Selectable Option Values (`OVL-R2`) | option id, bounded criteria | collection of Option Value DTOs (`id`, `optionId`, `code`, `displayOrder`) | empty collection | Option visible; Value active, not deleted, labelled, and represented by at least one visible Variant (A§12) | Specified |
| Exact Content (`CNT-R2`) | owner id and `ExactContentLanguage`: exact language, or unlocalized only (never all languages) | `{Owner}ContentCollectionDTO` of zero or one Content DTO | empty collection; never falls back to another language or to the unlocalized record | owner chain visible (active, non-deleted Product, and Option and Value for their Content); record not deleted | Implemented |
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

1. **Catalog setup.** Create a Product (`PRD-01`). Add Content with a name (`CNT-01`). Activate it (`PRD-02`).
2. **Make it configurable.** Create Options (`OPT-01`) and Values (`OVL-01`) while inactive. Add labels (`CNT-01`). Run reconfiguration (`CFG-01`) with the offered combinations.
3. **Shopper selection.** The Host reads the visible Variants (`VAR-R2`), collects the shopper's choices, and calls resolution (`RES-01`). It gets one `variant_id`, and passes that to Pricing, Inventory, or an order.
4. **Presentation.** The Host reads exact Content (`CNT-R2`) and exact-scope image-assignment (`IAS-R2`), applying its own fallback.

### 6. Open Contract Gaps

Each gap is open until a decision or evidence closes it, and the closing change updates this file in place; a closed gap is removed (the source topology, the timestamp representation, infrastructure-exception propagation, the status filter, absence semantics, the consumer read surface, the resolution failure classes, the lifecycle rules under soft deletion, the default operations under a deleted target, the reconfiguration contract, the lock order, the image-assignment scope key, the identity input type, and the read-time treatment of an unrepresented Value, and the Content fields and value limits (`GAP-13`) are closed and appear above as settled contracts). "Needs" names the kind of resolution. It is advisory, and authority follows `std-ai-collaboration-workflow` §3.2.1: a public-contract choice belongs to the Lead and, where it changes the approved contract, the Owner. An implementer must not guess on any of these.

| ID | Gap | Sources and what is missing | Impact | Needs |
|---|---|---|---|---|
| `GAP-02` | Exact PHP type names for the unimplemented capabilities (lists and search, ordering commands, SKU and barcode mutation). The root namespace `Maatify\Product\` is fixed by Package Building §4 and the production autoload in `composer.json`; the implemented types are listed in the Public API table. | Illustrative names only (the illustrative-names table above). | Each new contract signature. | Technical decision when each type is written |
| `GAP-04` | Management search semantics. Searchable fields, matching mode, escaping, sort whitelist, count alignment. Lookup by business key (Product code, SKU, barcode, role key, Option/Value code) is unspecified. | Package Building §11 makes these package-owned. A§12 names "search" without defining it. | Management list/search contracts. | Contract decision |
| `GAP-05` | List bounding and pagination. Which lists are paginated. Behavior when an unpaginated list would exceed 100 (truncate or reject). Page-size limit versus the shared default of 200. Default order for lists with no `display_order` (Product, Role). Content is settled by the Lead (see 1.5). | A§12, DEC-006, persistence reference. | Every list. | Contract decision |
| `GAP-11` | Restore and `display_order`. A restored row's position (original, or next position) and duplicate positions are unspecified. The shared Ordering API ignores soft-deleted rows. | A§9.4, A§11.3, persistence reference. | Restore of Option, Value, Variant, Assignment. | Contract decision |
| `GAP-14` | Command shapes the sources leave open. Settled and implemented: Product creation takes only `code`; reconfiguration-created Variants take no SKU or barcode; `IAS-01` never sets `is_default`; a same-status request changes nothing. Open: "leave, set, clear" for the `VAR-01` identifiers. | A§4.2. | `VAR-01`. | Contract decision |
| `GAP-16` | Standalone Option and Value status changes. Derived: an Option status change always breaks an invariant outside reconfiguration, and a Value status change is valid only under an inactive Option. Whether these are public commands or only reconfiguration internals is not stated; today reconfiguration is the only status change and `OPT-02`/`OVL-02` are not implemented. | A§5.4, A§6.2, A§6.4, A§6.5 (derivation in §3.3, §3.4). | `OPT-02`, `OVL-02`. | Lead confirmation |
| `GAP-17` | No generic update commands. Derived: Product, Option, Option Value, and Role have no mutable business field. Confirm no `Update*` command is intended. | A§4.1, A§5.2, A§9.2, DEC-006. | Command inventory. | Lead confirmation |
| `GAP-19` | Image assignment management criteria. Omitted dimension versus `NULL` versus value for role, language, platform. Whether a Role is identified by id or by key. | A§9.3, A§12. | `IAS-R1`, `IAS-R2`. | Contract decision |
| `GAP-23` | Collation consequences for identity beyond case folding. Case-insensitive equality under `utf8mb4_unicode_ci` is proven for the Product, Option, and Value codes, the role key, and the image-assignment scope strings; accent-insensitive and trailing-space behavior versus the PHP pre-check is not. | A§4.1, A§5.2, A§9.2. | Identifier Commands and conflicts. | Evidence (MySQL integration) |
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
| **Consumer reads** | Variant listing for a Product, Variant composition resolution (§5.5), exact Content reads, and exact-scope image-assignment reads. | Return only records valid under the visibility invariants: an active, non-deleted Product with structurally valid, active, non-deleted Variants and Options. A Variant appears only if every Option Value in its composition is active and not soft-deleted, belongs to the selected Option and to that Product, and the composition covers exactly the Product's active Options, one value per active Option with none missing, extra, or invalid (§5.5, §6.2, §6.3), and only while every active Value of an active Option of the Product is represented by such a Variant. A simple Product's single empty-composition Variant is valid because it has no active Options. An inactive or deleted ancestor suppresses its children without changing them. Consumer reads cannot request deleted records or bypass these rules. |

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

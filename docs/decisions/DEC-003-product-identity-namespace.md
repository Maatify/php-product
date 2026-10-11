# DEC-003 — Product Identity Namespace Boundary for Product V1

- **Decision ID:** `DEC-003`
- **Title:** Product Identity Namespace Boundary for Product V1
- **Status:** `ACTIVE`
- **Date:** `2026-10-11`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Product identity namespace boundary; Product code uniqueness; exclusion of Catalog entity and tenant_id fields from table set; independence of presentation and category organization
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§4.1, §20)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

During catalog and product architecture discussions for `Product V1`, questions arose regarding the scope of the Product Identity Namespace, the possibility of multi-tenancy or multiple Catalogs sharing the same database tables, and mapping products across multiple categories and presentation storefronts (such as customer, distributor, or enterprise interfaces).

This issue was raised as an Owner Decision Gate in the candidate architecture document ([`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) §20 Gate 3) to determine whether `Product V1` should introduce abstractions such as `Catalog` or fields like `catalog_id` / `tenant_id`, or confine itself strictly to a single, well-defined identity namespace.

---

## Decision

The project Owner formally approved the following decision on 2026-10-11:

### Owner-Approved Source Text (Arabic Original)

> تعتمد Product V1 مساحة هوية واحدة لكل مجموعة جداول Product منشورة. يكون `product_id` هوية المنتج الداخلية المرجعية، ويكون `Product.code` فريدًا داخل مساحة الهوية هذه.
>
> لا تنشئ Product V1 كيانًا مستقلًا باسم Catalog، ولا تضيف `catalog_id` أو `tenant_id` إلى نموذج المنتج لمجرد احتمال الاحتياج المستقبلي. ولا تدعم V1 مساحات هوية مستقلة متعددة للمنتجات داخل مجموعة الجداول نفسها.
>
> تظل علاقة المنتج بالأقسام، وإتاحة المنتجات والأقسام حسب الواجهة أو الجمهور، وطريقة تنظيم العرض مسؤوليات خارج المكتبتين الأساسيتين Product وCategory. ويمكن للتطبيق المستهلك إضافة هذه القدرات عند الحاجة، دون فرض إعداداتها على التطبيقات التي تستخدم النموذج الأساسي البسيط.
>
> إذا ظهر مستقبلًا احتياج إلى مساحات هوية مستقلة تشترك في مجموعة الجداول نفسها، فيلزم اتخاذ قرار معماري منسق قبل تغيير مخطط البيانات أو الادعاء بدعم هذا السيناريو.

### English Translation

> Product V1 adopts a single identity namespace per deployed set of Product tables. The `product_id` serves as the internal canonical product identity, and `Product.code` is unique within this identity namespace.
>
> Product V1 does not create an independent entity named Catalog, and does not add `catalog_id` or `tenant_id` to the product model merely for speculative future requirements. Nor does V1 support multiple independent product identity namespaces within the same set of tables.
>
> Product-to-category relationships, product and category availability by interface or audience, and presentation organization remain responsibilities outside the two core libraries Product and Category. Consuming applications may add these capabilities when needed, without imposing their configuration on applications that use the simple core model.
>
> If a future requirement emerges for independent identity namespaces sharing the same set of tables, a coordinated architectural decision must be made prior to modifying the schema or claiming support for that scenario.

### Key Dimensions of the Approved Decision

1. **Single Identity Namespace per Deployed Table Set:** The `php-product` library assumes and adopts a single unified identity namespace per deployed set of `maa_product_*` tables, where `product_id` is the internal canonical reference and `Product.code` is unique and non-duplicated across the entire namespace and its history.
2. **Exclusion of Catalog Entity and Speculative Tenant Fields:** The library creates no entity or table named Catalog, and adds no columns such as `catalog_id`, `tenant_id`, or `store_id` to the product model or its related tables merely out of speculative future expectation.
3. **No Multi-Namespace Support Within Shared Tables in V1:** V1 does not support sharing the same table set across independent catalogs or namespaces that reuse product codes. Multi-namespace scenarios require deploying isolated table sets or managing isolation at the host application layer outside `php-product`.
4. **Independence of Presentation, Categories, and Availability:** Product-to-category mapping, product/category availability by storefront or audience, and display categorization remain strictly outside the core `Product` and `Category` libraries.
5. **Consumer Application Flexibility Without Imposed Overhead:** Consuming applications or orchestration layers may introduce multi-interface availability and audience filtering according to their specific needs, without imposing unnecessary complexity, fields, or configuration on applications using the simple baseline model.
6. **Coordinated Architectural Decision Required for Future Expansion:** Should an actual need emerge for independent namespaces sharing the same table set, an explicit coordinated architectural decision must be approved prior to altering the database schema or claiming support for that scenario.

---

## Rationale

1. **Simplicity and Avoidance of Speculative Complexity (YAGNI):** Preemptively adding catalog and tenant fields without an operational requirement introduces schema complexity, composite keys, and indexing overhead across all queries, burdening simple use cases.
2. **Separation of Concerns:** The `php-product` package is responsible for defining commercial products and their intrinsic attributes; organizing, filtering, and presenting products across interfaces or sales channels belongs to orchestration or host layers.
3. **Preserving Library Independence and Purity:** Keeping multi-interface availability and presentation rules outside the core libraries ensures that `php-product` and `php-category` remain focused, decoupled packages without circular or unnecessary dependencies.
4. **Consumer Application Flexibility:** Enables consuming applications to design presentation structures and audience filters suitable for their workflows, while preserving a straightforward, configuration-light experience for standard implementations.

---

## Consequences

### Positive Consequences

- The `php-product` data model remains clean, focused, and free from `catalog_id`, `tenant_id`, or `store_id` columns.
- The uniqueness constraint on `Product.code` is globally enforced across the table without composite keys involving tenants or catalogs.
- Avoids imposing multi-storefront or audience availability configuration on standard consumers.
- Explicit demarcation between core library responsibilities and the host/orchestration layer.

### Follow-up Requirements

- Specifications and implementations of `php-product` V1 must not introduce multi-catalog or tenant fields within shared tables.
- The consuming application or Catalog coordinator manages product-to-category mappings and interface-specific visibility policies.
- Pricing policies by account type or sales surface remain entirely outside Product and are governed by Pricing per `DEC-001`.
- The architecture candidate document (`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`) remains classified as `Architecture Candidate` until the remaining Owner decision gates are resolved.

---

## Boundaries & Non-Goals

This decision is strictly confined to the product identity namespace, adopting a single namespace per deployed table set, and excluding Catalog and Tenant columns from Product V1. The following areas remain explicitly out of scope:

- Storefront and presentation layer schemas or channel management.
- Schema and APIs for product-to-category assignments.
- Rules and policies for multi-interface or audience-based catalog availability in consuming applications.
- Pricing rules and policies for user/account types (governed entirely by Pricing under `DEC-001`).
- Resolution of the remaining Owner decision gates.

# DEC-001 — Variant-Addressed Pricing Boundary for Product V1

- **Decision ID:** `DEC-001`
- **Title:** Variant-Addressed Pricing Boundary for Product V1
- **Status:** `ACTIVE`
- **Date:** `2026-10-11`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Product pricing boundary; adoption of Variant as the pricing reference identity; separation of Product library from independent Pricing library
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§7, §20)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

In the historical Catalog V1 specification, selling price calculation depended on a formula that included a Product Base Price plus price adjustments associated with Option Values.

During the architecture review and specification of `Product V1` as an independent, reusable Composer package decoupled from host projects, pricing ownership and structure were raised as an Owner Decision Gate in the candidate architecture document ([`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) §20 Gate 1).

This required explicit resolution of domain boundaries:
1. Does the Product library own prices, currencies, pricing tables, or price adjustment formulas?
2. What stable reference unit do external domains (principally Pricing) address to determine the price of a sellable unit?
3. How is the absence of a price in a given pricing context or currency handled?

---

## Decision

The project Owner formally approved the following decision on 2026-10-11:

### Owner-Approved Source Text (Arabic Original)

> تعتمد Product V1 الـVariant بوصفه وحدة التسعير المرجعية لكل وحدة قابلة للبيع. لا تملك Product Library أسعارًا أو معادلات لتعديلات الأسعار. تتولى مكتبة Pricing المستقلة تعريف الأسعار وقوائمها وسياقاتها وسياسات حلّ السعر المناسب للـVariant. ولا يحدث fallback تلقائي إلى سعر Product عند غياب سعر الـVariant.

### English Translation

> Product V1 adopts the Variant as the reference pricing unit for every sellable unit. The Product Library does not own prices or formulas for price adjustments. The independent Pricing library is responsible for defining prices, price lists, pricing contexts, and policies for resolving the applicable price for the Variant. No automatic fallback to a Product-level price occurs in the absence of a Variant price.

### Key Dimensions of the Approved Decision

1. **Variant as Sole Pricing Reference:** Every sellable unit represents a Variant with a stable identity (`variant_id`). Price lookups and pricing contexts are addressed exclusively to Variant identity.
2. **Product Library Relieved of Pricing Responsibility:** The `php-product` library contains no price columns, stores no currencies, provides no formulas for price adjustments or discounts, and participates in no financial calculations.
3. **Independent Pricing Domain:** The independent Pricing library (`php-pricing`) defines prices, Price Lists, Pricing Contexts, currencies, and resolution policies to determine the applicable Variant price.
4. **No Automatic Fallback:** No automatic fallback to a Product-level price occurs when a Variant has no price in a specific pricing context or currency; absence of a price signifies that the Variant is not sellable in that context/currency under integration rules.

---

## Rationale

1. **Stable Sellable Unit Identity:** Every product in Product V1 has at least one Variant identity: a Simple Product automatically creates a single empty-composition Variant, and a Configurable Product has multiple Variants with defined option compositions. Addressing prices to Variant identity unifies the commercial and transactional model across all product types without exceptions.
2. **Separation of Concerns and Reusability:** Keeping `php-product` free from price tables, currency rules, account classifications, and price adjustments ensures package purity and architectural stability, enabling reuse across diverse applications without binding to a particular pricing model.
3. **Price Safety and Commercial Risk Mitigation:** Option-level adjustment formulas and automatic fallbacks to a base product price introduce severe commercial risks (e.g., selling costly configurations at an unintended base price). Requiring explicit, direct pricing per Variant in the Pricing library guarantees commercial precision and safety.
4. **Flexibility for Advanced Pricing Models:** An independent Pricing library enables flexible, contextual pricing policies (such as B2B wholesale price lists, B2C retail, currency differences, and promotional campaigns) resolved against Variant identity without mutating or duplicating product data.

---

## Consequences

### Positive Consequences

- Absolute clarity in domain ownership and architectural boundaries between Product and Pricing.
- Simple, stable `php-product` schema free from price columns or adjustment calculations.
- Unified integration identity for downstream operations (inventory, supplier fulfillment, order lines) based on `variant_id`.
- Complete elimination of unintended or implicit price fallback risks.

### Follow-up Requirements

- Requires formal reconciliation between the Pricing library contract (`php-pricing`), the Catalog architecture document, and umbrella rules before complete Catalog sellability can be claimed.
- Customer account type mapping to pricing contexts and coordination of Pricing library calls remain the responsibility of the Catalog coordinator or Host Application.
- The architecture candidate document (`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`) remains classified as `Architecture Candidate` until the remaining Owner decision gates are resolved.

---

## Boundaries & Non-Goals

This decision is strictly confined to adopting the Variant as the reference pricing unit and defining the boundary between Product and the independent Pricing library. The following areas remain explicitly out of scope:

- Mechanisms for price sharing or price inheritance across multiple Variants.
- Discount rules, promotion vouchers, taxes, and shipping fees.
- Schema and API design for Price Lists in `php-pricing`.
- Public or internal API specifics of the `php-pricing` library.
- Resolution of the remaining Owner decision gates.

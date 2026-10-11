# DEC-002 — Optional Inventory Tracking Boundary for Product V1

- **Decision ID:** `DEC-002`
- **Title:** Optional Inventory Tracking Boundary for Product V1
- **Status:** `ACTIVE`
- **Date:** `2026-10-11`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Product inventory and availability boundary; non-mandatory Inventory record per Variant; independence of inventory domain from Product V1
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§8.1, §20)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

In the legacy Catalog V1 specification, an inventory identity/record was required for every participating `Variant` as a prerequisite for tracking and selling.

During the specification and architecture review of `Product V1` as an independent, reusable Composer package, the need arose to support diverse sales and fulfillment models (such as digital goods delivered via external providers, services, made-to-order goods, and items not requiring local quantity tracking) without mandating a local inventory record for every `Variant`.

This issue was raised as an Owner Decision Gate in the candidate architecture document ([`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) §20 Gate 2) to resolve the boundary between Product and Inventory.

---

## Decision

The project Owner formally approved the following decision on 2026-10-11:

### Owner-Approved Source Text (Arabic Original)

> تعتمد Product V1 عدم امتلاك بيانات المخزون أو التوافر، وعدم اشتراط وجود سجل Inventory لكل Variant. ويُسمح بوحدات البيع التي لا تحتاج إلى تتبّع كمية بالعمل دون سجل مخزون محلي، مع بقاء مسؤولية إدارة المخزون والتوافر ضمن النطاق المستقل المختص.

### English Translation

> Product V1 adopts not owning inventory or availability data, and not requiring an Inventory record for every Variant. Sellable units that do not require quantity tracking are permitted to operate without a local inventory record, while responsibility for inventory and availability management remains within the independent specialized domain.

### Key Dimensions of the Approved Decision

1. **Product Library Relieved of Inventory and Availability Data:** The `php-product` library contains no quantity-on-hand columns (`quantity_on_hand`), owns no logic or mechanisms for inventory reservations or warehouse movements, and enforces no out-of-stock states (`force_out_of_stock`).
2. **No Mandatory Inventory Record per Variant:** An inventory row or record is not required for each `Variant`. The Variant possesses a structurally valid, independent identity whether associated with an inventory record or not.
3. **Sellable Units Without Quantity Tracking Permitted:** Sellable units that inherently do not require quantity tracking (such as digital goods, services, or externally fulfilled products) are explicitly permitted to operate and sell without requiring a local inventory record.
4. **Independence of Inventory and Availability Domain:** Responsibility for stock levels, warehouse ledgers, and availability calculations remains within the independent specialized domain (`php-inventory` / Fulfillment Domain).

---

## Rationale

1. **Separation of Concerns and Domain Purity:** The `php-product` library focuses strictly on product definition, identity, variants, options, and content; quantity management, warehouse movements, and stocktaking represent an independent logistics and operations domain.
2. **Flexibility Across Diverse Fulfillment Models:** Mandating an inventory record for every Variant impairs package usability for digital goods (codes, APIs), services, and made-to-order products that do not follow traditional stock inventory models.
3. **Stability of Variant Identity:** Decoupling Variant identity from stock records ensures that sellable-unit identities remain stable and integratable across diverse systems without depending on table rows in an inventory database.
4. **Catalog Integration Flexibility:** Enables the catalog coordinator or host application to consult the inventory domain only when a Variant's fulfillment model requires quantity tracking, without imposing an artificial constraint across all goods.

---

## Consequences

### Positive Consequences

- The `php-product` schema remains completely free from quantity fields and inventory tables.
- Uniform, first-class support for stocked, non-stocked, and digital goods under the same Variant identity model.
- Independence of the inventory library without pre-conditions or forced coupling from the product library.

### Follow-up Requirements

- The Catalog orchestration contract must reflect that inventory verification is optional and governed by the fulfillment model of each Variant; absence of an inventory record does not block selling items that do not require tracking.
- Validating available quantity (when applicable) is the responsibility of the Catalog coordinator in integration with the inventory domain, not via methods in `php-product`.
- The architecture candidate document (`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`) remains classified as `Architecture Candidate` until the remaining Owner decision gates are resolved.

---

## Boundaries & Non-Goals

This decision is strictly confined to Product not owning inventory data and not requiring an Inventory record per Variant. The following areas remain explicitly out of scope:

- Schema and API design for the independent inventory library (`php-inventory`).
- Reservation policies and stock ledger mechanisms.
- Availability definitions and out-of-stock handling policies.
- Integration specifics with external suppliers or fulfillment providers.
- Resolution of the remaining Owner decision gates.

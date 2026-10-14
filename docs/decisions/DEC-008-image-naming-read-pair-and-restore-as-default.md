# DEC-008 — Image Naming, Read Pair, and Explicit Restore-as-Default

- **Decision ID:** `DEC-008`
- **Title:** Image Naming, Read Pair, and Explicit Restore-as-Default
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Image Role and Image Assignment naming / Consumer and Management read pair / Default marker under restore
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§9.2, §9.4, §11, §11.4) and [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Implemented Public API, Behavior and guarantees, §3.7)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture approved under [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) at `main` commit `cdbefe7d4296d29292f051f2f7db52b79cb40d62` named the image capability "Product Media Roles" and "Media Assignments", used the marker `is_primary`, and stated in §9.4 and §11.4 that restoring a soft-deleted assignment leaves it non-primary and that "restore as primary" is a separate explicit command that fails if another primary exists in the exact scope.

Commit `1f92ce1` aligned the package with `maatify/php-category` after that approval: `maa_product_image_roles` and `maa_product_image_assignments`, the marker `is_default` with the generated `default_scope_identity`, `getById` as the consumer read and `getByIdForManagement` as the management read, and it removed the restore-as-primary command from the architecture. These changes altered the approved architecture without a recorded Owner decision. The Lead raised them to the Owner as item B1.

## Decision

### Owner Decision Trace

The Owner chose on 2026-10-14, in the Lead conversation; the choice is also recorded in `Maatify/php-product#7` (comment `6161032003`). The Owner's exact words:

> Approve except restore-as-primary

### Lead Interpretation (Not Owner Words)

1. **Naming.** The implemented names are approved: `maa_product_image_roles`, `maa_product_image_assignments`, the marker `is_default`, and the generated `default_scope_identity`.
2. **Read pair.** `getById` is the consumer read (only a consumer-visible row) and `getByIdForManagement` is the management read (status ignored, deleted-state filter).
3. **Set default.** `setDefault` replaces the current default of the exact scope atomically: it clears the previous default and sets the requested assignment in the same transaction.
4. **Restore.** `restore` always leaves the restored assignment non-default.
5. **Restore as default is reinstated.** A separate explicit command, `restoreAsDefault`, restores a soft-deleted assignment and makes it the default of its exact scope in the same operation. It fails with a typed conflict (`FC-CONF`) when that exact scope already has a live default; it never replaces an existing default. This is the behavior of the approved architecture text at `cdbefe7` (§9.4, §11.4), expressed with the `is_default` naming.

## Rationale

The naming and read-pair changes keep Product consistent with `maatify/php-category` for the same concepts. The Owner did not accept dropping restore-as-default: a Host that soft-deletes a default by mistake needs one explicit operation to bring it back as the default, and the conflict rule keeps that operation from silently displacing a default chosen after the deletion.

## Consequences

- The architecture is amended in §9.4 and §11.4 to restore the explicit restore-as-default sentence with the `is_default` naming.
- `restoreAsDefault` is part of the approved contract and is not implemented yet; the Package Reference lists it under "Not yet implemented". Its implementation belongs to a later Work Unit.
- `DEC-005` and `DEC-006` remain `ACTIVE`. This record does not change the Media Asset identity contract of `DEC-005` or the ordering boundary of `DEC-006`. It replaces only the descriptive naming in their details (`DEC-005` Explicit Contract Details item 7, `DEC-006` Explicit Contract Details item 6), and it amends the architecture approved under `DEC-007`.

## Supersedes / Superseded By

- **Supersedes:** None. The descriptive names in `DEC-005` and `DEC-006` are replaced as stated above, without superseding either decision.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The current normative contract is the architecture document (§9, §11.4) and the Package Reference. This record keeps the decision and its rationale.

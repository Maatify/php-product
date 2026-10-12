# Maatify Engineering Standards Adoption Manifest

## Adoption Record

- **Overall Resolution Status:** `VALID`
- **Adoption Model:** Selective pinned adoption
- **Upstream Repository:** `https://github.com/Maatify/php-engineering-standards.git`
- **Adoption Commit:** `ebcf55b0591db61d18a1407bc3effef0197b388f`
- **Adoption Date:** `2026-10-12`
- **Latest Applicability Re-evaluation:** `2026-10-12` — pre-implementation Stage 2 re-evaluation under `DEC-007`; Adoption Commit, Profiles, and Control Set unchanged
- **Additional Standards:** None
- **Explicit Exceptions/Overrides:** None

## Pinned Adoption Control Set

All files below are copied from the exact Adoption Commit recorded above.

- [Standards Adoption Standard](standards/STANDARDS_ADOPTION_STANDARD_AR.md) — `std-standards-adoption` `5.0.0`
- [Composer Package Profile](standards/profiles/COMPOSER_PACKAGE_PROFILE.md) — `composer-package` `4.0.0`
- [Repository Governance Profile](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md) — `repository-governance` `3.0.0`

## Pinned Operational Assets Set

| Kind | Upstream Path | Local Path | SHA-256 |
|---|---|---|---|
| `skill` | `.agents/skills/maatify-standards-context-lifecycle/SKILL.md` | `.agents/skills/maatify-standards-context-lifecycle/SKILL.md` | `c2dc2e4274cda3d8618ad0d72867c2e21a5fc5e4199c88e476876c53ecb56735` |
| `skill` | `.agents/skills/maatify-strict-release-acceptance/SKILL.md` | `.agents/skills/maatify-strict-release-acceptance/SKILL.md` | `0f84c8265bc8d261fa4a93c695bd6d813f9903b90ec7b0d2a9cf65446f6e23ed` |
| `deterministic-helper` | `.agents/helpers/maatify-governance-context.php` | `.agents/helpers/maatify-governance-context.php` | `96ed5625416e8f58f24b5e9398adb95500ae78010d58130f17ddc17bdd0630b1` |

## Active Profiles

### Activation 1: `composer-package`

- **Profile Version:** `4.0.0`
- **Scope:** `/`
- **Resolution Status:** `VALID`
- **Exception State:** `NONE`
- **Activation:** [Composer Package Profile](standards/profiles/COMPOSER_PACKAGE_PROFILE.md)

### Activation 2: `repository-governance`

- **Profile Version:** `3.0.0`
- **Scope:** `/`
- **Resolution Status:** `VALID`
- **Exception State:** `NONE`
- **Activation:** [Repository Governance Profile](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md)

## Resolved Applicable Standards Set

These are the final Stage 2 applicable standards for the two activations at `/`, as re-evaluated on 2026-10-12 under `DEC-007`. Candidate standards excluded by their canonical applicability are not listed here.

| Standard ID | Version | Local path | Resolution provenance |
|---|---:|---|---|
| `std-package-building` | `3.0.2` | [PACKAGE_BUILDING_STANDARD.md](standards/packages/PACKAGE_BUILDING_STANDARD.md) | Direct requirement of `composer-package` at `/`; standalone Composer package |
| `std-composer-package` | `5.0.0` | [COMPOSER_PACKAGE_STANDARD.md](standards/packages/COMPOSER_PACKAGE_STANDARD.md) | Direct requirement of `composer-package` at `/`; repository is the standalone package root |
| `std-ci-workflow` | `5.0.0` | [CI_WORKFLOW_STANDARD.md](standards/packages/CI_WORKFLOW_STANDARD.md) | Direct requirement of `composer-package` at `/`; package CI scope |
| `std-library-presentation` | `5.0.0` | [LIBRARY_PRESENTATION_STANDARD.md](standards/packages/LIBRARY_PRESENTATION_STANDARD.md) | Direct requirement of `composer-package` at `/`; standalone reusable package presentation scope |
| `std-testing` | `2.0.0` | [TESTING_STANDARD.md](standards/testing/TESTING_STANDARD.md) | Direct requirement of `composer-package` at `/`; package testing scope |
| `std-php-source-documentation` | `1.0.0` | [PHP_SOURCE_DOCUMENTATION_STANDARD.md](standards/php/PHP_SOURCE_DOCUMENTATION_STANDARD.md) | Direct requirement of `composer-package` at `/`; the Owner-approved Product V1 implementation scope (`DEC-007`) makes the package own manually maintained PHP source |
| `std-php-coding-style` | `1.0.1` | [PHP_CODING_STYLE_STANDARD.md](standards/php/PHP_CODING_STYLE_STANDARD.md) | Direct requirement of `composer-package` at `/`; the Owner-approved Product V1 implementation scope (`DEC-007`) makes the package own manually maintained PHP code |
| `std-documentation-lifecycle` | `3.1.0` | [DOCUMENTATION_LIFECYCLE_STANDARD_AR.md](standards/governance/DOCUMENTATION_LIFECYCLE_STANDARD_AR.md) | Direct requirement of both active profiles; package owns durable documentation and repository governance records |
| `std-decision-governance` | `1.0.0` | [DECISION_GOVERNANCE_STANDARD_AR.md](standards/governance/DECISION_GOVERNANCE_STANDARD_AR.md) | Direct requirement of `repository-governance` at `/`; Maatify repository governance applies |
| `std-ai-collaboration-workflow` | `12.0.0` | [AI_COLLABORATION_WORKFLOW_AR.md](standards/ai/AI_COLLABORATION_WORKFLOW_AR.md) | Direct requirement of `repository-governance` at `/` |
| `std-github-phase-stack-workflow` | `4.0.0` | [GITHUB_PHASE_STACK_WORKFLOW_AR.md](standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md) | Direct requirement of `repository-governance` at `/` |

## Resolution Summary

Stage 1 resolved the two active Profiles at the pinned Adoption Commit. Neither extends another Profile. `composer-package @ /` contributes nine direct candidates and `repository-governance @ /` contributes four; `std-documentation-lifecycle` is shared, yielding 12 unique candidates:

| Candidate Standard | Activation(s) | Stage 2 result |
|---|---|---|
| `std-package-building` | `composer-package @ /` | Applicable; package-building standard applies to this package repository. |
| `std-composer-package` | `composer-package @ /` | Applicable; the repository is a standalone Composer package. |
| `std-ci-workflow` | `composer-package @ /` | Applicable; package CI scope. |
| `std-library-presentation` | `composer-package @ /` | Applicable; standalone reusable package presentation scope. |
| `std-testing` | `composer-package @ /` | Applicable; package testing scope. |
| `std-external-provider-verification` | `composer-package @ /` | Excluded; this repository owns no provider-specific contract. |
| `std-documentation-lifecycle` | both activations | Applicable; the repository owns durable documentation and governance records. |
| `std-php-source-documentation` | `composer-package @ /` | Applicable; the approved Product V1 scope makes the package own manually maintained PHP source (re-evaluated under `DEC-007`; previously excluded because no such source existed). |
| `std-php-coding-style` | `composer-package @ /` | Applicable; the approved Product V1 scope makes the package own manually maintained PHP code (re-evaluated under `DEC-007`; previously excluded because no such code existed). |
| `std-ai-collaboration-workflow` | `repository-governance @ /` | Applicable; direct governance requirement. |
| `std-github-phase-stack-workflow` | `repository-governance @ /` | Applicable; direct governance requirement. |
| `std-decision-governance` | `repository-governance @ /` | Applicable; direct governance requirement. |

The conditional persistence, SQL, PDO, schema, migration, and database-test requirements owned by Package Building are activated as of the 2026-10-12 re-evaluation: the Owner-approved Product V1 scope (`DEC-007`) makes the package own SQL persistence on MySQL `8.0.16+` through direct PDO. They remain conditional requirements inside already-applicable Standards and are not additional candidate Standards. Their activation also brings the persistence-conditioned obligations of the other applicable Standards into scope: real-service MySQL Integration verification and Docker Compose provisioning under `std-ci-workflow`, persistence and schema/install Integration coverage under `std-testing`, and database-support claims under `std-composer-package` and `std-library-presentation`. All 12 candidate references exist at the exact pinned upstream commit.

Per §16.1, the independent diagnostics are `composer-package @ /` — `Resolution Status: VALID`, `Exception State: NONE`; and `repository-governance @ /` — `Resolution Status: VALID`, `Exception State: NONE`. Their pinned inputs are the Control Set recorded above and the package-root repository facts used by Stage 2. The first activation resolves eight applicable candidates and deterministically excludes one. The second resolves its four applicable candidates, including the shared Documentation Lifecycle Standard. Neither activation has a deviation or unresolved adoption decision.

The final set is the eleven applicable Standards listed above; the one excluded candidate (`std-external-provider-verification`) remains out of `Resolved Applicable Standards Set`. The exact pinned commit contains the active Profile manifests and all candidate definitions. No inheritance cycle, missing candidate reference, or broken relative link in the final local reference closure was found.

The frozen Profile Version Baseline is established independently under §5.1.1 from the `Maatify/php-rate-limiter` consumer repository. Its completed valid Adoption at consumer commit `5e40583a00fd4bb8bf54fee06319afe19a0c13d3` pins `composer-package` `3.0.0` and `repository-governance` `3.0.0` to upstream commit `f9048d9d75395244fa4af26b55e6b85ff0a898c6`; its manifest records `Overall Resolution Status: VALID` and independent `VALID` / `NONE` diagnostics for both activations. The completed valid Upgrade at consumer commit `2891a070be22e14f35feef43390f6eb04a7db5f6` pins `composer-package` `4.0.0` and `repository-governance` `3.0.0` to upstream commit `35276355e35a61c2320305800a4551496e883c18`, again with independent `VALID` / `NONE` diagnostics. This Upgrade record remains present on `php-rate-limiter` `main` at `9d76f2dbc538362e01d70f8bb76e1675097514ef`. At upstream commit `35276355e35a61c2320305800a4551496e883c18`, the Profile blobs are `9c7bf1952372c59d50bb62daa03ce45729d1ebc4` (`composer-package`) and `ef7e13313e804cb6f02785dbff667b58119176b1` (`repository-governance`); those exact blobs are unchanged at this Adoption Commit `ebcf55b0591db61d18a1407bc3effef0197b388f`. Thus the current `4.0.0` and `3.0.0` Profile versions are frozen and content-matched; no Profile version bump is required. The earlier `php-product` Manifest at `38d492a` declared only an aggregate `VALID` without the independent Activation/Scope diagnostics required by its pinned §16.1, so it is not treated as a completed valid Adoption or as the baseline evidence.

The three listed Operational Assets are pinned to the Adoption Commit and verified by raw-content SHA-256. There is no mixed-pinning exception and no `DEC-032` adoption as an additional Standard or added central decision record.

The overall `Resolution Status` follows §16.2: `INVALID` takes precedence over `OWNER DECISION REQUIRED`, which takes precedence over `VALID`. Both independent Activation/Scope results are `VALID`; there is no unresolved adoption decision, structural failure, or exception. Therefore the aggregate result is `VALID`.

## Pre-Implementation Applicability Re-evaluation (2026-10-12)

Performed under `DEC-007`, `STANDARDS_ADOPTION_STANDARD_AR.md` §10.2, and `std-ai-collaboration-workflow` `12.0.0` §7.1. This is a targeted Stage 2 re-evaluation at the unchanged Adoption Commit `ebcf55b0591db61d18a1407bc3effef0197b388f`; it is not an Upgrade.

- **Artifact facts:** The Owner-approved architecture (`DEC-007`) establishes that the package will own manually maintained PHP source and tests, and package-owned SQL persistence (MySQL `8.0.16+`, direct PDO, `schema/`). The repository does not yet contain that source; applicability is recorded in advance so implementation starts with the complete applicable set.
- **Stage 1:** Unchanged. The two active Profiles resolve as before; both Profile files match the exact upstream Adoption Commit.
- **Stage 2 changes:** `std-php-source-documentation` and `std-php-coding-style` move from Excluded to Applicable. `std-external-provider-verification` stays Excluded because the repository owns no provider-specific contract and `DEC-001`/`DEC-002` keep Pricing, Inventory, and supplier concerns outside Product.
- **Added pinned files:** The two newly applicable Standards were copied from the exact Adoption Commit; their raw content matches upstream. Neither contains relative links, so local reference closure holds.
- **Unchanged:** Adoption Commit, Control Set, Operational Assets, Profile versions, `Explicit Exceptions/Overrides` (None), and the other nine Resolved Standards, all of which match the upstream Adoption Commit byte for byte.
- **Result:** Both Activations remain `Resolution Status: VALID`, `Exception State: NONE`; `Overall Resolution Status` remains `VALID`.

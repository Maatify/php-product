# Maatify Engineering Standards Adoption Manifest

## Adoption Record

- **Resolution Status:** `VALID`
- **Adoption Model:** Selective pinned adoption
- **Upstream Repository:** `Maatify/php-engineering-standards` (`https://github.com/Maatify/php-engineering-standards`)
- **Adoption Commit:** `9fbd25f59697fd169400587d6735d39a9256a554`
- **Adoption Date:** `2026-10-09`
- **Additional Standards:** None
- **Explicit Exceptions/Overrides:** None
- **Operational Assets:** None adopted

## Pinned Adoption Control Set

All files below are copied from the exact Adoption Commit recorded above.

- [Standards Adoption Standard](standards/STANDARDS_ADOPTION_STANDARD_AR.md) — `std-standards-adoption` `4.1.0`
- [Composer Package Profile](standards/profiles/COMPOSER_PACKAGE_PROFILE.md) — `composer-package` `4.0.0`
- [Repository Governance Profile](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md) — `repository-governance` `3.0.0`

## Active Profiles

| Profile ID | Version | Scope | Resolution Status | Exception State | Activation |
|---|---:|---|---|---|---|
| `composer-package` | `4.0.0` | `/` | `VALID` | `NONE` | [Composer Package Profile](standards/profiles/COMPOSER_PACKAGE_PROFILE.md) |
| `repository-governance` | `3.0.0` | `/` | `VALID` | `NONE` | [Repository Governance Profile](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md) |

## Resolved Applicable Standards Set

These are the final Stage 2 applicable standards for the two activations at `/`. Candidate standards excluded by their canonical applicability are not listed here.

| Standard ID | Version | Local path | Resolution provenance |
|---|---:|---|---|
| `std-package-building` | `3.0.2` | [PACKAGE_BUILDING_STANDARD.md](standards/packages/PACKAGE_BUILDING_STANDARD.md) | Direct requirement of `composer-package` at `/`; standalone Composer package |
| `std-composer-package` | `5.0.0` | [COMPOSER_PACKAGE_STANDARD.md](standards/packages/COMPOSER_PACKAGE_STANDARD.md) | Direct requirement of `composer-package` at `/`; repository is the standalone package root |
| `std-ci-workflow` | `5.0.0` | [CI_WORKFLOW_STANDARD.md](standards/packages/CI_WORKFLOW_STANDARD.md) | Direct requirement of `composer-package` at `/`; package CI scope |
| `std-library-presentation` | `5.0.0` | [LIBRARY_PRESENTATION_STANDARD.md](standards/packages/LIBRARY_PRESENTATION_STANDARD.md) | Direct requirement of `composer-package` at `/`; standalone reusable package presentation scope |
| `std-testing` | `2.0.0` | [TESTING_STANDARD.md](standards/testing/TESTING_STANDARD.md) | Direct requirement of `composer-package` at `/`; package testing scope |
| `std-documentation-lifecycle` | `3.1.0` | [DOCUMENTATION_LIFECYCLE_STANDARD_AR.md](standards/governance/DOCUMENTATION_LIFECYCLE_STANDARD_AR.md) | Direct requirement of both active profiles; package owns durable documentation and repository governance records |
| `std-decision-governance` | `1.0.0` | [DECISION_GOVERNANCE_STANDARD_AR.md](standards/governance/DECISION_GOVERNANCE_STANDARD_AR.md) | Direct requirement of `repository-governance` at `/`; Maatify repository governance applies |
| `std-ai-collaboration-workflow` | `11.0.0` | [AI_COLLABORATION_WORKFLOW_AR.md](standards/ai/AI_COLLABORATION_WORKFLOW_AR.md) | Direct requirement of `repository-governance` at `/` |
| `std-github-phase-stack-workflow` | `4.0.0` | [GITHUB_PHASE_STACK_WORKFLOW_AR.md](standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md) | Direct requirement of `repository-governance` at `/` |

## Resolution Summary

Stage 1 resolved the two active Profiles. Neither extends another Profile. The Composer Package Profile contributes nine direct candidate references; the Repository Governance Profile contributes four. `std-documentation-lifecycle` is shared, producing 12 unique candidate references. All Profile files and all 12 candidate Standard definitions exist at the pinned upstream commit.

Per §16.1, the independent diagnostics are `composer-package @ /` — `Resolution Status: VALID`, `Exception State: NONE`; and `repository-governance @ /` — `Resolution Status: VALID`, `Exception State: NONE`. The first activation resolves its package standards and shared Documentation Lifecycle applicability. The second resolves AI Collaboration Workflow, GitHub Phase Stack Workflow, Decision Governance, and the shared Documentation Lifecycle applicability. No deviation or unresolved adoption decision applies to either activation.

Stage 2 includes the nine standards listed above. It excludes:

- `std-external-provider-verification`: the repository does not own a provider-specific contract.
- `std-php-source-documentation`: the repository contains no manually maintained PHP source.
- `std-php-coding-style`: the repository contains no manually maintained PHP code.
- Persistence, SQL, PDO, schema, migration, and database-test requirements: these are conditional on package-owned persistence/database behavior; none exists in this repository.

The excluded references remain visible in the pinned Composer Package Profile and were checked structurally against the Adoption Commit. No profile inheritance cycle, missing candidate reference, or broken link between pinned control files and the final standards set was found. Profile versions `4.0.0` and `3.0.0` are initial consumer baselines for this repository; its history contains no earlier Adoption Manifest or frozen profile snapshot.

The overall `Resolution Status` is calculated under §16.2: any `INVALID` activation makes the result `INVALID`; otherwise any activation or unresolved adoption decision requiring an Owner decision makes it `OWNER DECISION REQUIRED`; otherwise the result is `VALID`. Both activation diagnostics are `VALID`, with no unresolved decision, so the recorded overall result is `VALID`.

# CI gates and local verification

This document maps every required CI gate to the local command that runs the same
verification contract. The governing contracts are `std-ci-workflow` (§2.1, §8,
§11, §16, §19), `std-testing` (§3.2, §3.4), and `std-composer-package` (§21), as
pinned in [`STANDARDS_MANIFEST.md`](../php-engineering-standards/STANDARDS_MANIFEST.md).
The workflow is [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml); branch
protection should require only the stable `Final Gate` job.

## Prerequisites

PHP 8.4 or later with `pdo_mysql`, Composer 2.10 or later (the dependency-policy
model), Docker with the Compose plugin, `openssl`, and `bash`. Install development
dependencies once:

```bash
composer update --no-interaction --prefer-dist --no-progress
```

`composer.lock` is not committed. No credential is stored: every run generates a
random, run-scoped database password.

## Gates

| Gate | Local command | CI job |
|---|---|---|
| Composer validation | `composer validate --strict` | Composer Validate |
| Platform requirements | `composer check-platform-reqs` | each dependency job |
| PHP syntax | `bash scripts/ci/check-php-syntax.sh` | PHP Syntax Check |
| Autoload (strict PSR-4) | `composer dump-autoload --optimize --strict-psr` | Static Analysis, Unit, Integration |
| Static analysis (PHPStan max over `src`, `tests`, and `examples`, no baseline or suppressions) | `composer analyse` | Static Analysis (PHPStan) |
| Coding style (PER-CS 3.1, non-mutating) | `composer format:check` | PHP Coding Style (PER 3.1) |
| Unit suite (no Docker needed) | `composer test:unit` | Unit (PHP 8.4 and 8.5) |
| Integration suite on real MySQL | `composer test:integration` (`composer test` runs Unit then the same Integration orchestration) | Integration (PHP x MySQL matrix) |
| Lowest supported dependencies | `composer update --prefer-lowest --prefer-stable` then `composer analyse` and `composer test` | Lowest Supported Dependencies |
| Dependency-policy audit | `composer audit --no-interaction --abandoned=fail` | Composer Security Audit |
| Consumer Verification Harness | `composer verify:consumer` | Consumer Verification Harness |
| Workflow lint (pinned, checksum-verified actionlint) | `bash scripts/ci/install-actionlint.sh <dir>` then `ACTIONLINT_BIN=<dir>/actionlint bash scripts/ci/lint-workflows.sh` | Workflow Lint |
| Whitespace (tracked tree, Markdown included) | `bash scripts/ci/test-whitespace-gate.sh` then `bash scripts/ci/check-whitespace.sh` | Whitespace Verification |

## Integration infrastructure contract

One Compose definition ([`docker-compose.yml`](../../docker-compose.yml)) serves the
Integration suite and the Harness through `scripts/ci/mysql-service.sh`:

- the image tag is the parameter `PRODUCT_TEST_MYSQL_VERSION` (default `8.0.16`, the
  supported minimum; CI also runs `8.0.46`), never a copied definition;
- readiness is the service health check (a TCP `mysqladmin ping`), not a `sleep`;
- there is no `container_name`; each run uses its own Compose project, a loopback-only
  dynamic host port, and `tmpfs` storage, so no state survives and no developer or
  Host database is touched;
- teardown is `docker compose down -v`, on success and on failure;
- the PHP runner stays on the host PHP runtime, so the PHP matrix is truthful;
- the suite runs twice against the same service and then asserts that no test
  database remains.

Integration variables are `PRODUCT_TEST_*` and are set by the orchestration; running
PHPUnit directly without them fails (nothing is skipped). Docker and Compose are
verification-time dependencies only, never consumer runtime dependencies.

## What the suites prove

**Unit** (`tests/Unit`, no infrastructure): command validation including whitespace-only and
encoding cases (Product, Option, Value, Role, Assignment, and reconfiguration commands, the Media Asset identity
contract, composition selections), the composition fingerprint vectors, the in-memory configuration validator,
DTO invariants, Service orchestration with test doubles,
the `ProductPersistenceException` consumer contract, identity stability (no package SQL statement sets an
identity column and no service method replaces one), and the Package Reference conformity test, which fails
when the "Implemented Public API" table and the code disagree (a listed type or method that does not exist,
an undocumented public method of a service or the factory, or a public type that is neither listed nor internal).

**Integration** (`tests/Integration`, real MySQL, exact MySQL error numbers):
- `Schema/`: install shape; status casing; code, SKU, barcode, language, platform, and role-key
  uniqueness including soft-deleted history; whitespace-only rejection (space, tab, LF, CR, VT,
  FF, NBSP, U+3000); composite ownership foreign keys; NULL-safe Content and image-assignment identity;
  image-assignment target, scope, and default rules.
- `Runtime/`: the public use cases through the factory: Product creation (inactive Product plus
  one active empty-composition Variant, case-insensitive and soft-delete-reserved conflicts),
  Variant reads (consumer visibility evaluated by the database including the product-wide
  unrepresented-Value rule, management filters, ordering and bounds), composition resolution with
  its four failure classes, the lifecycle contract of every family (soft-deleted row, soft-deleted
  owner chain, restore with the saved status and revalidation, inactivity blocking nothing), Option
  and Value creation and ordering, Image Roles and Assignments (identity, target integrity, the scope
  key shared with the Ordering API, atomic default replacement, no promotion), Product reconfiguration
  (transitions, explicit reuse, conflicts, restore, failure classes, full rollback), rollback of a failed
  operation the package started, and, inside a caller-owned transaction, savepoint rollback of a failed
  operation that the Host then commits or rolls back (the Host's other work preserved, the Host
  transaction never committed or rolled back by the package), Ordering API allocation, and real
  concurrent writers in separate processes (creation, reconfiguration of one Product, default
  replacement within one scope).

## Consumer Verification Harness

`composer verify:consumer` installs the package into an external Composer root (path
repository, copied, not symlinked), loads it only through the production PSR-4 autoloader
(asserting that the public classes resolve from the installed package and that test namespaces
are not autoloadable), installs the delivered `schema/product.sql`, and runs the workflow
`ProductFactory` -> create, activate, prepare Options and Values, reconfigure, resolve a composition, assign images and
replace the default, soft-delete and restore, against a real MySQL service. It runs
twice from clean consumer roots, uses the same Compose lifecycle as Integration, and is part of
the stable `Final Gate`.

## Whitespace gate

`check-whitespace.sh` scans every tracked file in the checked-out tree, so it does
not depend on checkout depth or on the PR base. Non-Markdown files may not end a line
with a blank, and also pass `git diff --check` against the empty tree. Markdown is
scanned too: the only exemption is exactly two trailing spaces after text (a Markdown
hard line break); one space, three or more, a tab, or a whitespace-only line fail.
`test-whitespace-gate.sh` proves, in a throwaway repository, that each defect is
rejected and that clean and hard-break files are accepted.

## Current limits

The harness exercises the public API that exists today. Each new public capability must extend it
in the Work Unit that adds it.

Not applicable or not yet provable, and not claimed:

- Regression suite and `test:regression`: no resolved regression exists yet.
- Concurrency proof for operations that do not exist yet (Content, SKU and barcode mutation,
  ordering commands).
- Release Artifact Verification and Published Artifact Verification: release-time gates, not
  applicable before a release target exists.

**Branch protection: in force.** The repository ruleset `main-final-gate` (enforcement `active`, no bypass actors) targets
the default branch `main`. GitHub reports it through `GET /repos/Maatify/php-product/rules/branches/main`: pull request
required, the stable `Final Gate` job (GitHub Actions) required with the branch up to date, no force push, and no deletion.
This was read back from GitHub after creation; it is repository configuration, not part of the checked-out tree, so it is
re-verified with that command rather than by a CI job.

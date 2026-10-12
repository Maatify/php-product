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
| Static analysis (PHPStan max, no baseline or suppressions) | `composer analyse` | Static Analysis (PHPStan) |
| Integration suite on real MySQL | `composer test:integration` (or `composer test`) | Integration (PHP x MySQL matrix) |
| Lowest supported dependencies | `composer update --prefer-lowest --prefer-stable` then `composer analyse` and `composer test:integration` | Lowest Supported Dependencies |
| Dependency-policy audit | `composer audit --no-interaction --abandoned=fail` | Composer Security Audit |
| Consumer Verification Harness | `composer verify:consumer` | Consumer Verification Harness |
| Workflow lint (pinned, checksum-verified actionlint) | `bash scripts/ci/install-actionlint.sh <dir>` then `ACTIONLINT_BIN=<dir>/actionlint bash scripts/ci/lint-workflows.sh` | Workflow Lint |
| Whitespace | `bash scripts/ci/check-whitespace.sh` | Whitespace Verification |

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

## What each test class proves

The Integration suite installs `schema/product.sql` into a fresh database per class
and asserts exact MySQL error numbers: install shape; status casing; code, SKU,
barcode, language, platform, and role-key uniqueness including soft-deleted history;
whitespace-only rejection (space, tab, LF, CR, VT, FF, NBSP, U+3000); composite
ownership foreign keys; NULL-safe Content and media identity; media target, scope,
and primary rules.

## Current limits (not yet satisfiable)

The repository contains no PHP runtime classes, so these cannot be exercised yet and
are not claimed:

- Unit and Regression suites, and `test:unit`/`test:regression` scripts: there is no
  PHP logic to test; they must be added with the first runtime code.
- Production PSR-4 autoload and public-API use in the Harness: it currently proves
  Composer installation, clean-state repeatability, delivery of the schema asset, and
  the observable database workflow, but not a PHP public API. The `autoload` mapping
  is omitted until `src/` exists.
- Concurrency proof (aggregate locking, atomic primary replacement) and structural
  validity of a Product configuration: these belong to the runtime.
- Code-style dry-run: no formatter is configured, so the gate does not apply.
- Release Artifact Verification and Published Artifact Verification: release-time
  gates, not applicable before a release target exists.

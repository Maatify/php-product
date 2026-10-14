# DEC-025 — Lock Order and Savepoint Transactions

- **Decision ID:** `DEC-025`
- **Title:** Lock Order and Savepoint Transactions
- **Status:** `ACTIVE`
- **Date:** `2026-10-14`
- **Decision Authority / Deciders:** Lead, within the authority of [`DEC-007`](DEC-007-product-v1-implementation-start-authorization.md) and `std-ai-collaboration-workflow` §3.2.1
- **Scope / Concern:** Lock order of every mutation / Transaction runner inside and outside a Host transaction
- **Canonical Contract / Current Owner:** [`PRODUCT_PACKAGE_REFERENCE.md`](../../PRODUCT_PACKAGE_REFERENCE.md) (Behavior and guarantees: Transactions, Locking, Failure contract; §1.7)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

The architecture (§11.6, §14) requires Product aggregate serialization, a stable lock order across Product, Variant, Role, and assignment rows, and that the package never commits or rolls back an outer transaction it does not own, but does not fix the order or the transaction mechanism.

This record documents a decision that already exists: it is stated in the Package Reference (Behavior and guarantees: Transactions, Locking, Failure contract; §1.7), and implemented in commits `49d521e` and `f8f3bc8`. It is recorded on 2026-10-14 so that the decision has a durable Decision Record (`DECISION_GOVERNANCE_STANDARD_AR.md` §3). It does not change the decision.

## Decision

1. **Lock order.** For every multi-table mutation: the Product row first (`SELECT ... FOR UPDATE`), then Options, then Option Values, then Variants, then Image Roles, then Image Assignments, ascending `id` within each group. A Content mutation locks the owner's configuration the same way, then its Content row. The state a mutation builds on is read only after the Product lock is taken, with locking reads, and validation runs on that graph in memory before any write.
2. **Transactions.** Every mutation runs through `PdoSavepointTransactionRunner` (`maatify/persistence` `^1.4`). It owns and commits a transaction when none is active. Inside a Host transaction on the same PDO connection it runs in an operation-local savepoint, rolls back to that savepoint on failure, and never commits or rolls back the Host transaction, so a failed call leaves none of its writes behind.
3. **Whole-transaction failures.** When MySQL itself rolls back the whole transaction, such as on a deadlock (driver error 1213), the `PDOException` propagates unchanged and the Host must treat it as a failure of its whole transaction. No automatic retry is claimed.

## Rationale

One stable order across every mutation reduces deadlocks, locking reads give the latest committed state even inside a Host snapshot, and the savepoint keeps a failed package call from leaving partial writes that a Host could still commit.

## Consequences

- Proven by the Integration tests, including `ConcurrentCreationTest.php`, `ConcurrentReconfigurationTest.php`, `ConcurrentDefaultReplacementTest.php`, and the Host-transaction cases.

## Supersedes / Superseded By

- **Supersedes:** None.
- **Superseded By:** None.

## Canonical Contract / Current Owner

The Package Reference (Behavior and guarantees: Transactions, Locking, Failure contract; §1.7) owns the current contract; this record keeps the decision and its rationale.

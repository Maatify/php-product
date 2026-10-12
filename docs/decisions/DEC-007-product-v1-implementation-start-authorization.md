# DEC-007 — Product V1 Implementation Start Authorization

- **Decision ID:** `DEC-007`
- **Title:** Product V1 Implementation Start Authorization
- **Status:** `ACTIVE`
- **Date:** `2026-10-12`
- **Decision Authority / Deciders:** Owner
- **Scope / Concern:** Product V1 Architecture Approval / Implementation Start Authorization / Pre-Implementation Standards Applicability Re-evaluation
- **Canonical Contract / Current Owner:** [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) (§1.1, §19, §20, §21) and [`STANDARDS_MANIFEST.md`](../php-engineering-standards/STANDARDS_MANIFEST.md)
- **Supersedes:** None
- **Superseded By:** None

---

## Context

`DEC-001` through `DEC-006` resolved the six Owner Decision Gates in [`docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`](../architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md) §20. That document stated that, until implementation was explicitly authorized, it remained a `CANDIDATE` and that no PHP or SQL implementation was authorized.

It also stated (§1.1, §11, §21) that, before any PHP or SQL implementation begins, standards applicability must be formally re-evaluated under the canonical standards governance procedure, because the settled adoption state in `STANDARDS_MANIFEST.md` recorded that the repository had no manually maintained PHP source and no package-owned persistence behavior.

---

## Decision

The Owner formally approved the following decision on 2026-10-12.

### Owner Approval Trace (Arabic Original)

> أعتمد الوثيقة `docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md` في المستودع `Maatify/php-product`، على Baseline `main` عند commit `cdbefe7d4296d29292f051f2f7db52b79cb40d62`، بوصفها عقد المعمارية المعتمد لتنفيذ Product V1.
>
> أوافق على بدء مرحلة التنفيذ ضمن نطاق Product المحلي المحدد في الوثيقة، مع الالتزام بقرارات `DEC-001` إلى `DEC-006` المسجلة كقرارات `ACTIVE`.
>
> قبل بدء أي تنفيذ لـPHP أو SQL، يجب إجراء إعادة تقييم رسمية لانطباق المعايير الهندسية وفق `STANDARDS_ADOPTION_STANDARD_AR.md` والإصدار المثبت `std-ai-collaboration-workflow 12.0.0`، وتحديث سجلات الاعتماد اللازمة إذا نتج عن التقييم تغيير مطلوب. يجب أن تشمل إعادة التقييم المتطلبات الشرطية الخاصة ببناء الحزم وSQL persistence والاختبارات المرتبطة بها.
>
> لا يشمل هذا الاعتماد تنفيذ مكتبات Pricing أو Inventory، أو تكامل Catalog الكامل، أو إنشاء علاقة Product-to-Category داخل المكتبتين الأساسيتين، أو تغيير أي قرار معماري معتمد. أي تغيير جوهري في هذه الحدود يحتاج قرارًا مستقلًا من المالك.
>
> يظل كل GitHub Merge خاضعًا لموافقة المالك الصريحة، ولا تُعد هذه الموافقة تصريحًا بالدمج أو الإصدار أو النشر.

### Explicit Contract Details of the Approved Decision

1. **Approved architecture contract:** `docs/architecture/PRODUCT_V1_ARCHITECTURE_CANDIDATE.md`, as it stood at `main` commit `cdbefe7d4296d29292f051f2f7db52b79cb40d62`, is the approved architecture contract for Product V1 implementation.
2. **Implementation start:** The implementation phase is authorized within the local Product scope defined by that document, bound by `DEC-001` through `DEC-006`, which remain `ACTIVE`.
3. **Pre-implementation gate:** No PHP or SQL implementation begins before the formal standards applicability re-evaluation required by `STANDARDS_ADOPTION_STANDARD_AR.md` is completed and its adoption records are updated where the outcome requires it. The re-evaluation covers the conditional Package Building requirements for SQL persistence and their associated tests. Its recorded outcome is in `STANDARDS_MANIFEST.md`.
4. **Excluded from this authorization:** Pricing or Inventory libraries; full Catalog integration; a Product-to-Category relationship inside either core library; and any change to an approved architectural decision. Any material change to these boundaries needs a separate Owner decision.
5. **No release authority:** Every GitHub Merge still needs explicit Owner authorization. This decision does not authorize merge, release, tag, or publish.

---

## Rationale

The architecture candidate was reviewed and its six Owner Decision Gates were closed. Recording the approval durably, with the exact pre-implementation gate, prevents dependent implementation from relying on a decision that exists only in chat, as `DECISION_GOVERNANCE_STANDARD_AR.md` §3 requires.

## Consequences

- Product V1 implementation may start once the standards applicability re-evaluation is complete and recorded.
- Executors treat the approved architecture and `DEC-001` through `DEC-007` as locked and stop rather than reopen them.
- The pre-implementation applicability outcome is recorded in `STANDARDS_MANIFEST.md`, not in this record.

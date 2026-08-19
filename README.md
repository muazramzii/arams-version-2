# ARAMS 2.0

**Academic Research Analytics and Monitoring System** — Universiti Tun Hussein Onn Malaysia (UTHM)

A rebuild of ARAMS, the centralised platform that tracks lecturer research output (publications, grants, H-Index, intellectual property, research income, awards), routes it through faculty-level validation, and reports on institutional research performance.

> Final Year Project — Muaz Ramzi, supervised by Dr Shuhaida binti Ismail

---

## Status

The rebuild is in the design stage. **No ARAMS 2.0 code has been written yet.**

| Phase | Scope | State |
|---|---|---|
| 0 | Discovery — inspect existing code, schema, and real data | ✅ Complete |
| 1 | System audit — strengths, defects, technical debt | ✅ Complete |
| 2 | Data architecture — domain model, ERD, tables, constraints | ✅ Complete, under review |
| 3 | Database implementation — migrations, seeders, data migration | ⏳ Blocked on Q1–Q2 below |
| 4 | Backend — Laravel API, auth, workflow, KPI, analytics | Not started |
| 5 | Frontend — React portals for Lecturer, TDPP, Admin | Not started |
| 6–8 | Integration, testing, hardening | Not started |

## Documentation

Both documents are self-contained HTML — open them in a browser, or view the hosted versions.

| Document | Contents |
|---|---|
| [`docs/phase0-1-system-audit.html`](docs/phase0-1-system-audit.html) | Audit of ARAMS 1.0: architecture, modules, roles, workflow, schema and real-data assessment, ranked technical debt, and the proposed 2.0 blueprint |
| [`docs/phase2-data-architecture.html`](docs/phase2-data-architecture.html) | Full data architecture: domain model, entity model, ERDs, table specifications, keys, constraints, indexes, normalization, soft-delete strategy, and migration mapping |

Every figure in both documents was derived by querying the actual production dump, not estimated.

## Repository layout

```
.
├── arams-main/                     ARAMS 1.0 — the existing PHP/MySQL system
│   ├── pages/                      live portals (admin · tdpp · lecturer)
│   ├── api/                        AJAX endpoints
│   ├── includes/                   shared header, auth, mailer, KPI engine
│   ├── config/                     database configuration
│   ├── assets/                     CSS, JS, images
│   └── admin/ lecturer/ grant/ …   abandoned first generation (see audit)
│
├── database/
│   └── arams_uthm_schema.sql       structure only — no data (see below)
│
└── docs/                           Phase 0–2 deliverables
```

## Database

`database/arams_uthm_schema.sql` contains **structure only**: 20 tables, 4 views, all indexes and foreign keys, with every `INSERT` removed.

The populated dump holds real staff names, `@uthm.edu.my` addresses, phone numbers and bcrypt password hashes for around 100 people. **It is deliberately excluded from this repository** and is gitignored. Obtain it separately if you need it, and do not commit it.

## Locked design decisions

These were approved before Phase 2 and are not to be reopened without the project owner's agreement.

| | Decision |
|---|---|
| **D1** | **TDPP alone validates** research submissions. Admin has no validation authority and no fallback. |
| **D2** | **H-Index is an institution-maintained metric snapshot**, outside the submission and validation workflow. |
| **D3** | **One submission = exactly one research record.** No bundling. |
| **D4** | **KPI credit follows the record's own effective date**, not its approval date. |
| **D5** | **TDPP sees their own faculty in full**, plus anonymised institutional benchmarking only. |
| **D6** | **Research Projects and Postgraduate Supervision are out of scope**, but the architecture must remain extensible to them. |

Roles are exactly three: **Lecturer**, **TDPP**, **Admin**. TNCPI is not an application role.

## Open questions blocking Phase 3

1. **FKAAS has 77 lecturers and no TDPP appointment.** Under D1 their submissions have no validator. Proposed: refuse submission with a clear message and raise a coverage alert to Admin — no Admin fallback.
2. **88 records have no effective date** (70 of 71 grants, all 18 IP records). Proposed: migrate with `effective_date_precision = 'UNKNOWN'`, count in totals, exclude from period-scoped KPI, and surface on an Admin backfill worklist.

Three further questions — grant deduplication, the 77 inactive accounts, and the benchmark suppression threshold — shape the migration but do not block the schema. All five are detailed at the end of the Phase 2 document.

## Security notice for ARAMS 1.0

The audit found defects in the existing system that need attention independently of this rebuild. The three most urgent, in full detail in the audit document:

- The profile-photo upload accepts an unvalidated file extension and a client-supplied MIME type, writing into a web-served directory — **remote code execution**.
- 24 of 25 portal pages perform no server-side role check, so any authenticated user can reach Admin and TDPP pages by URL.
- The abandoned first-generation tree is still reachable and contains SQL injection and plaintext password comparison. It should be deleted.

The SMTP credential in `arams-main/config/mail.php` is gitignored and is **not** in this repository, but it should be rotated regardless.

## Target stack

**Backend** Laravel · PHP 8.2+ · MySQL 8 (strict mode) · Sanctum
**Frontend** React 18 · TypeScript · Vite · Tailwind · shadcn/ui · TanStack Query · React Hook Form + Zod · Recharts

```
React SPA  →  REST API (/api/v1)  →  Laravel  →  MySQL
```

## Licence

Academic project — not licensed for redistribution.

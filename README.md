# ARAMS 2.0

**Academic Research Analytics and Monitoring System** — Universiti Tun Hussein Onn Malaysia (UTHM)

A rebuild of ARAMS, the centralised platform that tracks lecturer research output (publications, grants, H-Index, intellectual property, research income, awards), routes it through faculty-level validation, and reports on institutional research performance.

> Final Year Project — Muaz Ramzi, supervised by Dr Shuhaida binti Ismail

---

## Status

Database, API and the core web application are built and verified end to end.

| Phase | Scope | State |
|---|---|---|
| 0 | Discovery — inspect existing code, schema, and real data | ✅ Complete |
| 1 | System audit — strengths, defects, technical debt | ✅ Complete |
| 2 | Data architecture — domain model, ERD, tables, constraints | ✅ Complete, under review |
| 3 | Database implementation — migrations, models, seeders | ✅ Complete and verified |
| 4a | Backend — auth, roles, policies, audit | ✅ Complete and verified |
| 4b | Backend — research records, submissions, validation | ✅ Complete and verified |
| 4c | Backend — KPI engine and endpoints | ✅ Complete and verified |
| 4d | Backend — analytics, reports, notifications, audit | ✅ Complete (CSV reports; PDF/XLSX pending) |
| 5 | Frontend — React app for Lecturer, TDPP, Admin | ✅ Core complete and verified |
| 6 | Integration — React ↔ API ↔ MySQL | ✅ Verified in the running app |
| 7–8 | Full test coverage, accessibility, hardening | In progress |

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
├── backend/                        ARAMS 2.0 — Laravel 12 API
│   ├── app/Models/                 34 Eloquent models + 19 reference models
│   ├── app/Enums/                  11 backed enums
│   ├── app/Http/Controllers/Api/V1 auth · research · submissions · KPI
│   │                                analytics · reports · notifications · audit
│   ├── app/Policies/               6 policies — where D1 is enforced
│   ├── app/Services/               workflow · KPI · analytics · reporting
│   │                                notifications · audit · attribution
│   ├── database/migrations/        15 migrations — 69 tables
│   ├── database/seeders/           reference data and initial data
│   └── tests/Feature/              constraints · role boundaries · lifecycle
│
├── frontend/                       ARAMS 2.0 — React 19 + TypeScript + Vite
│   ├── src/features/               auth · dashboard · research · submissions
│   │                                validation · analytics · reports · audit
│   ├── src/components/ui/          shared primitives, incl. the four async states
│   └── src/lib/api.ts              typed client with the shared error envelope
│
├── database/
│   └── arams_uthm_schema.sql       ARAMS 1.0 structure only — no data
│
└── docs/                           Phase 0–2 deliverables
```

## Running the backend

Requires PHP 8.2+, Composer, and MySQL 8 or MariaDB 10.4+.

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
```

The schema is verified against MariaDB 10.4 in strict mode. `php artisan test`
runs the constraint suite, which asserts that each locked decision is enforced
by the database rather than by convention.

| | Verified |
|---|---|
| Tables | 69 |
| Foreign keys | 88 |
| Unique constraints | 52 |
| Check constraints | 25 |
| Indexes | 232 |
| API routes (`/api/v1`) | 44 |
| Tests | 51 passing, 165 assertions |

## Running the frontend

```bash
cd frontend
npm install
npm run dev
```

Vite proxies `/api` to `http://127.0.0.1:8000`, so run `php artisan serve` in
`backend/` alongside it. For local accounts:

```bash
php artisan db:seed --class=DevelopmentSeeder
```

That creates a lecturer, a second lecturer, two TDPPs (one appointed to FSKTM,
one to FKEE), and an admin, plus records in every workflow state. The seeder
refuses to run in production and its password is printed to the console rather
than into the UI — ARAMS 1.0 printed three working logins on its own login page.

### What the tests prove

Each one reproduces a defect measured in the real ARAMS 1.0 data and asserts
that ARAMS 2.0 refuses it:

- **`SchemaConstraintTest`** — two submissions on one record, an undeclared
  missing effective date, a duplicate grant claim, conflicting H-Index
  readings, an incoherent KPI scope, rewriting validation history.
- **`RoleBoundaryTest`** — Admin cannot approve (D1), a TDPP cannot approve
  outside their faculty or without a current appointment, nobody reviews their
  own work, a lecturer cannot read a colleague's record by changing the id in
  the URL, and submission is refused where no TDPP is appointed.
- **`SubmissionLifecycleTest`** — draft → submit → revision requested →
  resubmit → approve, with both decisions preserved; credit lands in the
  period of the publication year rather than the approval date (D4); progress
  falls again when a record is deleted.
- **`AnalyticsReportingTest`** — analytics scope derived from the token, not
  the request; the breakdown dimension is whitelisted so no column name comes
  from the client; D5 benchmarks suppress the median until enough faculties
  report; a new submission notifies the serving TDPP and *not* Admin; reports
  are scoped at generation and bind that scope into the artifact.

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

## Open questions

The schema accommodates both of these, so Phase 3 was not blocked. They block
the **data migration** from ARAMS 1.0, which is a separate reviewed step.

1. **FKAAS has 77 lecturers and no TDPP appointment.** Under D1 their submissions
   have no validator. The schema models TDPP as a dated appointment
   (`faculty_leaders`), so faculties without one are queryable and raise a
   coverage alert — no Admin fallback was introduced. Needs an appointment
   before those accounts are activated.
2. **88 records have no effective date** (70 of 71 grants, all 18 IP records).
   They migrate with `effective_date_precision = 'UNKNOWN'`: counted in totals,
   excluded from period-scoped KPI, surfaced on an Admin backfill worklist.
   Needs an owner for the backfill.

Three further questions — grant deduplication, the 77 inactive accounts, and the
benchmark suppression threshold — shape the migration but not the schema. All
five are detailed at the end of the Phase 2 document.

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

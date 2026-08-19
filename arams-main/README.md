# ARAMS — Academic Research Activity Monitoring System

A web-based research activity monitoring and analytics system for **Universiti Tun Hussein Onn Malaysia (UTHM)**. ARAMS tracks lecturer research output (publications, grants, intellectual property, research income, H-Index), manages faculty-level KPI targets, and provides drill-down analytics across the institution.

> Final Year Project — Muaz Ramzi. supervised Dr Shuhaida binti Ismail

## Roles

ARAMS supports three user roles, each with a dedicated portal:

- **Lecturer** — manage own profile and research records, track assigned KPI tasks, view a personal research timeline and analytics.
- **TDPP (Timbalan Dekan P&P / Deputy Dean)** — faculty-scoped oversight: monitor lecturers in their own faculty, assign and track KPI tasks, validate (approve/reject) submitted research, and view faculty-wide and per-lecturer analytics.
- **Admin** — institution-wide management: all lecturers, user management, reporting, system-wide analytics, and audit log.

## Features

### Lecturer
- Personal dashboard with KPI summary (publications, grants, H-Index, research income)
- Profile management with photo upload and research IDs (Scopus, ORCID, ResearcherID, etc.)
- Research Management — add and track Publications, Grants, IP Records, H-Index, and Income, each with submission status (Pending / Approved / Rejected)
- KPI tasks assigned by TDPP, which auto-complete when matching research is approved
- Research timeline and personal analytics with interactive drill-down charts

### TDPP (faculty-scoped)
- Faculty dashboard and faculty-wide analytics
- My Lecturers — list of lecturers in own faculty; click through to a per-lecturer analytics page
- KPI task assignment (single and bulk)
- Validation workflow — approve/reject lecturer submissions with optional remarks; approval can auto-complete matching KPI tasks
- Faculty Members — view-only list of lecturer accounts in own faculty

### Admin
- Institution-wide dashboard and analytics
- All Lecturers directory with search and filters
- User management (create, edit, activate/deactivate accounts)
- Report generation (by year / faculty / lecturer)
- System-wide audit log

### Analytics (Admin, TDPP & Lecturer)
- KPI cards, publication-by-year bar charts, and donut charts (quartile distribution, publication types, grant categories, grant roles)
- **Interactive drill-down** — click a chart segment to view the underlying records. For Admin, drill-down first breaks results down by faculty, then into individual records with lecturer names.
- Hover tooltips on donut segments

## Security & access control

- Role-based portals with server-side guards (e.g. the audit log is Admin-only and rejects direct URL access by other roles).
- Faculty scoping enforced server-side — a TDPP can only view lecturers and data within their own faculty; attempts to access another faculty's records (e.g. via URL tampering) are denied.
- Only **Approved** research records feed into KPI totals and analytics.

## Tech Stack

- **Backend:** PHP (PDO), vanilla — no framework
- **Database:** MySQL (`arams_uthm`)
- **Frontend:** Vanilla JS, custom CSS (CSS custom properties)
- **Runtime:** XAMPP (Apache + MySQL) on Windows

## Data model (overview)

All research entries (`tbl_publication`, `tbl_grant`, `tbl_hindex`, `tbl_research_income`, IP records) link to a parent `tbl_research_data` row, which carries the submission `status` (Pending / Approved / Rejected). KPI figures and charts are derived from approved records, aggregated through the `vw_lecturer_kpi` view. Lecturers belong to faculties (`tbl_faculty`); TDPP accounts are mapped to a faculty via `tbl_tdpp`.

## Local Setup

1. Install [XAMPP](https://www.apachefriends.org/).
2. Clone this repo into `C:\xampp\htdocs\arams`.
3. Start Apache + MySQL from the XAMPP control panel.
4. Open phpMyAdmin and create a database named `arams_uthm`.
5. Import the schema (`arams_uthm.sql`) via phpMyAdmin → Import.
6. Visit `http://localhost/arams/`.

## Configuration

Database credentials live in `config/database.php` (PDO connection to `arams_uthm`).

Defaults are XAMPP standard (`root` / no password). **Change before any non-local deployment.**

## Project Structure
arams/
├── api/            # AJAX endpoints (validate, notifications, user actions, analytics_detail, etc.)
├── assets/         # CSS, JS (main.js), images, profile photos
├── config/         # DB connection (database.php)
├── includes/       # Shared header/sidebar, auth helpers
├── pages/
│   ├── admin/      # Admin portal (dashboard, lecturers, analytics, reports, users, audit_log)
│   ├── tdpp/       # TDPP portal (dashboard, lecturers, lecturer_analytics, kpi, validation, analytics, users)
│   └── lecturer/   # Lecturer portal (dashboard, profile, research, tasks, timeline, analytics)
└── uploads/        # User-uploaded files (gitignored)

## Status

Built and verified across all three roles. Known items for production hardening: server-side role guards on every API endpoint, graceful DB error handling, password reset flow, HTTPS/hosting, and supervisor + faculty IT review before real staff data is used.

## License

Academic project — not licensed for redistribution.
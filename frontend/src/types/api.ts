export type Role = "Lecturer" | "TDPP" | "Admin";

export type SubmissionStatus =
  | "DRAFT"
  | "SUBMITTED"
  | "UNDER_REVIEW"
  | "APPROVED"
  | "REJECTED"
  | "REVISION_REQUESTED"
  | "WITHDRAWN"
  | "SUPERSEDED";

export type DatePrecision = "DAY" | "MONTH" | "YEAR" | "UNKNOWN";

export type AuthUser = {
  id: number;
  email: string;
  role: Role;
  staff: { id: number; staff_no: string; full_name: string; title: string | null } | null;
  faculty: { id: number; code: string; name: string } | null;
  /** Faculties this user may validate for — empty unless a serving TDPP. */
  validates_faculties: number[];
};

export type Review = {
  id: number;
  revision_no: number;
  decision: "APPROVED" | "REJECTED" | "REVISION_REQUESTED";
  remarks: string | null;
  decided_at: string | null;
  reviewer: { id: number; name: string; role: string } | null;
  is_legacy: boolean;
  /** True for migrated ARAMS 1.0 decisions whose reviewer was never recorded. */
  reviewer_unknown: boolean;
};

export type ResearchRecord = {
  id: number;
  type: string | null;
  display_title: string;
  effective_date: string | null;
  effective_date_precision: DatePrecision;
  needs_date_backfill: boolean;
  attributed_faculty_id: number | null;
  attribution_basis: string | null;
  owner: { id: number; full_name: string; staff_no: string } | null;
  submission: {
    id: number;
    status: SubmissionStatus;
    current_revision: number;
    submitted_at: string | null;
    decided_at: string | null;
    approver_unknown: boolean;
    editable: boolean;
    reviews: Review[] | null;
  } | null;
  detail?: Record<string, unknown> | null;
  deleted_at: string | null;
  deletion_reason: string | null;
};

export type Submission = {
  id: number;
  status: SubmissionStatus;
  current_revision: number;
  origin: "ARAMS_2" | "MIGRATED_V1";
  submitted_at: string | null;
  first_submitted_at: string | null;
  decided_at: string | null;
  claimed_at: string | null;
  faculty_id_at_submission: number | null;
  approver_unknown: boolean;
  record?: {
    id: number;
    type: string | null;
    display_title: string;
    effective_date: string | null;
    effective_date_precision: DatePrecision;
    needs_date_backfill: boolean;
    attributed_faculty_id: number | null;
    owner: { id: number; full_name: string; staff_no: string } | null;
  };
  reviews?: Review[];
  revisions?: { revision_no: number; submitted_at: string | null }[];
};

export type Transition = {
  from_status: string;
  to_status: SubmissionStatus;
  actor: string;
  requires_remarks: boolean;
  label: string;
};

export type AnalyticsOverview = {
  scope: "INSTITUTION" | "FACULTY" | "STAFF";
  period: string | null;
  totals: {
    records: number;
    publications: number;
    grants: number;
    ip_records: number;
    income: number;
    awards: number;
  };
  grant_value: number;
  income_total: number;
  latest_hindex: { value: number; citations: number | null; record_year: number } | null;
  data_quality: { records_missing_effective_date: number };
};

export type TrendPoint = { year: number; total: number };

export type Benchmark = {
  faculty_id: number;
  your_value: number;
  institution_median?: number;
  cohort_size?: number;
  suppressed: boolean;
  reason?: string;
};

export type CoverageGap = { faculty_id: number; code: string; name: string };

export type Notification = {
  id: string;
  type: string;
  data: Record<string, unknown>;
  action_url: string | null;
  read_at: string | null;
  created_at: string;
};

export type Envelope<T> = { data: T; meta?: Record<string, unknown> };

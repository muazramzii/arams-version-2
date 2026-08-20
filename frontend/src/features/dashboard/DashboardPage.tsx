import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api } from "../../lib/api";
import { useAuth } from "../auth/AuthContext";
import {
  Card,
  CardHeader,
  EmptyState,
  ErrorState,
  LoadingState,
  Stat,
  StatRow,
  StatusBadge,
  Table,
  Td,
  Th,
  Chip,
} from "../../components/ui";
import type { AnalyticsOverview, CoverageGap, Envelope, Submission, TrendPoint } from "../../types/api";

const money = new Intl.NumberFormat("en-MY", {
  style: "currency",
  currency: "MYR",
  maximumFractionDigits: 0,
});

export function DashboardPage() {
  const { user, can } = useAuth();

  const overview = useQuery({
    queryKey: ["analytics", "overview"],
    queryFn: () => api.get<Envelope<AnalyticsOverview>>("/analytics/overview"),
  });

  const trends = useQuery({
    queryKey: ["analytics", "trends"],
    queryFn: () => api.get<Envelope<TrendPoint[]>>("/analytics/trends"),
  });

  const scopeLabel =
    overview.data?.data.scope === "INSTITUTION"
      ? "Institution-wide"
      : overview.data?.data.scope === "FACULTY"
        ? `${user?.faculty?.code ?? "Faculty"} — faculty-wide`
        : "Your research";

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Dashboard</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          {scopeLabel} · validated records only
        </p>
      </div>

      {overview.isLoading && <LoadingState rows={3} label="Loading dashboard" />}
      {overview.isError && <ErrorState error={overview.error} onRetry={() => overview.refetch()} />}

      {overview.data && (
        <>
          <StatRow>
            <Stat label="Validated records" value={overview.data.data.totals.records} />
            <Stat label="Publications" value={overview.data.data.totals.publications} />
            <Stat label="Grants" value={overview.data.data.totals.grants} />
            <Stat label="Intellectual property" value={overview.data.data.totals.ip_records} />
            <Stat label="Awards" value={overview.data.data.totals.awards} />
            <Stat
              label="Research income"
              value={money.format(overview.data.data.income_total)}
            />
          </StatRow>

          {/*
            Surfaced rather than buried: these records count in totals but
            cannot be placed in a KPI period until someone supplies a date.
          */}
          {overview.data.data.data_quality.records_missing_effective_date > 0 && (
            <Card className="border-l-2 border-l-[--color-warn]">
              <div className="flex items-start gap-3 px-4 py-3">
                <Chip tone="warn">Needs attention</Chip>
                <p className="text-[13px] text-[--color-ink-2]">
                  <strong>
                    {overview.data.data.data_quality.records_missing_effective_date} record(s)
                  </strong>{" "}
                  have no effective date. They are counted in totals but excluded from
                  period-based KPI and trend charts until a date is added.{" "}
                  <Link className="text-[--color-accent] underline" to="/research?needs_date=1">
                    Review them
                  </Link>
                </p>
              </div>
            </Card>
          )}

          {overview.data.data.latest_hindex && (
            <StatRow>
              <Stat
                label="H-Index"
                value={overview.data.data.latest_hindex.value}
                hint={`Recorded ${overview.data.data.latest_hindex.record_year}`}
              />
              <Stat
                label="Citations"
                value={overview.data.data.latest_hindex.citations ?? "—"}
              />
            </StatRow>
          )}
        </>
      )}

      <div className="grid gap-5 lg:grid-cols-2">
        <TrendCard query={trends} />
        {can.validate ? <ValidationSummary /> : <MySubmissions />}
      </div>

      {user?.role === "Admin" && <CoverageGaps />}
    </div>
  );
}

/** Output by year. Says so plainly when there is not enough data to draw. */
function TrendCard({ query }: { query: ReturnType<typeof useQuery<Envelope<TrendPoint[]>>> }) {
  const points = query.data?.data ?? [];
  const max = Math.max(...points.map((p) => p.total), 1);

  return (
    <Card>
      <CardHeader title="Output by year" />
      {query.isLoading && <LoadingState rows={3} />}
      {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
      {query.data && points.length === 0 && (
        <EmptyState
          title="Nothing to chart yet"
          description="Validated records with a known date will appear here."
        />
      )}
      {points.length > 0 && (
        <div className="flex items-end gap-2 p-4" style={{ height: 160 }}>
          {points.map((point) => (
            <div key={point.year} className="flex flex-1 flex-col items-center gap-1">
              <span className="tabular font-mono text-[10px] text-[--color-ink-3]">
                {point.total}
              </span>
              <div
                className="w-full rounded-t-sm bg-[--color-accent]"
                style={{ height: `${(point.total / max) * 110}px`, minHeight: 2 }}
              />
              <span className="tabular font-mono text-[10px] text-[--color-ink-3]">
                {point.year}
              </span>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function MySubmissions() {
  const query = useQuery({
    queryKey: ["submissions", "mine"],
    queryFn: () => api.get<Envelope<Submission[]>>("/submissions"),
  });

  const rows = (query.data?.data ?? []).slice(0, 6);

  return (
    <Card>
      <CardHeader
        title="My submissions"
        action={
          <Link className="text-[12px] text-[--color-accent] hover:underline" to="/research">
            View all
          </Link>
        }
      />
      {query.isLoading && <LoadingState rows={3} />}
      {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
      {query.data && rows.length === 0 && (
        <EmptyState
          title="No submissions yet"
          description="Add a research record and submit it for validation."
        />
      )}
      {rows.length > 0 && (
        <Table>
          <thead>
            <tr>
              <Th>Title</Th>
              <Th>Status</Th>
            </tr>
          </thead>
          <tbody>
            {rows.map((s) => (
              <tr key={s.id}>
                <Td>
                  <Link className="hover:text-[--color-accent]" to={`/submissions/${s.id}`}>
                    {s.record?.display_title ?? `Submission #${s.id}`}
                  </Link>
                </Td>
                <Td>
                  <StatusBadge status={s.status} />
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  );
}

function ValidationSummary() {
  const query = useQuery({
    queryKey: ["submissions", "queue"],
    queryFn: () => api.get<Envelope<Submission[]>>("/submissions/queue"),
  });

  const rows = query.data?.data ?? [];

  return (
    <Card>
      <CardHeader
        title="Awaiting your validation"
        action={
          <Link className="text-[12px] text-[--color-accent] hover:underline" to="/validation">
            Open queue
          </Link>
        }
      />
      {query.isLoading && <LoadingState rows={3} />}
      {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
      {query.data && rows.length === 0 && (
        <EmptyState title="Queue is clear" description="Nothing is waiting for review." />
      )}
      {rows.length > 0 && (
        <Table>
          <thead>
            <tr>
              <Th>Researcher</Th>
              <Th>Title</Th>
              <Th>Status</Th>
            </tr>
          </thead>
          <tbody>
            {rows.slice(0, 6).map((s) => (
              <tr key={s.id}>
                <Td className="whitespace-nowrap">{s.record?.owner?.full_name ?? "—"}</Td>
                <Td>
                  <Link className="hover:text-[--color-accent]" to={`/submissions/${s.id}`}>
                    {s.record?.display_title}
                  </Link>
                </Td>
                <Td>
                  <StatusBadge status={s.status} />
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  );
}

/**
 * D1 has no Admin fallback, so a faculty with no serving TDPP cannot process
 * submissions at all. FKAAS is the live example: 77 lecturers, no appointment.
 */
function CoverageGaps() {
  const query = useQuery({
    queryKey: ["submissions", "coverage-gaps"],
    queryFn: () => api.get<Envelope<CoverageGap[]>>("/submissions/coverage-gaps"),
  });

  const gaps = query.data?.data ?? [];

  if (query.isLoading || gaps.length === 0) return null;

  return (
    <Card className="border-l-2 border-l-[--color-crit]">
      <CardHeader title="Faculties with no serving TDPP" />
      <div className="px-4 py-3">
        <p className="mb-2 text-[13px] text-[--color-ink-2]">
          Lecturers in these faculties cannot submit research for validation, because no one is
          appointed to review it.
        </p>
        <ul className="flex flex-wrap gap-1.5">
          {gaps.map((gap) => (
            <li key={gap.faculty_id}>
              <Chip tone="crit">{gap.code}</Chip>
            </li>
          ))}
        </ul>
      </div>
    </Card>
  );
}

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import {
  Card,
  CardHeader,
  EmptyState,
  ErrorState,
  LoadingState,
  Select,
  Stat,
  StatRow,
} from "../../components/ui";
import { useAuth } from "../auth/AuthContext";
import type { AnalyticsOverview, Benchmark, Envelope, TrendPoint } from "../../types/api";

const DIMENSIONS = [
  { value: "quartile", label: "Publication quartile" },
  { value: "indexing", label: "Indexing" },
  { value: "publication_type", label: "Publication type" },
  { value: "grant_level", label: "Grant level" },
  { value: "grant_role", label: "Grant role" },
  { value: "research_type", label: "Research type" },
  { value: "faculty", label: "Faculty" },
];

export function AnalyticsPage() {
  const { user } = useAuth();
  const [dimension, setDimension] = useState("quartile");

  const overview = useQuery({
    queryKey: ["analytics", "overview"],
    queryFn: () => api.get<Envelope<AnalyticsOverview>>("/analytics/overview"),
  });

  const trends = useQuery({
    queryKey: ["analytics", "trends"],
    queryFn: () => api.get<Envelope<TrendPoint[]>>("/analytics/trends"),
  });

  const breakdown = useQuery({
    queryKey: ["analytics", "breakdown", dimension],
    queryFn: () => api.get<Envelope<Record<string, number>>>("/analytics/breakdown", { dimension }),
  });

  const entries = Object.entries(breakdown.data?.data ?? {}).filter(([, value]) => value > 0);
  const maxBreakdown = Math.max(...entries.map(([, value]) => value), 1);
  const points = trends.data?.data ?? [];
  const maxTrend = Math.max(...points.map((point) => point.total), 1);

  const scopeNote =
    overview.data?.data.scope === "INSTITUTION"
      ? "All faculties."
      : overview.data?.data.scope === "FACULTY"
        ? `${user?.faculty?.code ?? "Your faculty"} only.`
        : "Your own research only.";

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Analytics</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          {scopeNote} Validated records only.
        </p>
      </div>

      {overview.isLoading && <LoadingState rows={2} />}
      {overview.isError && <ErrorState error={overview.error} onRetry={() => overview.refetch()} />}
      {overview.data && (
        <StatRow>
          <Stat label="Validated records" value={overview.data.data.totals.records} />
          <Stat label="Publications" value={overview.data.data.totals.publications} />
          <Stat label="Grants" value={overview.data.data.totals.grants} />
          <Stat
            label="No effective date"
            value={overview.data.data.data_quality.records_missing_effective_date}
            tone={
              overview.data.data.data_quality.records_missing_effective_date > 0 ? "warn" : undefined
            }
            hint="Excluded from period charts"
          />
        </StatRow>
      )}

      <Card>
        <CardHeader title="Output by year" />
        {trends.isLoading && <LoadingState rows={3} />}
        {trends.isError && <ErrorState error={trends.error} onRetry={() => trends.refetch()} />}
        {trends.data && points.length === 0 && (
          <EmptyState
            title="Not enough data to chart"
            description="Validated records with a known effective date will appear here."
          />
        )}
        {points.length > 0 && (
          <div className="flex items-end gap-2 p-4" style={{ height: 200 }}>
            {points.map((point) => (
              <div key={point.year} className="flex flex-1 flex-col items-center gap-1">
                <span className="tabular font-mono text-[10px] text-[--color-ink-3]">
                  {point.total}
                </span>
                <div
                  className="w-full rounded-t-sm bg-[--color-accent]"
                  style={{ height: `${(point.total / maxTrend) * 145}px`, minHeight: 2 }}
                />
                <span className="tabular font-mono text-[10px] text-[--color-ink-3]">
                  {point.year}
                </span>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Card>
        <CardHeader
          title="Breakdown"
          action={
            <Select
              aria-label="Breakdown dimension"
              className="w-auto py-1 text-xs"
              value={dimension}
              onChange={(event) => setDimension(event.target.value)}
            >
              {DIMENSIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          }
        />
        {breakdown.isLoading && <LoadingState rows={4} />}
        {breakdown.isError && (
          <ErrorState error={breakdown.error} onRetry={() => breakdown.refetch()} />
        )}
        {breakdown.data && entries.length === 0 && <EmptyState title="No data for this breakdown" />}
        {entries.length > 0 && (
          <ul className="flex flex-col gap-2 p-4">
            {entries.map(([label, value]) => (
              <li key={label} className="flex items-center gap-3">
                <span className="w-40 shrink-0 truncate text-[12px]">{label || "Unspecified"}</span>
                <div className="h-4 flex-1 rounded-sm bg-[--color-surface-2]">
                  <div
                    className="h-full rounded-sm bg-[--color-accent]"
                    style={{ width: `${(value / maxBreakdown) * 100}%` }}
                  />
                </div>
                <span className="tabular w-8 text-right font-mono text-[11px]">{value}</span>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {user?.role === "TDPP" && user.faculty && <BenchmarkCard facultyId={user.faculty.id} />}
    </div>
  );
}

/** D5: your faculty against an institution median, with no other faculty named. */
function BenchmarkCard({ facultyId }: { facultyId: number }) {
  const query = useQuery({
    queryKey: ["analytics", "benchmark", facultyId],
    queryFn: () => api.get<Envelope<Benchmark>>("/analytics/benchmark", { faculty_id: facultyId }),
  });

  const data = query.data?.data;

  return (
    <Card>
      <CardHeader title="How your faculty compares" />
      {query.isLoading && <LoadingState rows={2} />}
      {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}

      {data?.suppressed && (
        <div className="px-4 py-5">
          <p className="text-[13px] text-[--color-ink-2]">{data.reason}</p>
          <p className="mt-2 text-[12px] text-[--color-ink-3]">
            Your faculty: <strong className="tabular">{data.your_value}</strong> validated records.
          </p>
        </div>
      )}

      {data && !data.suppressed && (
        <StatRow>
          <Stat label="Your faculty" value={data.your_value} />
          <Stat label="Institution median" value={data.institution_median ?? "—"} />
          <Stat
            label="Faculties compared"
            value={data.cohort_size ?? 0}
            hint="Anonymised — no faculty is named"
          />
        </StatRow>
      )}
    </Card>
  );
}

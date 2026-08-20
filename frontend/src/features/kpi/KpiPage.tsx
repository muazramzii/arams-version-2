import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import {
  Card,
  CardHeader,
  Chip,
  EmptyState,
  ErrorState,
  LoadingState,
  Table,
  Td,
  Th,
} from "../../components/ui";
import { useAuth } from "../auth/AuthContext";
import type { Envelope } from "../../types/api";

type Assignment = {
  id: number;
  deadline: string | null;
  status: "OPEN" | "MET" | "MET_LATE" | "MISSED" | "CANCELLED";
  target: {
    id: number;
    target_value: string;
    description: string | null;
    measure: { code: string; label: string; unit: string | null };
    period: { code: string; label: string };
  };
  progress: { achieved_value: string; target_value: string; percentage: string }[];
};

type Contribution = {
  research_record_id: number;
  title: string | null;
  counted_on: string | null;
  contributed_value: number;
};

const STATUS: Record<Assignment["status"], { label: string; tone: "neutral" | "good" | "warn" | "crit" }> = {
  OPEN: { label: "In progress", tone: "neutral" },
  MET: { label: "Met", tone: "good" },
  MET_LATE: { label: "Met (late)", tone: "warn" },
  MISSED: { label: "Missed", tone: "crit" },
  CANCELLED: { label: "Cancelled", tone: "neutral" },
};

export function KpiPage() {
  const { user } = useAuth();
  const [expanded, setExpanded] = useState<number | null>(null);

  const assignments = useQuery({
    queryKey: ["kpi", "assignments"],
    queryFn: () => api.get<Envelope<Assignment[]>>("/kpi/assignments"),
  });

  const rows = assignments.data?.data ?? [];

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">KPI</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          Targets assigned to {user?.staff?.full_name ?? "you"}. Credit follows the record's own
          date, not when it was approved.
        </p>
      </div>

      <Card>
        <CardHeader title="My targets" />
        {assignments.isLoading && <LoadingState rows={4} label="Loading targets" />}
        {assignments.isError && (
          <ErrorState error={assignments.error} onRetry={() => assignments.refetch()} />
        )}
        {assignments.data && rows.length === 0 && (
          <EmptyState
            title="No KPI targets assigned"
            description="Your faculty's TDPP assigns these. Approved work in the period counts automatically."
          />
        )}

        {rows.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Target</Th>
                <Th>Period</Th>
                <Th>Progress</Th>
                <Th>Deadline</Th>
                <Th>Status</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {rows.map((assignment) => {
                const progress = assignment.progress?.[0];
                const achieved = Number(progress?.achieved_value ?? 0);
                const target = Number(assignment.target.target_value);
                const percentage = Number(progress?.percentage ?? 0);
                const status = STATUS[assignment.status];

                return (
                  <tr key={assignment.id}>
                    <Td>
                      <div className="font-medium">{assignment.target.measure.label}</div>
                      {assignment.target.description && (
                        <div className="text-[12px] text-[--color-ink-3]">
                          {assignment.target.description}
                        </div>
                      )}
                    </Td>
                    <Td className="whitespace-nowrap">{assignment.target.period.code}</Td>
                    <Td>
                      <div className="flex items-center gap-2">
                        <div className="h-2 w-24 rounded-sm bg-[--color-surface-2]">
                          <div
                            className="h-full rounded-sm bg-[--color-accent]"
                            style={{ width: `${Math.min(percentage, 100)}%` }}
                          />
                        </div>
                        <span className="tabular font-mono text-[11px]">
                          {achieved} / {target}
                        </span>
                      </div>
                    </Td>
                    <Td className="tabular whitespace-nowrap">{assignment.deadline ?? "—"}</Td>
                    <Td>
                      <Chip tone={status.tone}>{status.label}</Chip>
                    </Td>
                    <Td>
                      {/*
                        The difference between a progress number and an
                        auditable one. ARAMS 1.0 showed a bare counter that
                        reached 19 against a target of 1, with no way to see
                        what it had counted.
                      */}
                      <button
                        className="text-[12px] text-[--color-accent] hover:underline"
                        onClick={() =>
                          setExpanded(expanded === assignment.id ? null : assignment.id)
                        }
                      >
                        {expanded === assignment.id ? "Hide" : "What counted?"}
                      </button>
                    </Td>
                  </tr>
                );
              })}
            </tbody>
          </Table>
        )}
      </Card>

      {expanded !== null && <ContributionsCard assignmentId={expanded} />}
    </div>
  );
}

function ContributionsCard({ assignmentId }: { assignmentId: number }) {
  const query = useQuery({
    queryKey: ["kpi", "contributions", assignmentId],
    queryFn: () =>
      api.get<Envelope<{ contributions: Contribution[] }>>(
        `/kpi/assignments/${assignmentId}/contributions`,
      ),
  });

  const contributions = query.data?.data.contributions ?? [];

  return (
    <Card>
      <CardHeader title="Records that counted toward this target" />
      {query.isLoading && <LoadingState rows={3} />}
      {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
      {query.data && contributions.length === 0 && (
        <EmptyState
          title="Nothing has counted yet"
          description="Only approved records dated inside the target's period contribute."
        />
      )}
      {contributions.length > 0 && (
        <Table>
          <thead>
            <tr>
              <Th>Record</Th>
              <Th>Counted on</Th>
              <Th>Value</Th>
            </tr>
          </thead>
          <tbody>
            {contributions.map((contribution) => (
              <tr key={contribution.research_record_id}>
                <Td>{contribution.title ?? `Record #${contribution.research_record_id}`}</Td>
                <Td className="tabular whitespace-nowrap">{contribution.counted_on ?? "—"}</Td>
                <Td className="tabular">{contribution.contributed_value}</Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  );
}

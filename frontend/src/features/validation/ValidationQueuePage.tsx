import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import {
  Card,
  Chip,
  EmptyState,
  ErrorState,
  LoadingState,
  StatusBadge,
  Table,
  Td,
  Th,
} from "../../components/ui";
import { useAuth } from "../auth/AuthContext";
import type { Envelope, Submission } from "../../types/api";

/**
 * The TDPP queue. Scoped server-side to the faculties this user is appointed
 * to — anything shown here is presentation, not protection.
 */
export function ValidationQueuePage() {
  const { can } = useAuth();

  const query = useQuery({
    queryKey: ["submissions", "queue"],
    queryFn: () => api.get<Envelope<Submission[]>>("/submissions/queue"),
  });

  const rows = query.data?.data ?? [];

  // D1 has no Admin fallback, so an unappointed TDPP genuinely cannot act.
  if (!can.validate) {
    return (
      <Card>
        <EmptyState
          title="You have no active TDPP appointment"
          description="Validation is limited to the faculty you are currently appointed to. Ask an administrator to record your appointment."
        />
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Validation Queue</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          Submissions from your faculty awaiting a decision.
        </p>
      </div>

      <Card>
        {query.isLoading && <LoadingState rows={5} label="Loading queue" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
        {query.data && rows.length === 0 && (
          <EmptyState title="Queue is clear" description="Nothing is waiting for your review." />
        )}
        {rows.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Researcher</Th>
                <Th>Title</Th>
                <Th>Type</Th>
                <Th>Submitted</Th>
                <Th>Status</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((submission) => (
                <tr key={submission.id} className="hover:bg-[--color-surface-2]">
                  <Td className="whitespace-nowrap">
                    {submission.record?.owner?.full_name ?? "—"}
                  </Td>
                  <Td>
                    <Link
                      className="font-medium hover:text-[--color-accent]"
                      to={`/submissions/${submission.id}`}
                    >
                      {submission.record?.display_title}
                    </Link>
                    {submission.current_revision > 1 && (
                      <span className="ml-2">
                        <Chip>Revision {submission.current_revision}</Chip>
                      </span>
                    )}
                  </Td>
                  <Td className="whitespace-nowrap text-[--color-ink-3]">
                    {submission.record?.type?.replace("_", " ").toLowerCase()}
                  </Td>
                  <Td className="tabular whitespace-nowrap">
                    {submission.submitted_at?.slice(0, 10) ?? "—"}
                  </Td>
                  <Td>
                    <StatusBadge status={submission.status} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  );
}

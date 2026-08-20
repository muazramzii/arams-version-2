import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { Card, Chip, EmptyState, ErrorState, LoadingState, Table, Td, Th } from "../../components/ui";
import { useAuth } from "../auth/AuthContext";
import type { Envelope } from "../../types/api";

type AuditRow = {
  id: number;
  action: string;
  actor: string | null;
  actor_role: string | null;
  auditable_type: string | null;
  auditable_id: number | null;
  changes: Record<string, unknown> | null;
  created_at: string | null;
};

export function AuditPage() {
  const { user } = useAuth();

  const query = useQuery({
    queryKey: ["audit-events"],
    queryFn: () => api.get<Envelope<AuditRow[]>>("/audit-events"),
  });

  const rows = query.data?.data ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Activity Log</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          {user?.role === "Admin"
            ? "All recorded activity across the system."
            : "Your own recorded activity."}
        </p>
      </div>

      <Card>
        {query.isLoading && <LoadingState rows={6} label="Loading activity" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
        {query.data && rows.length === 0 && (
          <EmptyState title="Nothing recorded yet" description="Actions you take will appear here." />
        )}
        {rows.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>When</Th>
                <Th>Action</Th>
                <Th>Actor</Th>
                <Th>Subject</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <Td className="tabular whitespace-nowrap text-[--color-ink-3]">
                    {row.created_at?.slice(0, 16).replace("T", " ")}
                  </Td>
                  <Td>
                    {/* Typed action codes, not the free text ARAMS 1.0 stored. */}
                    <span className="font-mono text-[11px]">{row.action}</span>
                  </Td>
                  <Td className="whitespace-nowrap">
                    {row.actor ?? "system"}
                    {row.actor_role && (
                      <span className="ml-1.5">
                        <Chip>{row.actor_role}</Chip>
                      </span>
                    )}
                  </Td>
                  <Td className="text-[--color-ink-3]">
                    {row.auditable_type}
                    {row.auditable_id ? ` #${row.auditable_id}` : ""}
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

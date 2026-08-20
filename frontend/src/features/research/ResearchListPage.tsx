import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import {
  Button,
  Card,
  Chip,
  EmptyState,
  ErrorState,
  LoadingState,
  Select,
  StatusBadge,
  Table,
  Td,
  Th,
} from "../../components/ui";
import { NewRecordDialog } from "./NewRecordDialog";
import type { Envelope, ResearchRecord } from "../../types/api";

const TYPES = [
  { value: "", label: "All types" },
  { value: "PUBLICATION", label: "Publications" },
  { value: "GRANT", label: "Grants" },
  { value: "IP_RECORD", label: "Intellectual property" },
  { value: "RESEARCH_INCOME", label: "Research income" },
  { value: "AWARD", label: "Awards" },
];

export function ResearchListPage() {
  const [params, setParams] = useSearchParams();
  const [dialogOpen, setDialogOpen] = useState(false);

  const type = params.get("type") ?? "";
  const needsDate = params.get("needs_date") === "1";

  const query = useQuery({
    queryKey: ["research-records", type, needsDate],
    queryFn: () =>
      api.get<Envelope<ResearchRecord[]>>("/research-records", {
        type: type || undefined,
        needs_date_backfill: needsDate || undefined,
      }),
  });

  const records = query.data?.data ?? [];

  function setParam(key: string, value: string) {
    const next = new URLSearchParams(params);
    value ? next.set(key, value) : next.delete(key);
    setParams(next, { replace: true });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-serif text-xl font-semibold tracking-tight">My Research</h1>
          <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
            Every record you own, at any stage of validation.
          </p>
        </div>
        <Button variant="primary" onClick={() => setDialogOpen(true)}>
          Add record
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Select
          aria-label="Filter by type"
          className="w-auto"
          value={type}
          onChange={(e) => setParam("type", e.target.value)}
        >
          {TYPES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </Select>

        <label className="flex items-center gap-1.5 text-[12px] text-[--color-ink-2]">
          <input
            type="checkbox"
            checked={needsDate}
            onChange={(e) => setParam("needs_date", e.target.checked ? "1" : "")}
          />
          Missing an effective date
        </label>

        {(type || needsDate) && (
          <Button size="sm" variant="ghost" onClick={() => setParams({}, { replace: true })}>
            Clear filters
          </Button>
        )}
      </div>

      <Card>
        {query.isLoading && <LoadingState rows={5} label="Loading research records" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}

        {query.data && records.length === 0 && (
          <EmptyState
            title={needsDate || type ? "Nothing matches those filters" : "No research records yet"}
            description={
              needsDate || type
                ? "Try clearing the filters."
                : "Add a publication, grant, IP record, income entry or award to get started."
            }
            action={
              needsDate || type ? (
                <Button size="sm" onClick={() => setParams({}, { replace: true })}>
                  Clear filters
                </Button>
              ) : (
                <Button size="sm" variant="primary" onClick={() => setDialogOpen(true)}>
                  Add record
                </Button>
              )
            }
          />
        )}

        {records.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Title</Th>
                <Th>Type</Th>
                <Th>Effective date</Th>
                <Th>Status</Th>
              </tr>
            </thead>
            <tbody>
              {records.map((record) => (
                <tr key={record.id} className="hover:bg-[--color-surface-2]">
                  <Td>
                    {record.submission ? (
                      <Link
                        className="font-medium hover:text-[--color-accent]"
                        to={`/submissions/${record.submission.id}`}
                      >
                        {record.display_title}
                      </Link>
                    ) : (
                      <span className="font-medium">{record.display_title}</span>
                    )}
                  </Td>
                  <Td className="whitespace-nowrap text-[--color-ink-3]">
                    {record.type?.replace("_", " ").toLowerCase()}
                  </Td>
                  <Td className="tabular whitespace-nowrap">
                    {record.needs_date_backfill ? (
                      <Chip tone="warn">No date</Chip>
                    ) : (
                      <span>
                        {record.effective_date}
                        {record.effective_date_precision === "YEAR" && (
                          <span className="ml-1 text-[10px] text-[--color-ink-3]">(year)</span>
                        )}
                      </span>
                    )}
                  </Td>
                  <Td>
                    {record.submission ? (
                      <StatusBadge status={record.submission.status} />
                    ) : (
                      <span className="text-[--color-ink-3]">—</span>
                    )}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      <NewRecordDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </div>
  );
}

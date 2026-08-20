import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError } from "../../lib/api";
import {
  Button,
  Card,
  CardHeader,
  Chip,
  EmptyState,
  ErrorState,
  Field,
  LoadingState,
  Select,
  Table,
  Td,
  Th,
} from "../../components/ui";
import type { Envelope } from "../../types/api";

type Definition = {
  code: string;
  title: string;
  description: string | null;
  supported_formats: string[];
};

type Run = {
  id: number;
  definition: string | null;
  title: string | null;
  format: string;
  status: string;
  row_count: number | null;
  scope_type: string | null;
  file_hash: string | null;
  generated_at: string | null;
  expires_at: string | null;
};

export function ReportsPage() {
  const queryClient = useQueryClient();
  const [code, setCode] = useState("");
  const [format, setFormat] = useState("CSV");

  const definitions = useQuery({
    queryKey: ["reports", "definitions"],
    queryFn: () => api.get<Envelope<Definition[]>>("/reports/definitions"),
  });

  const runs = useQuery({
    queryKey: ["reports", "runs"],
    queryFn: () => api.get<Envelope<Run[]>>("/reports/runs"),
  });

  const generate = useMutation({
    mutationFn: () => api.post<Envelope<Run>>("/reports/runs", { code, format, parameters: {} }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["reports", "runs"] }),
  });

  const error = generate.error instanceof ApiError ? generate.error : null;
  const available = definitions.data?.data ?? [];
  const selected = available.find((definition) => definition.code === code);

  async function download(run: Run) {
    const blob = await api.download(`/reports/runs/${run.id}/download`);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${run.definition ?? "report"}-${run.id}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Reports</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          Generated from validated records, scoped to what you may see.
        </p>
      </div>

      <Card>
        <CardHeader title="Generate a report" />
        {definitions.isLoading && <LoadingState rows={2} />}
        {definitions.isError && (
          <ErrorState error={definitions.error} onRetry={() => definitions.refetch()} />
        )}
        {definitions.data && (
          <>
            <div className="flex flex-wrap items-end gap-3 p-4">
              <div className="min-w-56 flex-1">
                <Field label="Report">
                  <Select value={code} onChange={(event) => setCode(event.target.value)}>
                    <option value="">Choose a report…</option>
                    {available.map((definition) => (
                      <option key={definition.code} value={definition.code}>
                        {definition.title}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>
              <div className="w-32">
                <Field label="Format">
                  <Select value={format} onChange={(event) => setFormat(event.target.value)}>
                    <option value="CSV">CSV</option>
                    <option value="XLSX">Excel</option>
                    <option value="PDF">PDF</option>
                  </Select>
                </Field>
              </div>
              <Button
                variant="primary"
                disabled={!code || generate.isPending}
                onClick={() => generate.mutate()}
              >
                {generate.isPending ? "Generating…" : "Generate"}
              </Button>
            </div>

            {selected?.description && (
              <p className="px-4 pb-3 text-[12px] text-[--color-ink-3]">{selected.description}</p>
            )}
            {selected && !selected.supported_formats.includes(format) && (
              <p className="px-4 pb-4 text-[12px] text-[--color-warn]">
                {format} is not available yet — only {selected.supported_formats.join(", ")}.
              </p>
            )}
          </>
        )}
        {error && (
          <p role="alert" className="px-4 pb-4 text-[12px] text-[--color-crit]">
            {error.detail}
          </p>
        )}
      </Card>

      <Card>
        <CardHeader title="Generated reports" />
        {runs.isLoading && <LoadingState rows={3} />}
        {runs.isError && <ErrorState error={runs.error} onRetry={() => runs.refetch()} />}
        {runs.data && runs.data.data.length === 0 && (
          <EmptyState title="No reports yet" description="Generate one above." />
        )}
        {runs.data && runs.data.data.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Report</Th>
                <Th>Scope</Th>
                <Th>Rows</Th>
                <Th>Generated</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {runs.data.data.map((run) => (
                <tr key={run.id}>
                  <Td>{run.title ?? run.definition}</Td>
                  <Td>
                    <Chip>{run.scope_type ?? "—"}</Chip>
                  </Td>
                  <Td className="tabular">{run.row_count ?? "—"}</Td>
                  <Td className="tabular whitespace-nowrap">
                    {run.generated_at?.slice(0, 10) ?? "—"}
                  </Td>
                  <Td>
                    {run.status === "READY" ? (
                      <Button size="sm" onClick={() => download(run)}>
                        Download
                      </Button>
                    ) : (
                      <Chip tone={run.status === "FAILED" ? "crit" : "neutral"}>{run.status}</Chip>
                    )}
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

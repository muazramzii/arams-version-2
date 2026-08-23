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
  Input,
  LoadingState,
  Select,
  Table,
  Td,
  Th,
} from "../../components/ui";
import type { Envelope } from "../../types/api";

type Assignable = {
  id: number;
  full_name: string;
  staff_no: string;
  is_archived: boolean;
  assignments: {
    id: number;
    measure: string | null;
    target: number;
    achieved: number;
    status: string;
    deadline: string | null;
  }[];
};

type Period = { id: number; code: string; label: string };
type Measure = { id: number; code: string; label: string; source_kind: string };

/**
 * TDPP assigning KPI to researchers in the faculty they serve.
 *
 * The list is scoped server-side from the appointment, so a TDPP without one
 * sees nobody here — role alone confers nothing.
 */
export function AssignKpiPanel() {
  const queryClient = useQueryClient();
  const [target, setTarget] = useState<Assignable | null>(null);

  const staff = useQuery({
    queryKey: ["kpi", "assignable-staff"],
    queryFn: () => api.get<Envelope<Assignable[]>>("/kpi/assignable-staff"),
  });

  const rows = staff.data?.data ?? [];

  return (
    <>
      <Card>
        <CardHeader title="Researchers in your faculty" />
        {staff.isLoading && <LoadingState rows={4} label="Loading researchers" />}
        {staff.isError && <ErrorState error={staff.error} onRetry={() => staff.refetch()} />}
        {staff.data && rows.length === 0 && (
          <EmptyState
            title="No researchers to show"
            description="You need a current TDPP appointment before you can assign KPI targets."
          />
        )}
        {rows.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Researcher</Th>
                <Th>Current targets</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {rows.map((person) => (
                <tr key={person.id}>
                  <Td>
                    <span className="font-medium">{person.full_name}</span>
                    <span className="ml-2 font-mono text-[11px] text-[--color-ink-3]">
                      {person.staff_no}
                    </span>
                    {person.is_archived && (
                      <span className="ml-2">
                        <Chip>Archived</Chip>
                      </span>
                    )}
                  </Td>
                  <Td>
                    {person.assignments.length === 0 ? (
                      <span className="text-[--color-ink-3]">None</span>
                    ) : (
                      <ul className="flex flex-col gap-1">
                        {person.assignments.map((assignment) => (
                          <li key={assignment.id} className="flex items-center gap-2 text-[12px]">
                            <span>{assignment.measure}</span>
                            <span className="tabular font-mono text-[11px] text-[--color-ink-3]">
                              {assignment.achieved} / {assignment.target}
                            </span>
                            {assignment.status !== "OPEN" && (
                              <Chip tone={assignment.status.startsWith("MET") ? "good" : "crit"}>
                                {assignment.status.replace("_", " ")}
                              </Chip>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </Td>
                  <Td>
                    <Button size="sm" onClick={() => setTarget(person)}>
                      Assign
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      {target && (
        <AssignDialog
          person={target}
          onClose={() => setTarget(null)}
          onDone={() => {
            queryClient.invalidateQueries({ queryKey: ["kpi"] });
            setTarget(null);
          }}
        />
      )}
    </>
  );
}

function AssignDialog({
  person,
  onClose,
  onDone,
}: {
  person: Assignable;
  onClose: () => void;
  onDone: () => void;
}) {
  const [form, setForm] = useState({
    kpi_period_id: "",
    kpi_measure_id: "",
    target_value: "1",
    deadline: "",
    quartile: "",
    indexing_code: "",
    description: "",
  });

  const periods = useQuery({
    queryKey: ["kpi", "periods"],
    queryFn: () => api.get<Envelope<Period[]>>("/kpi/periods"),
  });

  const measures = useQuery({
    queryKey: ["kpi", "measures"],
    queryFn: () => api.get<Envelope<Measure[]>>("/kpi/measures"),
  });

  const assign = useMutation({
    mutationFn: () =>
      api.post("/kpi/assign", {
        staff_profile_id: person.id,
        kpi_period_id: Number(form.kpi_period_id),
        kpi_measure_id: Number(form.kpi_measure_id),
        target_value: Number(form.target_value),
        deadline: form.deadline || null,
        quartile: form.quartile || null,
        indexing_code: form.indexing_code || null,
        description: form.description || null,
      }),
    onSuccess: onDone,
  });

  const error = assign.error instanceof ApiError ? assign.error : null;

  // Quartile and indexing only mean anything for a publication target.
  const selectedMeasure = measures.data?.data.find((m) => String(m.id) === form.kpi_measure_id);
  const isPublication = selectedMeasure?.code === "PUBLICATION_COUNT";

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/30 p-4"
      role="dialog"
      aria-modal="true"
      aria-label={"Assign a KPI target to " + person.full_name}
      onClick={(event) => event.target === event.currentTarget && onClose()}
    >
      <form
        className="my-8 flex w-full max-w-md flex-col gap-3 border border-[--color-rule] bg-[--color-surface] p-5"
        onSubmit={(event) => {
          event.preventDefault();
          assign.mutate();
        }}
      >
        <h2 className="font-serif text-lg font-semibold tracking-tight">
          Assign a target to {person.full_name}
        </h2>

        {(periods.isLoading || measures.isLoading) && <LoadingState rows={3} />}

        <div className="grid grid-cols-2 gap-3">
          <Field label="Period" required error={error?.fieldError("kpi_period_id")}>
            <Select
              required
              value={form.kpi_period_id}
              onChange={(e) => setForm((f) => ({ ...f, kpi_period_id: e.target.value }))}
            >
              <option value="">Choose…</option>
              {periods.data?.data.map((period) => (
                <option key={period.id} value={period.id}>
                  {period.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Target" required error={error?.fieldError("target_value")}>
            <Input
              type="number"
              min="1"
              step="1"
              required
              value={form.target_value}
              onChange={(e) => setForm((f) => ({ ...f, target_value: e.target.value }))}
            />
          </Field>
        </div>

        <Field label="Measure" required error={error?.fieldError("kpi_measure_id")}>
          <Select
            required
            value={form.kpi_measure_id}
            onChange={(e) =>
              setForm((f) => ({
                ...f,
                kpi_measure_id: e.target.value,
                quartile: "",
                indexing_code: "",
              }))
            }
          >
            <option value="">Choose…</option>
            {measures.data?.data
              .filter((measure) => measure.source_kind === "RESEARCH_RECORD")
              .map((measure) => (
                <option key={measure.id} value={measure.id}>
                  {measure.label}
                </option>
              ))}
          </Select>
        </Field>

        {isPublication && (
          <div className="grid grid-cols-2 gap-3">
            <Field label="Quartile" hint="Leave blank for any.">
              <Select
                value={form.quartile}
                onChange={(e) => setForm((f) => ({ ...f, quartile: e.target.value }))}
              >
                <option value="">Any</option>
                {["Q1", "Q2", "Q3", "Q4"].map((q) => (
                  <option key={q}>{q}</option>
                ))}
              </Select>
            </Field>
            <Field label="Indexing" hint="Also matches papers indexed in several places.">
              <Select
                value={form.indexing_code}
                onChange={(e) => setForm((f) => ({ ...f, indexing_code: e.target.value }))}
              >
                <option value="">Any</option>
                <option value="SCOPUS">Scopus</option>
                <option value="WOS">Web of Science</option>
                <option value="MYCITE">MyCite</option>
              </Select>
            </Field>
          </div>
        )}

        <Field label="Deadline" error={error?.fieldError("deadline")} hint="Optional.">
          <Input
            type="date"
            value={form.deadline}
            onChange={(e) => setForm((f) => ({ ...f, deadline: e.target.value }))}
          />
        </Field>

        <Field label="Note" hint="Shown to the researcher.">
          <Input
            value={form.description}
            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
          />
        </Field>

        <p className="rounded-sm bg-[--color-accent-soft] px-2.5 py-2 text-[12px] text-[--color-ink-2]">
          Approved work already dated inside this period counts immediately. Credit follows the
          date of the record itself, not the date it was approved.
        </p>

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">
            {error.detail}
          </p>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="primary" disabled={assign.isPending}>
            {assign.isPending ? "Assigning…" : "Assign target"}
          </Button>
        </div>
      </form>
    </div>
  );
}

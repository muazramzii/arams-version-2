import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { api, ApiError } from "../../lib/api";
import { Button, Chip, Field, Input, LoadingState, Select } from "../../components/ui";
import type { Envelope, ResearchRecord } from "../../types/api";

const CURRENT_YEAR = new Date().getFullYear();

type Reference = { id: number; code: string; label: string; grant_level_id?: number | null };

type References = {
  levels: Reference[];
  categories: Reference[];
  roles: Reference[];
  statuses: Reference[];
  funders: Reference[];
  income_categories: Reference[];
  ip_types: Reference[];
  ip_registration_statuses: Reference[];
  publication_types: Reference[];
  author_roles: Reference[];
  indexings: Reference[];
  award_types: Reference[];
  award_levels: Reference[];
};

type GrantProject = {
  id: number;
  grant_code: string;
  title: string;
  level: string | null;
  total_amount: string | null;
  start_date: string | null;
  participants: string[];
  needs_start_date: boolean;
};

const TYPES = [
  { value: "PUBLICATION", label: "Publication" },
  { value: "GRANT", label: "Research grant" },
  { value: "RESEARCH_INCOME", label: "Research income" },
  { value: "IP_RECORD", label: "Intellectual property" },
  { value: "AWARD", label: "Award" },
];

/**
 * Creates a DRAFT of any of the five research types. Nothing is auto-approved
 * — ARAMS 1.0 had an admin path that inserted records pre-stamped 'Approved',
 * bypassing validation entirely.
 */
export function NewRecordDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [type, setType] = useState("PUBLICATION");
  const [form, setForm] = useState<Record<string, string>>({});
  const [indexings, setIndexings] = useState<number[]>([]);
  const [project, setProject] = useState<GrantProject | null>(null);

  const references = useQuery({
    queryKey: ["reference-data"],
    queryFn: () => api.get<Envelope<References>>("/reference-data"),
    enabled: open,
    staleTime: 10 * 60_000,
  });

  const mutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      api.post<Envelope<ResearchRecord>>("/research-records", payload),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["research-records"] });
      reset();
      onClose();
      if (result.data.submission) navigate(`/submissions/${result.data.submission.id}`);
    },
  });

  const error = mutation.error instanceof ApiError ? mutation.error : null;
  const refs = references.data?.data;

  function reset() {
    setForm({});
    setIndexings([]);
    setProject(null);
    mutation.reset();
  }

  function set(key: string, value: string) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  function num(key: string): number | undefined {
    const value = form[key];
    return value ? Number(value) : undefined;
  }

  if (!open) return null;

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    const payload: Record<string, unknown> = { type };

    switch (type) {
      case "PUBLICATION":
        Object.assign(payload, {
          title: form.title,
          journal_name: form.journal_name || null,
          pub_year: Number(form.pub_year ?? CURRENT_YEAR),
          quartile: form.quartile ?? "N/A",
          doi: form.doi || null,
          publication_type_id: num("publication_type_id"),
          author_role_id: num("author_role_id"),
          indexing_ids: indexings,
        });
        break;

      case "GRANT":
        Object.assign(payload, {
          grant_project_id: project?.id,
          grant_role_id: num("grant_role_id"),
          allocated_amount: form.allocated_amount ? Number(form.allocated_amount) : null,
        });
        break;

      case "RESEARCH_INCOME":
        Object.assign(payload, {
          source_name: form.source_name,
          income_category_id: num("income_category_id"),
          amount: Number(form.amount),
          year_received: Number(form.year_received ?? CURRENT_YEAR),
          received_on: form.received_on || null,
          grant_project_id: project?.id ?? null,
        });
        break;

      case "IP_RECORD":
        Object.assign(payload, {
          title: form.title,
          ip_type_id: num("ip_type_id"),
          ip_registration_status_id: num("ip_registration_status_id"),
          ip_number: form.ip_number || null,
          filing_date: form.filing_date || null,
          grant_date: form.grant_date || null,
        });
        break;

      case "AWARD":
        Object.assign(payload, {
          title: form.title,
          award_type_id: num("award_type_id"),
          award_level_id: num("award_level_id"),
          organiser: form.organiser || null,
          award_year: Number(form.award_year ?? CURRENT_YEAR),
        });
        break;
    }

    mutation.mutate(payload);
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/30 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Add research record"
      onClick={(event) => event.target === event.currentTarget && onClose()}
    >
      <form
        onSubmit={handleSubmit}
        className="my-8 flex w-full max-w-lg flex-col gap-3 border border-[--color-rule] bg-[--color-surface] p-5"
      >
        <h2 className="font-serif text-lg font-semibold tracking-tight">Add research record</h2>

        <Field label="Record type" required>
          <Select
            value={type}
            onChange={(event) => {
              setType(event.target.value);
              reset();
            }}
          >
            {TYPES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </Field>

        {references.isLoading && <LoadingState rows={3} label="Loading options" />}

        {refs && type === "PUBLICATION" && (
          <>
            <Field label="Title" required error={error?.fieldError("title")}>
              <Input required value={form.title ?? ""} onChange={(e) => set("title", e.target.value)} />
            </Field>
            <Field label="Journal or conference">
              <Input value={form.journal_name ?? ""} onChange={(e) => set("journal_name", e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Publication year" required error={error?.fieldError("pub_year")}>
                <Input
                  type="number" min={1950} max={CURRENT_YEAR + 1} required
                  value={form.pub_year ?? String(CURRENT_YEAR)}
                  onChange={(e) => set("pub_year", e.target.value)}
                />
              </Field>
              <Field label="Quartile">
                <Select value={form.quartile ?? "N/A"} onChange={(e) => set("quartile", e.target.value)}>
                  {["Q1", "Q2", "Q3", "Q4", "N/A"].map((q) => <option key={q}>{q}</option>)}
                </Select>
              </Field>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <RefSelect label="Publication type" options={refs.publication_types}
                value={form.publication_type_id} onChange={(v) => set("publication_type_id", v)} />
              <RefSelect label="Your role" options={refs.author_roles}
                value={form.author_role_id} onChange={(v) => set("author_role_id", v)} />
            </div>
            {/*
              A join table, not a single choice — a paper indexed in both
              Scopus and WoS must match either filter. ARAMS 1.0 stored this
              as a SET and compared it with `=`, hiding such papers entirely.
            */}
            <Field label="Indexed in" hint="Select all that apply.">
              <div className="flex flex-wrap gap-2">
                {refs.indexings.map((indexing) => (
                  <label key={indexing.id} className="flex items-center gap-1.5 text-[12px]">
                    <input
                      type="checkbox"
                      checked={indexings.includes(indexing.id)}
                      onChange={(e) =>
                        setIndexings((prev) =>
                          e.target.checked
                            ? [...prev, indexing.id]
                            : prev.filter((id) => id !== indexing.id),
                        )
                      }
                    />
                    {indexing.label}
                  </label>
                ))}
              </div>
            </Field>
            <Field label="DOI" error={error?.fieldError("doi")} hint="Must be unique across ARAMS.">
              <Input value={form.doi ?? ""} onChange={(e) => set("doi", e.target.value)} placeholder="10.1000/…" />
            </Field>
          </>
        )}

        {refs && type === "GRANT" && (
          <>
            <GrantProjectPicker selected={project} onSelect={setProject} references={refs} />
            {project && (
              <div className="grid grid-cols-2 gap-3">
                <RefSelect label="Your role" required options={refs.roles}
                  value={form.grant_role_id} onChange={(v) => set("grant_role_id", v)} />
                <Field label="Your allocation (RM)" hint="Leave blank if not tracked.">
                  <Input type="number" min={0} step="0.01" value={form.allocated_amount ?? ""}
                    onChange={(e) => set("allocated_amount", e.target.value)} />
                </Field>
              </div>
            )}
          </>
        )}

        {refs && type === "RESEARCH_INCOME" && (
          <>
            <Field label="Source" required error={error?.fieldError("source_name")}>
              <Input required value={form.source_name ?? ""}
                onChange={(e) => set("source_name", e.target.value)}
                placeholder="Funder or company name" />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Amount (RM)" required error={error?.fieldError("amount")}>
                <Input type="number" min="0.01" step="0.01" required value={form.amount ?? ""}
                  onChange={(e) => set("amount", e.target.value)} />
              </Field>
              <Field label="Year received" required error={error?.fieldError("year_received")}>
                <Input type="number" min={1950} max={CURRENT_YEAR + 1} required
                  value={form.year_received ?? String(CURRENT_YEAR)}
                  onChange={(e) => set("year_received", e.target.value)} />
              </Field>
            </div>
            <RefSelect label="Category" required options={refs.income_categories}
              value={form.income_category_id} onChange={(v) => set("income_category_id", v)} />
            <GrantProjectPicker
              selected={project} onSelect={setProject} references={refs} optional
              label="Linked grant (optional)"
            />
          </>
        )}

        {refs && type === "IP_RECORD" && (
          <>
            <Field label="Title" required error={error?.fieldError("title")}>
              <Input required value={form.title ?? ""} onChange={(e) => set("title", e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <RefSelect label="IP type" required options={refs.ip_types}
                value={form.ip_type_id} onChange={(v) => set("ip_type_id", v)} />
              <RefSelect label="Status" options={refs.ip_registration_statuses}
                value={form.ip_registration_status_id}
                onChange={(v) => set("ip_registration_status_id", v)} />
            </div>
            <Field label="Registration number" hint="MyIPO reference, if any.">
              <Input value={form.ip_number ?? ""} onChange={(e) => set("ip_number", e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Filing date" error={error?.fieldError("filing_date")}>
                <Input type="date" value={form.filing_date ?? ""}
                  onChange={(e) => set("filing_date", e.target.value)} />
              </Field>
              <Field label="Grant date" error={error?.fieldError("grant_date")}>
                <Input type="date" value={form.grant_date ?? ""}
                  onChange={(e) => set("grant_date", e.target.value)} />
              </Field>
            </div>
            {!form.filing_date && !form.grant_date && (
              <p className="rounded-sm bg-[--color-warn-soft] px-2.5 py-2 text-[12px] text-[--color-warn]">
                Without a date this record cannot be credited to a KPI period. It will still count
                in totals, and you can add one later.
              </p>
            )}
          </>
        )}

        {refs && type === "AWARD" && (
          <>
            <Field label="Award name" required error={error?.fieldError("title")}>
              <Input required value={form.title ?? ""} onChange={(e) => set("title", e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <RefSelect label="Award type" options={refs.award_types}
                value={form.award_type_id} onChange={(v) => set("award_type_id", v)} />
              <RefSelect label="Level" options={refs.award_levels}
                value={form.award_level_id} onChange={(v) => set("award_level_id", v)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Organiser">
                <Input value={form.organiser ?? ""} onChange={(e) => set("organiser", e.target.value)} />
              </Field>
              <Field label="Year" required error={error?.fieldError("award_year")}>
                <Input type="number" min={1950} max={CURRENT_YEAR + 1} required
                  value={form.award_year ?? String(CURRENT_YEAR)}
                  onChange={(e) => set("award_year", e.target.value)} />
              </Field>
            </div>
          </>
        )}

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">{error.detail}</p>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={() => { reset(); onClose(); }}>
            Cancel
          </Button>
          <Button
            type="submit"
            variant="primary"
            disabled={mutation.isPending || (type === "GRANT" && !project)}
          >
            {mutation.isPending ? "Saving…" : "Save as draft"}
          </Button>
        </div>
      </form>
    </div>
  );
}

function RefSelect({
  label, options, value, onChange, required,
}: {
  label: string;
  options: Reference[];
  value: string | undefined;
  onChange: (value: string) => void;
  required?: boolean;
}) {
  return (
    <Field label={label} required={required}>
      <Select required={required} value={value ?? ""} onChange={(e) => onChange(e.target.value)}>
        <option value="">— none —</option>
        {options.map((option) => (
          <option key={option.id} value={option.id}>{option.label}</option>
        ))}
      </Select>
    </Field>
  );
}

/**
 * Search first, create only if it genuinely is not there.
 *
 * This ordering is the whole point of the project/participation split: two
 * lecturers on one grant attach to the same project. ARAMS 1.0 had no such
 * step, so eleven codes were registered twice and RM 420,000 of funding was
 * counted more than once.
 */
function GrantProjectPicker({
  selected, onSelect, references, optional, label = "Grant",
}: {
  selected: GrantProject | null;
  onSelect: (project: GrantProject | null) => void;
  references: References;
  optional?: boolean;
  label?: string;
}) {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [creating, setCreating] = useState(false);
  const [draft, setDraft] = useState<Record<string, string>>({});

  const projects = useQuery({
    queryKey: ["grant-projects", search],
    queryFn: () => api.get<Envelope<GrantProject[]>>("/grant-projects", { search: search || undefined }),
  });

  const create = useMutation({
    mutationFn: () =>
      api.post<Envelope<GrantProject>>("/grant-projects", {
        grant_code: draft.grant_code,
        title: draft.title,
        grant_level_id: draft.grant_level_id ? Number(draft.grant_level_id) : null,
        funder_id: draft.funder_id ? Number(draft.funder_id) : null,
        total_amount: draft.total_amount ? Number(draft.total_amount) : null,
        start_date: draft.start_date || null,
        end_date: draft.end_date || null,
      }),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["grant-projects"] });
      onSelect({ ...result.data, participants: [], level: null } as GrantProject);
      setCreating(false);
    },
  });

  const createError = create.error instanceof ApiError ? create.error : null;
  const results = projects.data?.data ?? [];

  if (selected) {
    return (
      <div className="border border-[--color-rule] bg-[--color-surface-2] px-3 py-2.5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="font-mono text-[11px] text-[--color-ink-3]">{selected.grant_code}</div>
            <div className="text-[13px] font-medium">{selected.title}</div>
            {selected.participants.length > 0 && (
              <div className="mt-1 text-[11px] text-[--color-ink-3]">
                Already claimed by: {selected.participants.join(", ")}
              </div>
            )}
            {selected.needs_start_date && (
              <p className="mt-1.5 text-[11px] text-[--color-warn]">
                This grant has no start date, so it cannot be credited to a KPI period.
              </p>
            )}
          </div>
          <button
            type="button"
            className="shrink-0 text-[12px] text-[--color-ink-3] hover:text-[--color-ink]"
            onClick={() => onSelect(null)}
          >
            Change
          </button>
        </div>
      </div>
    );
  }

  if (creating) {
    return (
      <div className="flex flex-col gap-3 border border-[--color-rule] p-3">
        <div className="flex items-center justify-between">
          <span className="text-[12px] font-medium">Register a new grant</span>
          <button type="button" className="text-[12px] text-[--color-ink-3]"
            onClick={() => setCreating(false)}>
            Back to search
          </button>
        </div>
        <Field label="Grant code" required error={createError?.fieldError("grant_code")}>
          <Input value={draft.grant_code ?? ""}
            onChange={(e) => setDraft((d) => ({ ...d, grant_code: e.target.value }))}
            placeholder="e.g. Q940 or FRGS/1/2026/ICT02/UTHM/01/1" />
        </Field>
        <Field label="Grant title" required error={createError?.fieldError("title")}>
          <Input value={draft.title ?? ""}
            onChange={(e) => setDraft((d) => ({ ...d, title: e.target.value }))} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <RefSelect label="Level" options={references.levels} value={draft.grant_level_id}
            onChange={(v) => setDraft((d) => ({ ...d, grant_level_id: v }))} />
          <RefSelect label="Funder" options={references.funders} value={draft.funder_id}
            onChange={(v) => setDraft((d) => ({ ...d, funder_id: v }))} />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Total amount (RM)">
            <Input type="number" min={0} step="0.01" value={draft.total_amount ?? ""}
              onChange={(e) => setDraft((d) => ({ ...d, total_amount: e.target.value }))} />
          </Field>
          <Field label="Start date" hint="Needed for KPI periods.">
            <Input type="date" value={draft.start_date ?? ""}
              onChange={(e) => setDraft((d) => ({ ...d, start_date: e.target.value }))} />
          </Field>
        </div>
        {createError && !createError.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">{createError.detail}</p>
        )}
        <Button type="button" variant="primary" disabled={create.isPending}
          onClick={() => create.mutate()}>
          {create.isPending ? "Registering…" : "Register grant"}
        </Button>
      </div>
    );
  }

  return (
    <Field
      label={label}
      required={!optional}
      hint="Search by code or title. Register a new one only if it is genuinely not listed."
    >
      <Input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Search grant code or title"
      />
      <div className="mt-1 max-h-40 overflow-y-auto border border-[--color-rule]">
        {projects.isLoading && <LoadingState rows={2} />}
        {results.length === 0 && !projects.isLoading && (
          <p className="px-3 py-3 text-center text-[12px] text-[--color-ink-3]">
            No grants match.
          </p>
        )}
        {results.map((item) => (
          <button
            key={item.id}
            type="button"
            className="flex w-full flex-col items-start gap-0.5 border-b border-[--color-rule] px-3 py-2 text-left last:border-b-0 hover:bg-[--color-surface-2]"
            onClick={() => onSelect(item)}
          >
            <span className="font-mono text-[11px] text-[--color-ink-3]">{item.grant_code}</span>
            <span className="text-[12px]">{item.title}</span>
            {item.participants.length > 0 && (
              <span className="text-[10px] text-[--color-ink-3]">
                {item.participants.length} participant(s)
              </span>
            )}
          </button>
        ))}
      </div>
      <div className="mt-1.5 flex items-center gap-2">
        <Chip>Not listed?</Chip>
        <button type="button" className="text-[12px] text-[--color-accent] hover:underline"
          onClick={() => setCreating(true)}>
          Register a new grant
        </button>
      </div>
    </Field>
  );
}
